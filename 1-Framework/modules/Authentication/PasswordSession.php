<?php
declare(strict_types=1);
namespace Jasr\Framework;

/** Extracted from box/lib/auth.php; removes project paths and global user records.
 * Adds strict cookies, CSRF, session rotation, inactivity expiry, and caller-owned throttling.
 */
final class PasswordSession
{
    public function __construct(string $name, string $path, bool $secure, private readonly int $timeout = 1800)
    {
        if (session_status() === PHP_SESSION_ACTIVE) throw new \RuntimeException('A session is already active.');
        ini_set('session.use_strict_mode', '1');
        session_name($name);
        session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Strict', 'path' => $path]);
        session_start();
        if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $timeout) $_SESSION = [];
        $_SESSION['last'] = time();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public function csrf(): string { return $_SESSION['csrf']; }
    public function validCsrf(mixed $token): bool { return is_string($token) && hash_equals($this->csrf(), $token); }
    public function loggedIn(): bool { return isset($_SESSION['user']); }
    public function login(string $user, string $password, string $expectedUser, string $hash, callable $allow): bool
    {
        if (!$allow()) return false;
        $valid = password_verify($password, $hash);
        if (!$valid || !hash_equals($expectedUser, $user)) return false;
        session_regenerate_id(true);
        $_SESSION['user'] = $expectedUser;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    }
    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
