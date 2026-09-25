<?php
declare(strict_types=1);
namespace Jasr\Users;

final class Permissions
{
    public const RANKS = ['viewer' => 1, 'member' => 2, 'admin' => 3, 'super_admin' => 4];
    public const ACCOUNT_ROLES = ['user', 'admin', 'super_admin'];
    public static function normalize(array $user): array
    {
        // Preserve existing deployments' explicit site-admin rights; new accounts
        // always write all fields and never receive scope from their role alone.
        if (!isset($user['role'])) {
            $legacyAdmin = !empty($user['admin']);
            $user['role'] = $legacyAdmin ? 'super_admin' : (in_array('admin', $user['projects'] ?? [], true) ? 'admin' : 'user');
            $user['allProjects'] = $legacyAdmin;
            $user['directoryAdmin'] = $legacyAdmin;
        }
        $user += ['allProjects' => false, 'directoryAdmin' => false, 'demo' => false, 'email' => ''];
        if (!in_array($user['role'], self::ACCOUNT_ROLES, true)) throw new \RuntimeException('Invalid account role.');
        // Compatibility field for legacy portal/linking callers: directory control,
        // not the user's project role. Do not use this flag for project authorization.
        $user['admin'] = $user['directoryAdmin'] && $user['role'] === 'super_admin' && $user['allProjects'] && !$user['demo'];
        return $user;
    }
    public static function maximum(array $user): string { return $user['role'] === 'user' ? 'member' : $user['role']; }
    public static function projectRole(array $user, string $project): ?string
    {
        $user = self::normalize($user);
        $role = $user['allProjects'] ? self::maximum($user) : ($user['projects'][$project] ?? null);
        if (!isset(self::RANKS[$role ?? ''])) return null;
        return self::RANKS[$role] > self::RANKS[self::maximum($user)] ? self::maximum($user) : $role;
    }
    public static function assign(array $user, array $input): array
    {
        $role = $input['account_role'] ?? (isset($input['admin']) ? 'super_admin' : 'user');
        if (!in_array($role, self::ACCOUNT_ROLES, true)) throw new \InvalidArgumentException('Select a valid account role.');
        // Old form/API input retains its old meaning for compatibility.
        $legacyInput = !isset($input['account_role']) && isset($input['admin']);
        $user['role'] = $role;
        $user['allProjects'] = isset($input['all_projects']) || $legacyInput;
        $user['directoryAdmin'] = isset($input['directory_admin']) || $legacyInput;
        if ($user['directoryAdmin'] && ($role !== 'super_admin' || !$user['allProjects'])) {
            throw new \InvalidArgumentException('Central directory management requires Super Admin and all-project access.');
        }
        if (!empty($user['demo']) && ($user['allProjects'] || $user['directoryAdmin'])) {
            throw new \InvalidArgumentException('Demo accounts must remain scoped to selected projects and cannot manage the central directory.');
        }
        return self::normalize($user);
    }
}
