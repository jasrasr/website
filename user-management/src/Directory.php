<?php
declare(strict_types=1);
namespace Jasr\Users;

use Jasr\Framework\JsonStore;

/** One locked document makes user/role changes and last-admin protection atomic. */
final class Directory
{
    private string $path;
    public function __construct(string $directory)
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('Identity storage is unavailable.');
        }
        $this->path = $directory . '/directory.json';
    }
    public function read(): array
    {
        $records = JsonStore::read($this->path)['records'];
        if ($records === []) return ['users' => [], 'projects' => []];
        if (!is_array($records['users'] ?? null) || !is_array($records['projects'] ?? null)) {
            throw new \RuntimeException('Invalid identity directory.');
        }
        $records['users'] = array_map([Permissions::class, 'normalize'], $records['users']);
        return $records;
    }
    private function update(callable $change): void
    {
        JsonStore::update($this->path, function (array $records) use ($change): array {
            if ($records === []) $records = ['users' => [], 'projects' => []];
            $records['users'] = array_map([Permissions::class, 'normalize'], $records['users']);
            $records = $change($records);
            $records['users'] = array_map([Permissions::class, 'normalize'], $records['users']);
            $admins = array_filter($records['users'], fn(array $u): bool => $u['active'] && $u['admin']);
            if (!$admins) throw new \InvalidArgumentException('Keep at least one active administrator.');
            return $records;
        });
    }
    /** Keep administrator/target validation stable throughout a linking transaction. */
    public function withAdministrator(string $id, int $version, callable $callback): mixed
    {
        $lock = fopen($this->path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_SH)) throw new \RuntimeException('Identity lock failed.');
        try {
            $state = $this->read();
            $actor = $state['users'][$id] ?? null;
            if (!$actor || !$actor['active'] || !$actor['admin'] || $actor['mustChangePassword'] || $actor['version'] !== $version) {
                throw new \InvalidArgumentException('Administrator access required. Sign in again.');
            }
            return $callback($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    /** Commit link ownership and project access in one atomic directory write. */
    public function updateAsAdministrator(string $id, int $version, callable $change): void
    {
        $this->update(function (array $state) use ($id, $version, $change): array {
            $actor = $state['users'][$id] ?? null;
            if (!$actor || !$actor['active'] || !$actor['admin'] || $actor['mustChangePassword'] || $actor['version'] !== $version) {
                throw new \InvalidArgumentException('Administrator access required. Sign in again.');
            }
            return $change($state);
        });
    }
    public static function password(string $password): string
    {
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Use a password between 12 and 72 bytes.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
    public static function publicUser(array $user): array
    {
        unset($user['hash']);
        return $user;
    }
    private static function newUser(string $username, string $name, string $password, bool $admin, bool $temporary): array
    {
        $username = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D', $username)) {
            throw new \InvalidArgumentException('Username must be 3–64 ASCII letters, numbers, dots, underscores or hyphens.');
        }
        $name = trim($name);
        if ($name === '' || strlen($name) > 120) throw new \InvalidArgumentException('Enter a display name up to 120 bytes.');
        return ['id' => bin2hex(random_bytes(16)), 'username' => $username, 'name' => $name,
            'hash' => self::password($password), 'admin' => $admin, 'role' => $admin ? 'super_admin' : 'user',
            'allProjects' => $admin, 'directoryAdmin' => $admin, 'demo' => false, 'email' => '', 'active' => true,
            'mustChangePassword' => $temporary, 'version' => 1, 'projects' => [], 'createdAt' => gmdate(DATE_ATOM)];
    }
    public function setup(string $username, string $name, string $password): void
    {
        $user = self::newUser($username, $name, $password, true, false);
        $this->update(function (array $r) use ($user): array {
            if ($r['users']) throw new \InvalidArgumentException('Setup is already complete.');
            $r['users'][$user['id']] = $user;
            return $r;
        });
    }
    // Administrative methods recheck the actor under the same lock as the mutation.
    public function administer(string $actorId, int $version, string $action, array $input): void
    {
        $this->update(function (array $r) use ($actorId, $version, $action, $input): array {
            $actor = $r['users'][$actorId] ?? null;
            if (!$actor || !$actor['active'] || !$actor['admin'] || $actor['mustChangePassword'] || $actor['version'] !== $version) {
                throw new \InvalidArgumentException('Administrator access required. Sign in again.');
            }
            if ($action === 'create-user') {
                $user = self::newUser($input['username'] ?? '', $input['name'] ?? '', $input['password'] ?? '', isset($input['admin']), true);
                $user = Permissions::assign($user, $input);
                foreach ($r['users'] as $u) {
                    if ($u['username'] === $user['username']) throw new \InvalidArgumentException('That username is already in use.');
                }
                $r['users'][$user['id']] = $user;
            } elseif ($action === 'save-project') {
                $id = $input['project'] ?? '';
                $path = $input['path'] ?? '';
                $name = trim($input['name'] ?? '');
                if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) || $name === '' || strlen($name) > 120 ||
                    !preg_match('~^/(?:[a-zA-Z0-9_-]+/)+(?:[a-zA-Z0-9_-]+\.php)?$~D', $path)) {
                    throw new \InvalidArgumentException('Use a project slug, display name, and local path such as /mpg/.');
                }
                $r['projects'][$id] = ['name' => $name, 'path' => $path];
            } else {
                $id = $input['id'] ?? '';
                if (!isset($r['users'][$id])) throw new \InvalidArgumentException('User not found.');
                $user = &$r['users'][$id];
                switch ($action) {
                    case 'save-user':
                        $user['active'] = isset($input['active']);
                        $user = Permissions::assign($user, $input);
                        break;
                    case 'reset-password':
                        $password = $input['password'] ?? '';
                        if (password_verify($password, $user['hash'])) throw new \InvalidArgumentException('Choose a different password.');
                        $user['hash'] = self::password($password);
                        $user['mustChangePassword'] = true;
                        break;
                    case 'grant':
                        $project = $input['project'] ?? '';
                        $role = $input['role'] ?? '';
                        if (!isset($r['projects'][$project]) || !in_array($role, ['', 'viewer', 'member', 'admin', 'super_admin'], true)) {
                            throw new \InvalidArgumentException('Select a registered project and valid role.');
                        }
                        if ($role !== '' && Permissions::RANKS[$role] > Permissions::RANKS[Permissions::maximum($user)]) {
                            throw new \InvalidArgumentException('Project access cannot exceed the account role. Change the account role first.');
                        }
                        if ($role === '') unset($user['projects'][$project]);
                        else $user['projects'][$project] = $role;
                        break;
                    default: throw new \InvalidArgumentException('Unknown action.');
                }
                ++$user['version']; // Revoke all sessions after account/security changes.
            }
            return $r;
        });
    }
    public function updateProfile(string $id, int $version, string $name, string $email): void
    {
        $name = trim($name); $email = trim($email);
        if ($name === '' || strlen($name) > 120 || strlen($email) > 254 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new \InvalidArgumentException('Enter a display name and an optional valid contact email.');
        }
        $this->update(function (array $r) use ($id, $version, $name, $email): array {
            $user = &$r['users'][$id];
            if (!$user || !$user['active'] || $user['mustChangePassword'] || $user['version'] !== $version) throw new \InvalidArgumentException('Sign in again to update your profile.');
            $user['name'] = $name; $user['email'] = $email;
            return $r;
        });
    }
    /** Explicit owner-approved provisioning; never runs automatically on deployment. */
    public function provisionRequestedAccounts(string $actorId, int $version): array
    {
        $credentials = [];
        $this->update(function (array $r) use ($actorId, $version, &$credentials): array {
            $actor = $r['users'][$actorId] ?? null;
            if (!$actor || !$actor['active'] || !$actor['admin'] || $actor['mustChangePassword'] || $actor['version'] !== $version) {
                throw new \InvalidArgumentException('Central directory administrator access required.');
            }
            $specs = [
                'jasrasr' => ['Jason Lamb', 'super_admin', false],
                'demo-user' => ['Demo User', 'user', true],
                'demo-admin' => ['Demo Admin', 'admin', true],
                'demo-super-admin' => ['Demo Super Admin', 'super_admin', true],
            ];
            foreach ($specs as $username => [$name, $role, $demo]) {
                $existing = null;
                foreach ($r['users'] as $id => $u) if ($u['username'] === $username) $existing = $id;
                if ($existing !== null && $demo) {
                    if (!$r['users'][$existing]['demo']) throw new \InvalidArgumentException('A requested demo username belongs to an existing non-demo account. Resolve the collision first.');
                    continue; // Never reset existing demo passwords, grants or activation.
                }
                if ($existing === null) {
                    $password = bin2hex(random_bytes(16));
                    $user = self::newUser($username, $name, $password, false, true);
                    $existing = $user['id'];
                    $r['users'][$existing] = $user;
                    $credentials[] = ['username' => $username, 'password' => $password];
                }
                $user = &$r['users'][$existing];
                $before = $user;
                $user['role'] = $role; $user['demo'] = $demo;
                $user['allProjects'] = !$demo; $user['directoryAdmin'] = !$demo;
                $user['active'] = true;
                if (!$demo) $user['name'] = 'Jason Lamb';
                $user = Permissions::normalize($user);
                if ($user !== $before) ++$user['version'];
                unset($user);
            }
            return $r;
        });
        return $credentials;
    }
    public function changePassword(string $id, int $version, string $current, string $replacement): void
    {
        $hash = self::password($replacement);
        $this->update(function (array $r) use ($id, $version, $current, $replacement, $hash): array {
            $user = &$r['users'][$id];
            if (!$user || !$user['active'] || $user['version'] !== $version || !password_verify($current, $user['hash'])) {
                throw new \InvalidArgumentException('Current password is incorrect or the session has expired.');
            }
            if (password_verify($replacement, $user['hash'])) throw new \InvalidArgumentException('Choose a different password.');
            $user['hash'] = $hash;
            $user['mustChangePassword'] = false;
            ++$user['version'];
            return $r;
        });
    }
}
