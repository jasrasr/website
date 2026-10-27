<?php
/* File Name: PasswordSession.php
Author: Jason Lamb (with help from ChatGPT)
Description: Shared PHP sessions with CSRF, revocation and optional bounded persistent login.
Revision: 1.1.1
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.1.1 - Preserve fixed persistent-cookie expiry on every request and rotate expired IDs. 1.1.0 - Add explicitly opted-in 7-day cookies while retaining 30-minute default inactivity expiry.
*/
declare(strict_types=1);
namespace Jasr\Framework;

final class PasswordSession
{
    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly bool $secure,
        private readonly int $timeout = 1800,
        private readonly int $rememberSeconds = 604800
    ) {
        if (session_status() === PHP_SESSION_ACTIVE) throw new \RuntimeException('A session is already active.');
        if ($timeout < 60 || $rememberSeconds < $timeout || $rememberSeconds > 30 * 86400) {
            throw new \RuntimeException('Invalid identity session timeout.');
        }
        ini_set('session.use_strict_mode', '1');
        // PHP's default 24-minute garbage collection would otherwise invalidate
        // a remembered browser session before its advertised expiry.
        $gc = max((int)ini_get('session.gc_maxlifetime'),$timeout,$rememberSeconds);
        ini_set('session.gc_maxlifetime',(string)$gc);
        session_name($name);
        session_set_cookie_params([
            'httponly' => true,'secure' => $secure,'samesite' => 'Strict',
            'path' => $path,'lifetime' => 0
        ]);
        session_start();
        $now = time();
        $until = isset($_SESSION['remember_until']) && is_int($_SESSION['remember_until'])
            ? $_SESSION['remember_until'] : 0;
        $idle = $until > 0 ? $rememberSeconds : $timeout;
        if (($until > 0 && $until <= $now)
            || (isset($_SESSION['last']) && $now - (int)$_SESSION['last'] > $idle)) {
            $_SESSION = [];
            session_regenerate_id(true); // Never reuse an expired identity's session ID.
            // Replace any persistent cookie with a new session-only anonymous ID.
            $this->writeCookie(false);
        } elseif ($until > $now) {
            // session_start() can resend a session-only cookie on some hosts.
            // Repeat the SAME fixed expiry, never sliding it forward.
            $this->writeCookie(true);
        }
        $_SESSION['last'] = $now;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    private function writeCookie(bool $remember): void
    {
        setcookie($this->name, session_id(), [
            'expires'=>$remember ? (int)($_SESSION['remember_until'] ?? 0) : 0,
            'path'=>$this->path,'secure'=>$this->secure,
            'httponly'=>true,'samesite'=>'Strict'
        ]);
    }

    /** Only called by the central identity provider after successful password verification. */
    public function enableRemember(): void
    {
        $_SESSION['remember_until'] = time() + $this->rememberSeconds;
        $this->writeCookie(true);
    }

    public function csrf(): string { return $_SESSION['csrf']; }
    public function validCsrf(mixed $token): bool {
        return is_string($token) && hash_equals($this->csrf(), $token);
    }
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
        // Removing the persistent expiry on the new session cookie signs out
        // this browser across all connected projects without stale remember flags.
        $this->writeCookie(false);
    }
}
