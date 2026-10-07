<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
require_once dirname(__DIR__) . '/user-management/bootstrap.php';

function gb_setup_owner(?array $user): bool {
    return $user !== null && ($user['username'] ?? '') === 'jasrasr'
        && ($user['role'] ?? '') === 'super_admin' && !empty($user['admin'])
        && !empty($user['active']) && empty($user['demo']) && empty($user['mustChangePassword']);
}
function gb_identity_enabled(array $config): bool {
    return is_file($config['data_dir'] . '/identity-enabled.php');
}
function gb_project_path(): string {
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/github/game-buzzer/api.php'), '/') . '/';
    if (!preg_match('~^/(?:[a-zA-Z0-9_-]+/)+$~D', $path)) gb_fail('Invalid project deployment path.', 503);
    return $path;
}
function gb_identity_status(array $config): array {
    $auth = jasr_users_auth();
    $user = $auth->user();
    $enabled = gb_identity_enabled($config);
    $password = $config['create_password'] ?? '';
    return ['enabled' => $enabled, 'setupAllowed' => gb_setup_owner($user),
        'legacyAvailable' => !$enabled && strlen($password) >= 12 && $password !== 'REPLACE-WITH-A-LONG-PRIVATE-PASSWORD',
        'canCreate' => $enabled && $user && empty($user['demo']) && $auth->can('game-buzzer', 'admin'),
        'username' => $user['username'] ?? null, 'csrf' => $auth->csrf(), 'loginUrl' => $auth->portal()];
}
function gb_enable_identity(array $config, array $input): array {
    $auth = jasr_users_auth();
    if (!$auth->validCsrf($input['csrf'] ?? null)) gb_fail('Reload the page and try again.', 403);
    $user = $auth->user();
    if (!gb_setup_owner($user)) gb_fail('Sign in as the jasrasr Super Admin to enable Game Buzzer.', 403);
    $path = gb_project_path();
    // Register only this project. Never provision/promote accounts or replace an existing mapping.
    $auth->directory->updateAsAdministrator($user['id'], $user['version'], function (array $records) use ($user, $path): array {
        if (!gb_setup_owner($records['users'][$user['id']] ?? null)) gb_fail('Owner permissions changed. Sign in again.', 403);
        if (isset($records['projects']['game-buzzer']) && $records['projects']['game-buzzer']['path'] !== $path) {
            gb_fail('Game Buzzer is already registered at a different path. Review it in user-management first.', 409);
        }
        $records['projects']['game-buzzer'] ??= ['name' => 'Game Buzzer', 'path' => $path];
        return $records;
    });
    $auth->directory->withAdministrator($user['id'], $user['version'], function (array $records) use ($config, $user): void {
        if (!gb_setup_owner($records['users'][$user['id']] ?? null)) gb_fail('Owner permissions changed.', 403);
        $dir = gb_dir($config);
        $target = $dir . '/identity-enabled.php';
        if (is_file($target)) return; // Repeated setup cannot reset or overwrite anything.
        $tmp = tempnam($dir, '.identity-');
        if ($tmp === false) gb_fail('Game storage is not writable.', 503);
        try {
            $body = "<?php exit; ?>\n" . json_encode(['enabledAt' => gmdate(DATE_ATOM), 'ownerId' => $user['id']], JSON_THROW_ON_ERROR);
            if (file_put_contents($tmp, $body) !== strlen($body)) gb_fail('Unable to save setup.', 503);
            // Publish a complete file without replacing an existing setup marker.
            if (!@link($tmp, $target) && !is_file($target)) gb_fail('Unable to enable shared login. Check storage permissions.', 503);
        } finally { unlink($tmp); }
    });
    return gb_identity_status($config);
}
function gb_require_identity_create(array $input): void {
    $auth = jasr_users_auth();
    if (!$auth->validCsrf($input['csrf'] ?? null)) gb_fail('Reload the page and try again.', 403);
    $user = $auth->user();
    if (!$user || !empty($user['demo']) || !$auth->can('game-buzzer', 'admin')) gb_fail('Sign in with Game Buzzer admin access.', 403);
}
