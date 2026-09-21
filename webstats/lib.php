<?php
declare(strict_types=1);
use Jasr\Framework\JsonStore;
use Jasr\Framework\Response;

function config(): array {
    static $config;
    if ($config !== null) return $config;
    $explicit = getenv('JASR_WEBSTATS_CONFIG');
    $path = $explicit ?: __DIR__ . '/config.local.php';
    if ($explicit && !is_file($path)) throw new RuntimeException('Configured settings file is missing.');
    if (is_file($path)) {
        $value = require $path; // Existing installations retain credentials, secret and storage.
    } else {
        $value = require __DIR__ . '/config.example.php';
        $dir = validate_storage_path($value['storage']);
        $settings = $dir . '/settings.json';
        if (!is_file($settings)) {
            JsonStore::update($settings, static function (array $records) use ($dir): array {
                if ($records) return $records; // Another first request initialized it.
                if (glob($dir . '/events-*.json') || is_file($dir . '/admin.json')) {
                    throw new RuntimeException('Restore existing settings; refusing to reset an existing installation.');
                }
                $initial = require __DIR__ . '/initial-admin.php';
                return $initial + ['secret' => bin2hex(random_bytes(32)), 'must_change_password' => true];
            });
        }
        $settingsData = JsonStore::read($settings)['records'];
        if (!$settingsData) throw new RuntimeException('Invalid saved settings.');
        $value = array_replace($value, $settingsData);
    }
    if (!is_array($value)) throw new RuntimeException('Invalid configuration.');
    $dir = validate_storage_path($value['storage'] ?? '');
    // Password changes are stored separately so legacy config files need not be writable.
    if (is_file($dir . '/admin.json')) {
        $admin = JsonStore::read($dir . '/admin.json')['records'];
        if (!isset($admin['username'], $admin['password_hash'], $admin['must_change_password'])) throw new RuntimeException('Invalid administrator record.');
        $value = array_replace($value, $admin);
    }
    if (strlen($value['secret'] ?? '') < 32 ||
        empty(password_get_info($value['password_hash'] ?? '')['algo']) ||
        !is_string($value['username'] ?? null) || $value['username'] === '' || empty($value['origins']) ||
        !is_array($value['origins']) || !is_array($value['excluded_paths'] ?? null)) throw new RuntimeException('Invalid configuration.');
    new DateTimeZone($value['timezone']);
    foreach (['retention_days', 'events_per_ip_per_minute', 'events_per_day'] as $key) {
        if (!is_int($value[$key] ?? null) || $value[$key] < 1) throw new RuntimeException('Invalid limit.');
    }
    $value['must_change_password'] = (bool)($value['must_change_password'] ?? false);
    $config = $value;
    return $config;
}

/** Permit the protected, ignored data folder; retain existing external storage support. */
function validate_storage_path(string $path): string {
    $dir = realpath($path);
    if (!$dir || !is_dir($dir)) throw new RuntimeException('Storage directory must exist.');
    $local = realpath(__DIR__ . '/data');
    if ($local && $dir === $local && !is_link(__DIR__ . '/data')) {
        $rule = $dir . '/.htaccess';
        if (!is_file($rule) || !preg_match('/^Require all denied\s*$/m', (string)file_get_contents($rule))) {
            throw new RuntimeException('Runtime data access protection is missing.');
        }
        return $dir;
    }
    $roots = [realpath(dirname(__DIR__))];
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($documentRoot !== '') $roots[] = realpath($documentRoot);
    foreach ($roots as $root) {
        if ($root && ($dir === $root || str_starts_with($dir, rtrim($root, '/') . '/'))) {
            throw new RuntimeException('Use webstats/data or private external storage.');
        }
    }
    return $dir;
}

function storage(): string { return validate_storage_path(config()['storage']); }

function credential_version(array $cfg): string {
    return hash('sha256', $cfg['username'] . ':' . $cfg['password_hash']);
}

