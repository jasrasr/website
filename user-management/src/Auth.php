<?php
declare(strict_types=1);
namespace Jasr\Users;

use Jasr\Framework\JsonStore;
use Jasr\Framework\PasswordSession;
use Jasr\Framework\Response;

/* File Name: Auth.php
Revision: 1.3.0
Modified Date: 2026-11-08
Changelog: 1.3.0 - Return successful sign-ins to the originating registered project without permitting external redirects. 1.2.0 - Restore remembered identities from revocable server-side tokens when shared hosting purges PHP sessions. 1.1.0 - Offer opt-in seven-day shared login without changing default expiry.
*/
final class Auth
{
    public readonly Directory $directory;
    private PasswordSession $session;
    private string $rememberCookie;
    public function __construct(private array $config)
    {
        if (PHP_SAPI !== 'cli' && $config['secure_cookie'] && (($_SERVER['HTTPS'] ?? '') === '' || $_SERVER['HTTPS'] === 'off')) {
            throw new \RuntimeException('HTTPS is required.');
        }
        $this->directory = new Directory($config['data_path']);
        $this->rememberCookie = $config['cookie_name'] . '_REMEMBER';
        $this->session = new PasswordSession($config['cookie_name'], $config['cookie_path'], $config['secure_cookie'], $config['timeout'], $config['remember_seconds']);
        $this->restoreRememberedIdentity();
        header('Cache-Control: no-store');
    }
    public function csrf(): string { return $this->session->csrf(); }
    public function validCsrf(mixed $token): bool { return $this->session->validCsrf($token); }
    public function portal(): string { return $this->config['base_path']; }
    public function safeReturnPath(mixed $candidate): string
    {
        if (!is_string($candidate) || $candidate === '' || strlen($candidate) > 2048
            || $candidate[0] !== '/' || str_starts_with($candidate, '//')
            || preg_match('/[\\\\\x00-\x1f\x7f]/', $candidate) === 1) {
            return '';
        }
        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        $decodedPath = rawurldecode($path);
        if ($path === '' || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decodedPath) === 1) {
            return '';
        }
        foreach ($this->directory->read()['projects'] as $project) {
            $base = (string) ($project['path'] ?? '');
            $basePath = parse_url($base, PHP_URL_PATH);
            if (!is_string($basePath) || $basePath === '' || $basePath[0] !== '/') continue;
            $directory = rtrim($basePath, '/') . '/';
            if ($path === rtrim($directory, '/') || str_starts_with($path, $directory)) return $candidate;
        }
        return '';
    }
    public function loginUrl(mixed $returnTo = ''): string
    {
        $returnTo = $this->safeReturnPath($returnTo);
        return $returnTo === '' ? $this->portal() : $this->portal() . '?' . http_build_query(['return' => $returnTo], '', '&', PHP_QUERY_RFC3986);
    }
    public function user(): ?array
    {
        $id = $_SESSION['identity_id'] ?? '';
        $user = $this->directory->read()['users'][$id] ?? null;
        if (!$user || !$user['active'] || $user['version'] !== ($_SESSION['identity_version'] ?? null)) {
            if ($id !== '') $this->revokeRememberedIdentity();
            return null;
        }
        return Directory::publicUser($user);
    }
    public function login(string $username, string $password, bool $remember = false): bool
    {
        $username = strtolower(trim($username));
        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $rateLimitExempt = in_array($remoteIp, $this->config['rate_limit_exempt_ips'] ?? [], true);
        // Both account and direct peer IP limits persist across new browser sessions.
        $keys = ['user:' . hash('sha256', $username), 'ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'cli')];
        $allowed = true;
        if (!$rateLimitExempt) JsonStore::update($this->config['data_path'] . '/attempts.json', function (array $r) use ($keys, &$allowed): array {
            $now = time();
            $r = array_filter($r, fn(array $v): bool => $v['until'] > $now);
            foreach ($keys as $key) {
                $r[$key] ??= ['count' => 0, 'until' => $now + 900];
                $limit = str_starts_with($key, 'ip:') ? 60 : 10;
                if ($r[$key]['count'] >= $limit) $allowed = false;
            }
            if ($allowed) foreach ($keys as $key) ++$r[$key]['count'];
            return $r;
        });
        if (!$allowed) return false;
        $found = null;
        foreach ($this->directory->read()['users'] as $user) if ($user['username'] === $username) $found = $user;
        // A valid dummy bcrypt hash avoids skipping password verification for unknown users.
        $hash = $found['hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (strlen($password) > 72 || str_contains($password, "\0")) return false;
        $valid = password_verify($password, $hash);
        if (!$valid || !$found || !$found['active']) return false;
        $this->revokeRememberedIdentity();
        $this->session->logout(); // Rotate ID/token before establishing the shared identity.
        $_SESSION['identity_id'] = $found['id'];
        $_SESSION['identity_version'] = $found['version'];
        if ($remember) {
            $this->session->enableRemember();
            $this->issueRememberedIdentity($found, (int) $_SESSION['remember_until']);
        }
        // Do not clear the peer-IP counter: it also limits password spraying.
        JsonStore::update($this->config['data_path'] . '/attempts.json', function (array $r) use ($keys): array {
            unset($r[$keys[0]]);
            return $r;
        });
        return true;
    }
    public function logout(): void
    {
        $this->revokeRememberedIdentity();
        $this->session->logout();
    }

    private function rememberStore(): string
    {
        return $this->config['data_path'] . '/remember_tokens.json';
    }

    private function rememberCookie(string $value, int $expires): void
    {
        setcookie($this->rememberCookie, $value, [
            'expires' => $expires,
            'path' => $this->config['cookie_path'],
            'secure' => $this->config['secure_cookie'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function cookieToken(): ?array
    {
        $value = $_COOKIE[$this->rememberCookie] ?? '';
        if (!is_string($value) || preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D', $value, $matches) !== 1) return null;
        return ['selector' => $matches[1], 'validator' => $matches[2]];
    }

    private function issueRememberedIdentity(array $user, int $expires, string $replaces = ''): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $now = time();
        JsonStore::update($this->rememberStore(), function (array $records) use ($selector, $validator, $user, $expires, $replaces, $now): array {
            $records = array_filter($records, static fn(array $record): bool => (int) ($record['expires'] ?? 0) > $now);
            if ($replaces !== '') unset($records[$replaces]);
            $records[$selector] = [
                'tokenHash' => hash('sha256', $validator),
                'userId' => (string) $user['id'],
                'identityVersion' => (int) $user['version'],
                'expires' => $expires,
                'createdAt' => gmdate(DATE_ATOM),
            ];
            return $records;
        });
        $this->rememberCookie($selector . '.' . $validator, $expires);
        $_COOKIE[$this->rememberCookie] = $selector . '.' . $validator;
    }

    private function restoreRememberedIdentity(): void
    {
        if (!empty($_SESSION['identity_id'])) return;
        $token = $this->cookieToken();
        if ($token === null) return;
        $records = JsonStore::read($this->rememberStore())['records'];
        $record = $records[$token['selector']] ?? null;
        $users = $this->directory->read()['users'];
        $user = is_array($record) ? ($users[(string) ($record['userId'] ?? '')] ?? null) : null;
        $valid = is_array($record)
            && (int) ($record['expires'] ?? 0) > time()
            && is_string($record['tokenHash'] ?? null)
            && hash_equals($record['tokenHash'], hash('sha256', $token['validator']))
            && is_array($user)
            && !empty($user['active'])
            && (int) $user['version'] === (int) ($record['identityVersion'] ?? -1);
        if (!$valid) {
            $this->revokeRememberedIdentity();
            return;
        }
        $expires = (int) $record['expires'];
        $this->session->restoreRemembered($expires);
        $_SESSION['identity_id'] = $user['id'];
        $_SESSION['identity_version'] = $user['version'];
        // Rotate the one-time validator after recovery while retaining the original fixed expiry.
        $this->issueRememberedIdentity($user, $expires, $token['selector']);
    }

    private function revokeRememberedIdentity(): void
    {
        $hadCookie = array_key_exists($this->rememberCookie, $_COOKIE);
        $token = $this->cookieToken();
        if ($token !== null) {
            JsonStore::update($this->rememberStore(), function (array $records) use ($token): array {
                unset($records[$token['selector']]);
                return $records;
            });
        }
        if ($hadCookie) {
            $this->rememberCookie('', time() - 3600);
            unset($_COOKIE[$this->rememberCookie]);
        }
    }
    public function can(string $project, string $minimum = 'viewer'): bool
    {
        $roles = Permissions::RANKS;
        if (!isset($roles[$minimum]) || !isset($this->directory->read()['projects'][$project])) return false;
        $user = $this->user();
        if (!$user || $user['mustChangePassword']) return false;
        return ($roles[Permissions::projectRole($user, $project) ?? ''] ?? 0) >= $roles[$minimum];
    }
    /** Server-side gate for HTML or JSON routes. Call before output/session_start(). */
    public function requireProject(string $project, string $minimum = 'viewer', bool $json = false): array
    {
        $user = $this->user();
        if (!$user || $user['mustChangePassword']) {
            if ($json) Response::json(401, 'Sign in and complete any required password change.');
            header('Location: ' . $this->loginUrl($_SERVER['REQUEST_URI'] ?? ''), true, 303);
            exit;
        }
        if (!$this->can($project, $minimum)) {
            if ($json) Response::json(403, 'Project access denied.');
            http_response_code(403);
            exit('Project access denied.');
        }
        return $user;
    }
}

