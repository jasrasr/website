<?php
declare(strict_types=1);
use Jasr\Framework\JsonStore;
use Jasr\Framework\Response;

function config(): array {
    static $config;
    if ($config !== null) return $config;
    $path = getenv('JASR_WEBSTATS_CONFIG') ?: __DIR__ . '/config.local.php';
    if (!is_file($path)) throw new RuntimeException('Webstats is not configured.');
    $value = require $path;
    if (!is_array($value) || strlen($value['secret'] ?? '') < 32 ||
        empty(password_get_info($value['password_hash'] ?? '')['algo']) ||
        !is_string($value['username'] ?? null) || $value['username'] === '' || empty($value['origins']) ||
        !is_array($value['origins']) || !is_array($value['excluded_paths'] ?? null)) throw new RuntimeException('Invalid configuration.');
    new DateTimeZone($value['timezone']);
    foreach (['retention_days', 'events_per_ip_per_minute', 'events_per_day'] as $key) {
        if (!is_int($value[$key] ?? null) || $value[$key] < 1) throw new RuntimeException('Invalid limit.');
    }
    $config = $value;
    return $config;
}

function storage(): string {
    $dir = realpath(config()['storage']);
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
    if (!$dir || ($root && ($dir === $root || str_starts_with($dir, $root . '/')))) {
        throw new RuntimeException('Storage must exist outside the document root.');
    }
    return $dir;
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
