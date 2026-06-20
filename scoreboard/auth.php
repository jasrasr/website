<?php declare(strict_types=1);
/**
 * Filename: auth.php
 * Revision : 1.12.0
 * Description : Shared authentication library for CVC Scoreboard.
 *               Handles sessions, user management, login/logout, and audit logging.
 *               Users stored in data/users.json with bcrypt-hashed passwords.
 *               First-run users are created from data/users-seed.sample.json only when users.json is absent.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-06-20
 * Changelog :
 * 1.0.0 Initial release; per-user auth, roles, scoreboard access, audit logging
 * 1.1.0 Replaced hardcoded temp passwords with random generation; writes first-run-credentials.txt
 * 1.2.0 Default passwords set to cvc-[username] pattern instead of random hex
 * 1.3.0 Added signed-in user password change helper
 * 1.4.0 Restored random first-run passwords so public source does not define usable defaults
 * 1.5.0 Added requireSignedIn helper for pages open to any authenticated user
 * 1.6.0 Track modified_at on users; requireAuth redirects to scoreboards.php on missing access
 * 1.7.0 Force first-run/reset password changes and remove used first-run credentials
 * 1.8.0 Create two first-run users (admin/scorer) with temporary password password
 * 1.9.0 Extend session retention: 7-day idle (session.gc_maxlifetime), 30-day cookie lifetime; mark HttpOnly + SameSite=Lax + Secure on HTTPS
 * 1.10.0 Added soft-disable on users: makeUser includes disabled:false; attemptLogin rejects disabled accounts (returns null, same as invalid credentials)
 * 1.11.0 saveUsers() now snapshots data/users.json to data/users.previous.json before every write, so a destructive change can be recovered by copying that file back. Single slot, overwritten on each save.
 * 1.12.0 Preserve an existing users.json during deployments; seed a missing file from users-seed.sample.json using an atomic create so concurrent requests cannot overwrite it.
 */

const USERS_FILE     = __DIR__ . '/data/users.json';
const USERS_SAMPLE_FILE = __DIR__ . '/data/users-seed.sample.json';
const FIRST_RUN_CREDENTIALS_FILE = __DIR__ . '/data/first-run-credentials.txt';
const AUTH_SESSION   = 'cvc_user';
const ALL_SCOREBOARDS = ['root', 'youth', 'collide', 'frontlines'];
const DEFAULT_FIRST_RUN_PASSWORD = 'password';

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

const AUTH_SESSION_IDLE_SECONDS   = 7 * 24 * 60 * 60;
const AUTH_SESSION_COOKIE_SECONDS = 30 * 24 * 60 * 60;

function authStart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', (string) AUTH_SESSION_IDLE_SECONDS);
        session_set_cookie_params([
            'lifetime' => AUTH_SESSION_COOKIE_SECONDS,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function authUser(): ?array
{
    authStart();
    return $_SESSION[AUTH_SESSION] ?? null;
}

function authPasswordChangeRequired(array $user): bool
{
    return !empty($user['must_change_password']);
}

function authPasswordChangeUrl(string $loginUrl): string
{
    $base = rtrim(str_replace('\\', '/', dirname($loginUrl)), '/');
    return ($base === '' || $base === '.') ? './change-password.php' : $base . '/change-password.php';
}

function authRedirectIfPasswordChangeRequired(array $user, string $loginUrl): void
{
    if (!authPasswordChangeRequired($user)) {
        return;
    }

    $changePasswordUrl = authPasswordChangeUrl($loginUrl);
    header("Location: {$changePasswordUrl}?force=1&return=scoreboards.php");
    exit;
}

// ---------------------------------------------------------------------------
// Access guards
// ---------------------------------------------------------------------------

function requireAuth(string $scoreboardId, string $loginUrl): array
{
    authStart();
    $user = $_SESSION[AUTH_SESSION] ?? null;

    if ($user === null) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$loginUrl}?redirect={$redirect}");
        exit;
    }

    authRedirectIfPasswordChangeRequired($user, $loginUrl);

    if (!in_array($scoreboardId, $user['scoreboards'] ?? [], true)) {
        $base            = rtrim(str_replace('\\', '/', dirname($loginUrl)), '/');
        $scoreboardsUrl  = ($base === '' || $base === '.') ? 'scoreboards.php' : $base . '/scoreboards.php';
        header("Location: {$scoreboardsUrl}?denied=" . urlencode($scoreboardId));
        exit;
    }

    return $user;
}

