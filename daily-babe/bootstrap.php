<?php

declare(strict_types=1);

const BABY_TRACKER_VERSION = '2.7.0';
const BABY_TRACKER_PROJECT = 'daily-babe';

require_once __DIR__ . '/lib/unlisted-access.php';
require_once __DIR__ . '/lib/thumbnails.php';
require_once __DIR__ . '/lib/project-metadata.php';

$dailyConfigFile = __DIR__ . '/config/config.php';
$config = require is_file($dailyConfigFile) ? $dailyConfigFile : __DIR__ . '/config/config.example.php';
if (!is_array($config)) {
    throw new RuntimeException('Application configuration must return an array.');
}

date_default_timezone_set((string) ($config['timezone'] ?? 'America/New_York'));

require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
$dailyIdentity = \Jasr\Framework\SharedIdentity::connect();

$storageRoot = rtrim((string) ($config['storage_path'] ?? (__DIR__ . '/storage')), DIRECTORY_SEPARATOR);
foreach (['central/data', 'central/logs', 'central/backups', 'central/babies'] as $folder) {
    $path = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to initialize Daily Babe storage.');
    }
}

function daily_auth(): \Jasr\Users\Auth
{
    global $dailyIdentity;
    return $dailyIdentity;
}

function storage_path(string $relative = ''): string
{
    global $storageRoot;
    return $storageRoot . ($relative === '' ? '' : DIRECTORY_SEPARATOR . ltrim($relative, '/\\'));
}

function central_storage_path(string $relative = ''): string
{
    return storage_path('central' . ($relative === '' ? '' : '/' . ltrim($relative, '/\\')));
}

function json_read(string $relative, array $default): array
{
    $path = storage_path($relative);
    if (!is_file($path)) {
        return $default;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to read application data.');
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Unable to lock application data.');
        }
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : $default;
}

function json_write(string $relative, array $data): void
{
    $path = storage_path($relative);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create application data directory.');
    }
    $temporary = tempnam($directory, '.write-');
    if ($temporary === false) {
        throw new RuntimeException('Unable to prepare application data.');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $handle = fopen($temporary, 'wb');
    if ($handle === false) {
        @unlink($temporary);
        throw new RuntimeException('Unable to write application data.');
    }
    try {
        if (!flock($handle, LOCK_EX) || fwrite($handle, $json) === false) {
            throw new RuntimeException('Unable to save application data.');
        }
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    chmod($temporary, 0640);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to finalize application data.');
    }
}

function tracker_state(): array
{
    $state = json_read('central/data/state.json', []);
    return $state + [
        'schemaVersion' => 1,
        'babies' => [],
        'memberships' => [],
        'updatedAt' => gmdate('c'),
    ];
}

function write_tracker_state(array $state): void
{
    $state['schemaVersion'] = 1;
    $state['updatedAt'] = gmdate('c');
    json_write('central/data/state.json', $state);
}

/** Compatibility name used by existing project routes; contains no credentials. */
function auth_data(): array
{
    return tracker_state();
}

/** Compatibility name used by existing project routes; writes memberships/gallery metadata only. */
function write_auth(array $state): void
{
    write_tracker_state($state);
}

function ensure_project_registered(): void
{
    $auth = daily_auth();
    $directory = $auth->directory->read();
    if (isset($directory['projects'][BABY_TRACKER_PROJECT])) {
        return;
    }
    $user = $auth->user();
    if (!is_array($user) || empty($user['admin'])) {
        return;
    }
    $auth->directory->administer(
        (string) $user['id'],
        (int) $user['version'],
        'save-project',
        [
            'project' => BABY_TRACKER_PROJECT,
            'name' => 'Daily Babe',
            'path' => '/github/daily-babe/',
        ]
    );
}

function daily_project_role(?array $user = null): string
{
    $user ??= daily_auth()->user();
    if (!is_array($user)) {
        return '';
    }
    return (string) (\Jasr\Users\Permissions::projectRole($user, BABY_TRACKER_PROJECT) ?? '');
}