function change_initial_password(array $cfg, string $password, string $confirmation): void {
    if (!$cfg['must_change_password']) throw new InvalidArgumentException('Initial password has already been changed.');
    if ($password !== $confirmation) throw new InvalidArgumentException('Passwords do not match.');
    if (strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
    if (password_verify($password, $cfg['password_hash'])) throw new InvalidArgumentException('Choose a different password from the temporary password.');
    JsonStore::update(storage() . '/admin.json', static function (array $records) use ($cfg, $password): array {
        if ($records && (!($records['must_change_password'] ?? false) || $records['password_hash'] !== $cfg['password_hash'])) {
            throw new InvalidArgumentException('Credentials changed in another session. Sign in again.');
        }
        return ['username' => $cfg['username'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => false];
    });
}

function allowance(string $key, int $limit, int $seconds): bool {
    $now = time();
    $key = hash_hmac('sha256', $key . ':' . intdiv($now, $seconds), config()['secret']);
    $allowed = false;
    JsonStore::update(storage() . '/limits.json', function (array $records) use ($key, $now, $seconds, $limit, &$allowed): array {
        $records = array_filter($records, static fn(array $r): bool => $r['expires'] > $now);
        // A bounded limiter; expire entries before admitting new addresses.
        if (!isset($records[$key]) && count($records) >= 10000) return $records;
        $entry = $records[$key] ?? ['count' => 0, 'expires' => $now + $seconds];
        $allowed = ++$entry['count'] <= $limit;
        $records[$key] = $entry;
        return $records;
    });
    return $allowed;
}

function clean_url(string $value): string {
    $url = parse_url($value);
    if (!$url || !in_array(strtolower($url['scheme'] ?? ''), ['https', 'http'], true) || empty($url['host'])) return '';
    return strtolower($url['scheme'] . '://' . $url['host']) .
        (isset($url['port']) ? ':' . $url['port'] : '') . ($url['path'] ?? '/');
}
function url_origin(string $value): string {
    $url = parse_url($value);
    if (!$url || !isset($url['scheme'], $url['host'])) return '';
    return strtolower($url['scheme'] . '://' . $url['host']) . (isset($url['port']) ? ':' . $url['port'] : '');
}
function normalize_event(array $event, string $origin): ?array {
    $cfg = config();
    if (!isset($cfg['origins'][$origin])) return null;
    foreach (['id', 'kind', 'page', 'target', 'label', 'referrer', 'session'] as $key) {
        if (!isset($event[$key]) || !is_string($event[$key]) || strlen($event[$key]) > 2048) return null;
    }
    if (!preg_match('/^[a-f0-9-]{16,64}$/D', $event['id']) ||
        !preg_match('/^[a-f0-9-]{16,64}$/D', $event['session']) ||
        !in_array($event['kind'], ['pageview', 'click'], true)) return null;
    $page = clean_url($event['page']);
    if (!$page || url_origin($page) !== $origin) return null;
    $path = rawurldecode(parse_url($page, PHP_URL_PATH) ?: '/');
    foreach ($cfg['excluded_paths'] as $prefix) {
        if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) return null;
    }
    $label = preg_match('/^[a-zA-Z0-9_.:-]{0,80}$/D', $event['label']) ? $event['label'] : '';
    $day = (new DateTimeImmutable('now', new DateTimeZone($cfg['timezone'])))->format('Y-m-d');
    return ['id' => $event['id'], 'occurred' => time(), 'site' => $cfg['origins'][$origin],
        'kind' => $event['kind'], 'page' => $page,
        'target' => $event['kind'] === 'click' ? clean_url($event['target']) : '',
        'label' => $event['kind'] === 'click' ? $label : '', 'referrer' => url_origin(clean_url($event['referrer'])),
        'session' => hash_hmac('sha256', $cfg['origins'][$origin] . '|' . $day . '|' . $event['session'], $cfg['secret'])];
}
function save_event(array $event): void {
    $date = gmdate('Y-m-d', $event['occurred']);
    JsonStore::update(storage() . '/events-' . $date . '.json', function (array $records) use ($event): array {
        $key = $event['site'] . ':' . $event['id'];
        if (isset($records[$key])) return $records;
        if (count($records) >= config()['events_per_day']) throw new OverflowException('Daily capacity reached.');
        $records[$key] = $event;
        return $records;
    });
}
function events_between(int $start, int $end, string $site): Generator {
    for ($day = strtotime(gmdate('Y-m-d', $start) . ' UTC'); $day < $end; $day += 86400) {
        foreach (JsonStore::read(storage() . '/events-' . gmdate('Y-m-d', $day) . '.json')['records'] as $event) {
            if ($event['occurred'] >= $start && $event['occurred'] < $end && ($site === '' || $site === $event['site'])) yield $event;
        }
    }
}
function fail(int $status, string $message): never {
    Response::json($status, $message, null, [['code' => 'REQUEST_FAILED', 'message' => $message]]);
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