function requireAuthJson(string $scoreboardId): array
{
    authStart();
    $user = $_SESSION[AUTH_SESSION] ?? null;

    if ($user === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required.']);
        exit;
    }

    if (authPasswordChangeRequired($user)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Password change required.']);
        exit;
    }

    if (!in_array($scoreboardId, $user['scoreboards'] ?? [], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied to this scoreboard.']);
        exit;
    }

    return $user;
}

function requireSignedIn(string $loginUrl): array
{
    authStart();
    $user = $_SESSION[AUTH_SESSION] ?? null;

    if ($user === null) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$loginUrl}?redirect={$redirect}");
        exit;
    }

    authRedirectIfPasswordChangeRequired($user, $loginUrl);

    return $user;
}

function requireAdmin(string $loginUrl): array
{
    authStart();
    $user = $_SESSION[AUTH_SESSION] ?? null;

    if ($user === null) {
        header("Location: {$loginUrl}");
        exit;
    }

    authRedirectIfPasswordChangeRequired($user, $loginUrl);

    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo authErrorPage('Admin access required.', $loginUrl);
        exit;
    }

    return $user;
}

function authErrorPage(string $message, string $backUrl): string
{
    $msg = htmlspecialchars($message);
    $url = htmlspecialchars($backUrl);
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'/><title>Access Denied</title>"
        . "<link rel='stylesheet' href='./public/styles.css'/></head>"
        . "<body style='display:flex;align-items:center;justify-content:center;height:100vh'>"
        . "<div style='text-align:center'><h2>{$msg}</h2>"
        . "<a href='{$url}' style='color:#94a3b8'>Go back</a></div></body></html>";
}

// ---------------------------------------------------------------------------
// User management
// ---------------------------------------------------------------------------

function loadUsers(): array
{
    ensureUsersFile();
    $raw  = file_get_contents(USERS_FILE);
    $data = json_decode($raw ?: '', true);
    return is_array($data['users'] ?? null) ? $data['users'] : [];
}

function saveUsers(array $users): void
{
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (is_file(USERS_FILE)) {
        @copy(USERS_FILE, $dir . '/users.previous.json');
    }
    file_put_contents(
        USERS_FILE,
        json_encode(['users' => array_values($users)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );
}

function firstRunUserDefinitions(): array
{
    $fallback = [
        ['username' => 'admin', 'role' => 'admin', 'scoreboards' => ALL_SCOREBOARDS],
        ['username' => 'scorer', 'role' => 'scorer', 'scoreboards' => ALL_SCOREBOARDS],
    ];

    if (!is_file(USERS_SAMPLE_FILE)) {
        return $fallback;
    }

    $sample = json_decode(file_get_contents(USERS_SAMPLE_FILE) ?: '', true);
    $definitions = $sample['users'] ?? null;
    if (!is_array($definitions)) {
        return $fallback;
    }

    $valid = [];
    foreach ($definitions as $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $username = trim((string) ($definition['username'] ?? ''));
        $role = (string) ($definition['role'] ?? '');
        $requestedScoreboards = is_array($definition['scoreboards'] ?? null)
            ? $definition['scoreboards']
            : [];
        $scoreboards = array_values(array_intersect(ALL_SCOREBOARDS, $requestedScoreboards));

        if ($username === '' || !in_array($role, ['admin', 'scorer'], true) || $scoreboards === []) {
            continue;
        }

        $valid[] = [
            'username' => $username,
            'role' => $role,
            'scoreboards' => $scoreboards,
        ];
    }

    return $valid !== [] ? $valid : $fallback;
}

function ensureUsersFile(): void
{
    if (is_file(USERS_FILE)) {
        return;
    }

    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $credentials = [];
    $users       = [];
    foreach (firstRunUserDefinitions() as $definition) {
        $username = $definition['username'];
        $role = $definition['role'];
        $scoreboards = $definition['scoreboards'];
        $users[] = makeUser($username, DEFAULT_FIRST_RUN_PASSWORD, $role, $scoreboards, true);
        $credentials[] = "{$username}: " . DEFAULT_FIRST_RUN_PASSWORD;
    }

    $payload = json_encode(
        ['users' => $users],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    $handle = @fopen(USERS_FILE, 'x');
    if ($handle === false) {
        if (is_file(USERS_FILE)) {
            return;
        }
        throw new RuntimeException('Unable to create the scoreboard users file.');
    }

    $created = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the scoreboard users file.');
        }

        $written = fwrite($handle, $payload);
        if ($written === false || $written !== strlen($payload)) {
            throw new RuntimeException('Unable to write the scoreboard users file.');
        }

        fflush($handle);
        $created = true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!$created) {
            @unlink(USERS_FILE);
        }
    }

    file_put_contents(
        FIRST_RUN_CREDENTIALS_FILE,
        "CVC Scoreboard — First-Run Credentials\n"
        . "Generated: " . gmdate('c') . "\n"
        . "Temporary password for first sign-in: " . DEFAULT_FIRST_RUN_PASSWORD . "\n"
        . "Users must change this password before continuing.\n"
        . "Each line is removed automatically after that user changes their password.\n\n"
        . implode("\n", $credentials) . "\n",
        LOCK_EX
    );
}