function daily_require_user(string $minimum = 'viewer', bool $json = false): array
{
    ensure_project_registered();
    $user = daily_auth()->requireProject(BABY_TRACKER_PROJECT, $minimum, $json);
    ensure_initial_gallery($user);
    if (!daily_auth()->can(BABY_TRACKER_PROJECT, 'admin')) {
        $membership = tracker_state()['memberships'][(string) $user['id']] ?? null;
        if (!is_array($membership) || ($membership['babyIds'] ?? []) === []) {
            if ($json) {
                json_response(false, 'Your account has Daily Babe access but has not been assigned to a baby gallery.', null, [['code' => 'GALLERY_ACCESS_REQUIRED']], 403);
            }
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Your account has not been assigned to a Daily Babe gallery. Ask a Daily Babe administrator for access.');
        }
    }
    return $user;
}

function require_project_role(string $minimum, bool $json = true): array
{
    return daily_require_user($minimum, $json);
}

function is_configured(): bool
{
    return isset(daily_auth()->directory->read()['projects'][BABY_TRACKER_PROJECT]);
}

function is_authenticated(): bool
{
    ensure_project_registered();
    return daily_auth()->can(BABY_TRACKER_PROJECT, 'viewer');
}

function require_auth(): void
{
    daily_require_user('viewer', true);
}

function csrf_token(): string
{
    return daily_auth()->csrf();
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!daily_auth()->validCsrf(is_string($provided) ? $provided : null)) {
        json_response(false, 'Your session expired. Refresh and try again.', null, [['code' => 'CSRF_FAILED']], 419);
    }
}

