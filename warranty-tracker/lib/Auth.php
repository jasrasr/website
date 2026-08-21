<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(private readonly string $usersPath, private readonly string $invitesPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('warranty_tracker_session');
            session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
            session_start();
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        $id = $_SESSION['userId'] ?? null;
        if (!is_string($id)) return null;
        foreach ($this->read($this->usersPath, 'users') as $user) {
            if (($user['id'] ?? '') === $id) return $this->publicUser($user);
        }
        return null;
    }

    public function csrf(): string { return (string) $_SESSION['csrf']; }
    public function setupRequired(): bool { return count($this->read($this->usersPath, 'users')) === 0; }

    /** @return array<string,mixed> */
    public function register(string $name, string $email, string $password, string $inviteCode): array
    {
        $name = trim($name); $email = strtolower(trim($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) throw new InvalidArgumentException('Name, a valid email, and a password of at least 12 characters are required.');
        $users = $this->read($this->usersPath, 'users');
        foreach ($users as $user) if (($user['email'] ?? '') === $email) throw new InvalidArgumentException('That email address is already registered.');
        $isFirst = count($users) === 0;
        if (!$isFirst) $this->consumeInvite($inviteCode, $email);
        $user = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'email' => $email, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $isFirst ? 'admin' : 'member', 'createdAt' => gmdate(DATE_ATOM)];
        $users[] = $user; $this->write($this->usersPath, 'users', $users); $this->establishSession($user['id']);
        return $this->publicUser($user);
    }

    /** @return array<string,mixed> */
    public function login(string $email, string $password): array
    {
        $this->checkLoginThrottle(); $email = strtolower(trim($email));
        foreach ($this->read($this->usersPath, 'users') as $user) {
            if (($user['email'] ?? '') === $email && password_verify($password, (string) ($user['passwordHash'] ?? ''))) {
                unset($_SESSION['loginFailures']); $this->establishSession((string) $user['id']); return $this->publicUser($user);
            }
        }
        $_SESSION['loginFailures'][] = time(); throw new InvalidArgumentException('Email or password is incorrect.');
    }

    public function logout(): void { $_SESSION = []; session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(24)); }

    /** @return array{code:string,expiresAt:string} */
    public function createInvite(): array
    {
        $user = $this->user(); if (($user['role'] ?? '') !== 'admin') throw new InvalidArgumentException('Administrator access is required.');
        $invite = ['code' => strtoupper(bin2hex(random_bytes(4))), 'createdBy' => $user['id'], 'createdAt' => gmdate(DATE_ATOM), 'expiresAt' => gmdate(DATE_ATOM, time() + 604800), 'usedAt' => null, 'usedBy' => null];
        $invites = $this->read($this->invitesPath, 'invites'); $invites[] = $invite; $this->write($this->invitesPath, 'invites', $invites); return ['code' => $invite['code'], 'expiresAt' => $invite['expiresAt']];
    }

    public function verifyCsrf(?string $token): void { if (!is_string($token) || !hash_equals($this->csrf(), $token)) throw new InvalidArgumentException('Security token is invalid. Refresh and try again.'); }
    private function establishSession(string $id): void { session_regenerate_id(true); $_SESSION['userId'] = $id; $_SESSION['csrf'] = bin2hex(random_bytes(24)); }
    private function checkLoginThrottle(): void { $recent = array_filter($_SESSION['loginFailures'] ?? [], static fn(int $time): bool => $time > time() - 900); $_SESSION['loginFailures'] = $recent; if (count($recent) >= 5) throw new InvalidArgumentException('Too many login attempts. Try again in 15 minutes.'); }
    private function consumeInvite(string $code, string $email): void
    {
        $invites = $this->read($this->invitesPath, 'invites'); $code = strtoupper(trim($code));
        foreach ($invites as &$invite) {
            if (($invite['code'] ?? '') === $code && empty($invite['usedAt']) && strtotime((string) $invite['expiresAt']) >= time()) { $invite['usedAt'] = gmdate(DATE_ATOM); $invite['usedBy'] = $email; $this->write($this->invitesPath, 'invites', $invites); return; }
        }
        throw new InvalidArgumentException('Invite code is invalid, expired, or already used.');
    }
    /** @param array<string,mixed> $user @return array<string,mixed> */
    private function publicUser(array $user): array { return array_intersect_key($user, array_flip(['id', 'name', 'email', 'role', 'createdAt'])); }
    /** @return array<int,array<string,mixed>> */
    private function read(string $path, string $key): array { if (!is_file($path)) return []; $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); return is_array($data[$key] ?? null) ? $data[$key] : []; }
    /** @param array<int,array<string,mixed>> $records */
    private function write(string $path, string $key, array $records): void
    {
        $directory = dirname($path); if (!is_dir($directory)) mkdir($directory, 0775, true); $lock = fopen($path . '.lock', 'c+'); if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('Account storage could not be locked.');
        try { $temp = tempnam($directory, 'auth-'); if ($temp === false || file_put_contents($temp, json_encode(['schemaVersion' => 1, 'updatedAt' => gmdate(DATE_ATOM), $key => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL) === false || !rename($temp, $path)) throw new RuntimeException('Account storage could not be written.'); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
