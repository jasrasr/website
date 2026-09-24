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
        return $records;
    }
    private function update(callable $change): void
    {
        JsonStore::update($this->path, function (array $records) use ($change): array {
            if ($records === []) $records = ['users' => [], 'projects' => []];
            $records = $change($records);
            $admins = array_filter($records['users'], fn(array $u): bool => $u['active'] && $u['admin']);
            if (!$admins) throw new \InvalidArgumentException('Keep at least one active administrator.');
            return $records;
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
            'hash' => self::password($password), 'admin' => $admin, 'active' => true,
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
                        $user['admin'] = isset($input['admin']);
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
                        if (!isset($r['projects'][$project]) || !in_array($role, ['', 'viewer', 'member', 'admin'], true)) {
                            throw new \InvalidArgumentException('Select a registered project and valid role.');
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