function json_response(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
        'meta' => ['timestamp' => gmdate('c'), 'requestId' => request_id()],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function request_id(): string
{
    static $id;
    return $id ??= bin2hex(random_bytes(8));
}

function audit_event(string $event, array $details = []): void
{
    $user = daily_auth()->user();
    if (is_array($user)) {
        $details['centralUserId'] = (string) $user['id'];
        $details['username'] = (string) $user['username'];
    }
    if (!empty($_SESSION['daily_babe_selected_baby_id'])) {
        $details['babyId'] = (string) $_SESSION['daily_babe_selected_baby_id'];
    }
    $entry = json_encode([
        'timestamp' => gmdate('c'),
        'requestId' => request_id(),
        'event' => $event,
        'details' => $details,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    file_put_contents(central_storage_path('logs/audit-' . date('Y-m') . '.jsonl'), $entry, FILE_APPEND | LOCK_EX);
}

function default_baby_settings(string $name = 'Baby'): array
{
    global $config;
    return [
        'schemaVersion' => 7,
        'babyName' => $name,
        'gender' => '',
        'dueDate' => (string) ($config['due_date'] ?? '2026-11-02'),
        'birthDate' => '',
        'birthTime' => '',
        'birthLengthInches' => '',
        'birthWeightPounds' => '',
        'birthWeightOunces' => '',
        'reminderEnabled' => false,
        'reminderTime' => '20:00',
        'reminderEmails' => '',
        'reminderSmsEnabled' => false,
        'reminderSmsNumber' => '',
        'visibility' => 'private',
        'updatedAt' => gmdate('c'),
    ];
}

function baby_root(string $babyId): string
{
    if (preg_match('/^[a-f0-9]{24}$/', $babyId) !== 1) {
        throw new RuntimeException('Invalid baby gallery identifier.');
    }
    return central_storage_path('babies/' . $babyId);
}

function initialize_baby_storage(string $babyId, string $name = 'Baby'): void
{
    foreach (['data', 'uploads', 'thumbnails', 'backups'] as $folder) {
        $path = baby_root($babyId) . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to initialize baby storage.');
        }
    }
    $settingsPath = 'central/babies/' . $babyId . '/data/settings.json';
    if (!is_file(storage_path($settingsPath))) {
        json_write($settingsPath, default_baby_settings($name));
    }
}

function baby_thumbnail_path(string $babyId, string $photoId): string
{
    if (preg_match('/^[a-f0-9]{24}$/', $photoId) !== 1) {
        throw new RuntimeException('Invalid photo identifier.');
    }
    return baby_root($babyId) . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . $photoId . '.webp';
}

function ensure_baby_thumbnail(string $babyId, array $record): ?string
{
    $photoId = (string) ($record['id'] ?? '');
    $sourcePath = baby_root($babyId) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename((string) ($record['filename'] ?? ''));
    $thumbnailPath = baby_thumbnail_path($babyId, $photoId);
    if (is_file($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($sourcePath)) {
        return $thumbnailPath;
    }
    return create_gallery_thumbnail($sourcePath, $thumbnailPath, (string) ($record['mimeType'] ?? '')) ? $thumbnailPath : null;
}

function ensure_initial_gallery(array $user): void
{
    $state = tracker_state();
    if ($state['babies'] !== []) {
        return;
    }
    if (!daily_auth()->can(BABY_TRACKER_PROJECT, 'admin')) {
        throw new RuntimeException('A Daily Babe administrator must initialize the first gallery.');
    }
    $babyId = bin2hex(random_bytes(12));
    initialize_baby_storage($babyId, 'Baby');
    $state['babies'][$babyId] = ['name' => 'Baby', 'createdAt' => gmdate('c')];
    $state['memberships'][(string) $user['id']] = [
        'babyIds' => [$babyId],
        'homeBabyId' => $babyId,
        'updatedAt' => gmdate('c'),
    ];
    write_tracker_state($state);
    $_SESSION['daily_babe_selected_baby_id'] = $babyId;
    audit_event('central.gallery_initialized', ['babyId' => $babyId]);
}

function current_account(): array
{
    $user = daily_auth()->user();
    if (!is_array($user)) {
        throw new RuntimeException('No central user is signed in.');
    }
    $state = tracker_state();
    $membership = $state['memberships'][(string) $user['id']] ?? ['babyIds' => [], 'homeBabyId' => ''];
    $role = daily_project_role($user);
    if (daily_auth()->can(BABY_TRACKER_PROJECT, 'admin')) {
        $membership['babyIds'] = array_keys($state['babies']);
        $membership['homeBabyId'] = in_array($membership['homeBabyId'] ?? '', $membership['babyIds'], true)
            ? $membership['homeBabyId']
            : (string) ($membership['babyIds'][0] ?? '');
    }
    return [
        'userId' => (string) $user['id'],
        'username' => (string) $user['username'],
        'name' => (string) ($user['name'] ?? $user['username']),
        'role' => $role,
        'babyIds' => array_values($membership['babyIds'] ?? []),
        'homeBabyId' => (string) ($membership['homeBabyId'] ?? ''),
    ];
}

function can_access_baby(array $account, string $babyId, array $state): bool
{
    return isset($state['babies'][$babyId])
        && (in_array($account['role'] ?? '', ['admin', 'super_admin'], true)
            || in_array($babyId, $account['babyIds'] ?? [], true));
}

function is_super_admin(): bool
{
    return daily_auth()->can(BABY_TRACKER_PROJECT, 'admin');
}

function current_baby_id(): string
{
    $state = tracker_state();
    $account = current_account();
    $selected = (string) ($_SESSION['daily_babe_selected_baby_id'] ?? '');
    if ($selected !== '' && can_access_baby($account, $selected, $state)) {
        return $selected;
    }
    $home = (string) ($account['homeBabyId'] ?? '');
    if ($home !== '' && can_access_baby($account, $home, $state)) {
        $_SESSION['daily_babe_selected_baby_id'] = $home;
        return $home;
    }
    foreach ($state['babies'] as $babyId => $_baby) {
        if (can_access_baby($account, (string) $babyId, $state)) {
            $_SESSION['daily_babe_selected_baby_id'] = (string) $babyId;
            return (string) $babyId;
        }
    }
    throw new RuntimeException('No baby gallery is assigned to this central account.');
}

function baby_storage_path(string $relative = ''): string
{
    $root = baby_root(current_baby_id());
    return $root . ($relative === '' ? '' : DIRECTORY_SEPARATOR . ltrim($relative, '/\\'));
}

function baby_json_read(string $relative, array $default): array
{
    return json_read('central/babies/' . current_baby_id() . '/' . ltrim($relative, '/\\'), $default);
}

function baby_json_write(string $relative, array $data): void
{
    json_write('central/babies/' . current_baby_id() . '/' . ltrim($relative, '/\\'), $data);
}

function tracker_settings(): array
{
    $state = tracker_state();
    $babyId = current_baby_id();
    return baby_json_read('data/settings.json', default_baby_settings((string) ($state['babies'][$babyId]['name'] ?? 'Baby')));
}

function tracker_entries(): array
{
    return baby_json_read('data/entries.json', [
        'schemaVersion' => 1,
        'updatedAt' => gmdate('c'),
        'records' => [],
    ]);
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function public_gallery_slug(array $settings): string
{
    $birthDate = (string) ($settings['birthDate'] ?? '');
    if (!valid_date($birthDate)) {
        return '';
    }
    $name = trim((string) ($settings['babyName'] ?? ''));
    $firstName = preg_split('/\s+/', $name, 2)[0] ?? '';
    $asciiName = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $firstName) : $firstName;
    $slugName = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $asciiName));
    $slugName = trim($slugName, '-');
    if ($slugName === '') {
        return '';
    }
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    return $birth === false ? '' : $slugName . '-' . $birth->format('mdY');
}

function gallery_visibility(array $settings): string
{
    if (array_key_exists('visibility', $settings)) {
        $visibility = (string) $settings['visibility'];
        return in_array($visibility, ['private', 'unlisted', 'public'], true) ? $visibility : 'private';
    }
    // Existing shared galleries had direct links but no public directory.
    return ($settings['publicEnabled'] ?? false) === true ? 'unlisted' : 'private';
}

function shared_gallery_identifier(string $babyId, array $settings): string
{
    $visibility = gallery_visibility($settings);
    if ($visibility === 'private') {
        return '';
    }
    return $visibility === 'unlisted' ? unlisted_gallery_identifier($babyId) : public_gallery_slug($settings);
}

function unlisted_gallery_access_granted(string $babyId): bool
{
    return isset($_SESSION['daily_babe_unlisted_access'][$babyId]);
}

function grant_unlisted_gallery_access(string $babyId): void
{
    $_SESSION['daily_babe_unlisted_access'][$babyId] = time();
    unset($_SESSION['daily_babe_unlisted_attempts'][$babyId]);
}

function unlisted_gallery_csrf_token(): string
{
    if (empty($_SESSION['daily_babe_unlisted_csrf'])) {
        $_SESSION['daily_babe_unlisted_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['daily_babe_unlisted_csrf'];
}

function valid_unlisted_gallery_csrf(string $token): bool
{
    return $token !== '' && hash_equals(unlisted_gallery_csrf_token(), $token);
}

function unlisted_gallery_attempt_allowed(string $babyId): bool
{
    $cutoff = time() - 900;
    $attempts = array_values(array_filter(
        $_SESSION['daily_babe_unlisted_attempts'][$babyId] ?? [],
        static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
    ));
    $_SESSION['daily_babe_unlisted_attempts'][$babyId] = $attempts;
    return count($attempts) < 8;
}

function record_unlisted_gallery_attempt(string $babyId): void
{
    $_SESSION['daily_babe_unlisted_attempts'][$babyId][] = time();
}

function public_gallery_by_slug(string $slug): ?array
{
    if (preg_match('/^(?:u-[a-f0-9]{40}|[a-z0-9][a-z0-9-]{1,88}-\d{8})$/D', $slug) !== 1) {
        return null;
    }
    $state = tracker_state();
    foreach ($state['babies'] as $babyId => $_baby) {
        $babyId = (string) $babyId;
        if (preg_match('/^[a-f0-9]{24}$/', $babyId) !== 1) {
            continue;
        }
        $settings = json_read('central/babies/' . $babyId . '/data/settings.json', []);
        if (gallery_visibility($settings) === 'private') {
            continue;
        }
        $candidate = shared_gallery_identifier($babyId, $settings);
        if ($candidate !== '' && hash_equals($candidate, $slug)) {
            return ['babyId' => $babyId, 'settings' => $settings];
        }
    }
    return null;
}

function central_users_with_project_access(): array
{
    $directory = daily_auth()->directory->read();
    $users = [];
    foreach ($directory['users'] ?? [] as $user) {
        if (!is_array($user) || empty($user['active'])) {
            continue;
        }
        $public = \Jasr\Users\Directory::publicUser($user);
        $role = \Jasr\Users\Permissions::projectRole($public, BABY_TRACKER_PROJECT);
        if ($role === null) {
            continue;
        }
        $users[] = [
            'id' => (string) $public['id'],
            'username' => (string) $public['username'],
            'name' => (string) ($public['name'] ?? $public['username']),
            'projectRole' => (string) $role,
        ];
    }
    usort($users, static fn(array $left, array $right): int => strcasecmp($left['name'], $right['name']));
    return $users;
}