function makeUser(string $username, string $password, string $role, array $scoreboards, bool $mustChangePassword = false): array
{
    $now = gmdate('c');
    return [
        'id'            => 'user-' . bin2hex(random_bytes(6)),
        'username'      => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role'          => $role,
        'scoreboards'   => $scoreboards,
        'must_change_password' => $mustChangePassword,
        'disabled'      => false,
        'created_at'    => $now,
        'modified_at'   => $now,
    ];
}

function removeFirstRunCredential(string $username): void
{
    if (!is_file(FIRST_RUN_CREDENTIALS_FILE)) {
        return;
    }

    $lines = file(FIRST_RUN_CREDENTIALS_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    $updated = [];
    $removed = false;
    $pattern = '/^' . preg_quote($username, '/') . ':\s+.+$/';

    foreach ($lines as $line) {
        if (preg_match($pattern, $line) === 1) {
            $removed = true;
            continue;
        }
        $updated[] = $line;
    }

    if (!$removed) {
        return;
    }

    file_put_contents(FIRST_RUN_CREDENTIALS_FILE, rtrim(implode(PHP_EOL, $updated)) . PHP_EOL, LOCK_EX);
}

function attemptLogin(string $username, string $password): ?array
{
    foreach (loadUsers() as $user) {
        if (($user['username'] ?? '') === $username &&
            password_verify($password, $user['password_hash'] ?? '')) {
            if (!empty($user['disabled'])) {
                return null;
            }
            $session = $user;
            unset($session['password_hash']);
            return $session;
        }
    }
    return null;
}

function changeCurrentUserPassword(string $userId, string $currentPassword, string $newPassword): array
{
    if ($currentPassword === '' || $newPassword === '') {
        return ['ok' => false, 'message' => 'Current password and new password are required.'];
    }

    $users = loadUsers();

    foreach ($users as &$user) {
        if (($user['id'] ?? '') !== $userId) {
            continue;
        }

        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }

        $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $user['must_change_password'] = false;
        $user['modified_at'] = gmdate('c');
        saveUsers($users);
        removeFirstRunCredential($user['username']);

        authStart();
        $_SESSION[AUTH_SESSION] = [
            'id'          => $user['id'],
            'username'    => $user['username'],
            'role'        => $user['role'],
            'scoreboards' => $user['scoreboards'] ?? [],
            'must_change_password' => false,
        ];

        return ['ok' => true, 'message' => 'Password updated.'];
    }
    unset($user);

    return ['ok' => false, 'message' => 'Signed-in user was not found.'];
}

// ---------------------------------------------------------------------------
// Audit logging
// ---------------------------------------------------------------------------

function logAudit(string $auditFile, array $entry): void
{
    $dir = dirname($auditFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $entries = [];
    if (is_file($auditFile)) {
        $data    = json_decode(file_get_contents($auditFile) ?: '', true);
        $entries = is_array($data) ? $data : [];
    }

    array_unshift($entries, $entry);
    $entries = array_slice($entries, 0, 1000);

    file_put_contents(
        $auditFile,
        json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );
}

function clientIp(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $val = $_SERVER[$key] ?? '';
        if ($val !== '') {
            return trim(explode(',', $val)[0]);
        }
    }
    return 'unknown';
}

function clientUserAgent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}
