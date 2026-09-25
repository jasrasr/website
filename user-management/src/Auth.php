<?php
declare(strict_types=1);
namespace Jasr\Users;

use Jasr\Framework\JsonStore;
use Jasr\Framework\PasswordSession;
use Jasr\Framework\Response;

final class Auth
{
    public readonly Directory $directory;
    private PasswordSession $session;
    public function __construct(private array $config)
    {
        if (PHP_SAPI !== 'cli' && $config['secure_cookie'] && (($_SERVER['HTTPS'] ?? '') === '' || $_SERVER['HTTPS'] === 'off')) {
            throw new \RuntimeException('HTTPS is required.');
        }
        $this->directory = new Directory($config['data_path']);
        $this->session = new PasswordSession($config['cookie_name'], $config['cookie_path'], $config['secure_cookie'], $config['timeout']);
        header('Cache-Control: no-store');
    }
    public function csrf(): string { return $this->session->csrf(); }
    public function validCsrf(mixed $token): bool { return $this->session->validCsrf($token); }
    public function portal(): string { return $this->config['base_path']; }
    public function user(): ?array
    {
        $id = $_SESSION['identity_id'] ?? '';
        $user = $this->directory->read()['users'][$id] ?? null;
        if (!$user || !$user['active'] || $user['version'] !== ($_SESSION['identity_version'] ?? null)) return null;
        return Directory::publicUser($user);
    }
    public function login(string $username, string $password): bool
    {
        $username = strtolower(trim($username));
        // Both account and direct peer IP limits persist across new browser sessions.
        $keys = ['user:' . hash('sha256', $username), 'ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'cli')];
        $allowed = true;
        JsonStore::update($this->config['data_path'] . '/attempts.json', function (array $r) use ($keys, &$allowed): array {
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
        $this->session->logout(); // Rotate ID/token before establishing the shared identity.
        $_SESSION['identity_id'] = $found['id'];
        $_SESSION['identity_version'] = $found['version'];
        // Do not clear the peer-IP counter: it also limits password spraying.
        JsonStore::update($this->config['data_path'] . '/attempts.json', function (array $r) use ($keys): array {
            unset($r[$keys[0]]);
            return $r;
        });
        return true;
    }
    public function logout(): void { $this->session->logout(); }
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
            header('Location: ' . $this->portal(), true, 303);
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
