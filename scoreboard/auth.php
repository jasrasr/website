<?php declare(strict_types=1);
/**
 * Filename: auth.php
 * Revision : 1.7.0
 * Description : Shared authentication library for CVC Scoreboard.
 *               Handles sessions, user management, login/logout, and audit logging.
 *               Users stored in data/users.json with bcrypt-hashed passwords.
 *               Default users auto-created on first load.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 Initial release; per-user auth, roles, scoreboard access, audit logging
 * 1.1.0 Replaced hardcoded temp passwords with random generation; writes first-run-credentials.txt
 * 1.2.0 Default passwords set to cvc-[username] pattern instead of random hex
 * 1.3.0 Added signed-in user password change helper
 * 1.4.0 Restored random first-run passwords so public source does not define usable defaults
 * 1.5.0 Added requireSignedIn helper for pages open to any authenticated user
 * 1.6.0 Track modified_at on users; requireAuth redirects to scoreboards.php on missing access
 * 1.7.0 Force first-run/reset password changes and remove used first-run credentials
 */

const USERS_FILE     = __DIR__ . '/data/users.json';
const FIRST_RUN_CREDENTIALS_FILE = __DIR__ . '/data/first-run-credentials.txt';
const AUTH_SESSION   = 'cvc_user';
const ALL_SCOREBOARDS = ['root', 'youth', 'collide', 'frontlines'];

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

function authStart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['path' => '/']);
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

/**
 * For page requests: redirects to login if not authenticated or not allowed.
 */
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

/**
 * For API requests: returns JSON error instead of redirecting.
 */
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

/**
 * For pages open to any signed-in user (no scoreboard or role gate).
 */
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

/**
 * For admin-only pages: requires role === 'admin'.
 */
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
    file_put_contents(
        USERS_FILE,
        json_encode(['users' => array_values($users)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );
}

function ensureUsersFile(): void
{
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!is_file(USERS_FILE)) {
        $names = [
            ['jason',  'admin',  ALL_SCOREBOARDS],
            ['tate',   'scorer', ALL_SCOREBOARDS],
            ['dahlia', 'scorer', ALL_SCOREBOARDS],
            ['joe',    'scorer', ALL_SCOREBOARDS],
            ['james',  'scorer', ALL_SCOREBOARDS],
        ];

        $credentials = [];
        $users       = [];
        foreach ($names as [$username, $role, $scoreboards]) {
            $password      = bin2hex(random_bytes(8));
            $users[]       = makeUser($username, $password, $role, $scoreboards, true);
            $credentials[] = "{$username}: {$password}";
        }

        file_put_contents(
            USERS_FILE,
            json_encode(['users' => $users], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );

        // Write one-time credentials file — read this, then delete it
        $credFile = dirname(USERS_FILE) . '/first-run-credentials.txt';
        file_put_contents(
            $credFile,
            "CVC Scoreboard — First-Run Credentials\n"
            . "Generated: " . gmdate('c') . "\n"
            . "Users must change these passwords before continuing.\n"
            . "Each line is removed automatically after that user changes their password.\n\n"
            . implode("\n", $credentials) . "\n",
            LOCK_EX
        );
    }
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
