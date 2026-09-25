<?php
declare(strict_types=1);
namespace Jasr\Users;

use Jasr\Framework\JsonStore;

/** Links identities without changing legacy identifiers, credentials, or data files. */
final class AccountLinks
{
    public function __construct(private Directory $directory, private AccountLinkAdapter $adapter) {}
    private function path(): string { return $this->adapter->storage() . '/links.json'; }
    public function records(?array $state = null): array
    {
        return $this->validateRecords(($state ?? $this->directory->read())['accountLinks'][$this->adapter->project()] ?? JsonStore::read($this->path())['records']);
    }
    private function validateRecords(array $r): array
    {
        if ($r === []) return ['links' => [], 'history' => []];
        if (!is_array($r['links'] ?? null) || !is_array($r['history'] ?? null)) throw new \RuntimeException('Invalid account links.');
        $seen = [];
        foreach ($r['links'] as $id => $link) {
            if (!preg_match('/^[a-f0-9]{32}$/D', (string)$id) || !is_array($link) || !is_string($link['legacyId'] ?? null) ||
                $link['legacyId'] === '' || isset($seen[$link['legacyId']])) throw new \RuntimeException('Invalid or duplicate account links.');
            $seen[$link['legacyId']] = true;
        }
        return $r;
    }
    public function legacyId(string $centralId): ?string
    {
        $link = $this->records()['links'][$centralId] ?? null;
        if ($link === null) return null;
        if (!is_string($link['legacyId'] ?? null)) throw new \RuntimeException('Invalid account link.');
        return $link['legacyId'];
    }
    private function target(array $state, string $targetId): array
    {
        $user = $state['users'][$targetId] ?? null;
        $project = $this->adapter->project();
        if (!isset($state['projects'][$project]) || !$user || !$user['active']) {
            throw new \InvalidArgumentException('The target must be active and the project must be registered.');
        }
        return $user;
    }
    private function plan(array $state, array $records, string $legacyId, string $targetId, array $snapshot, string $reviewedRole): array
    {
        $target = $this->target($state, $targetId);
        if (isset($records['links'][$targetId])) throw new \InvalidArgumentException('This central account is already linked. Reassignment is not allowed.');
        foreach ($records['links'] as $link) {
            if (($link['legacyId'] ?? null) === $legacyId) throw new \InvalidArgumentException('This legacy account is already linked.');
        }
        $sourceRole = $this->adapter->sourceRole($snapshot);
        $chosen = $sourceRole ?? $reviewedRole;
        if (!in_array($chosen, $this->adapter->supportedRoles(), true) || !isset(Permissions::RANKS[$chosen])) {
            throw new \InvalidArgumentException('Review the existing project permissions and explicitly choose a supported role.');
        }
        $existing = Permissions::projectRole($target, $this->adapter->project());
        $effective = $chosen;
        foreach ([Permissions::maximum($target), $existing, $target['projects'][$this->adapter->project()] ?? null] as $cap) {
            if ($cap !== null && isset(Permissions::RANKS[$cap]) && Permissions::RANKS[$cap] < Permissions::RANKS[$effective]) $effective = $cap;
        }
        return ['sourceRole' => $sourceRole, 'reviewedRole' => $reviewedRole, 'existingRole' => $existing,
            'effectiveRole' => $effective, 'project' => $this->adapter->project(), 'legacyId' => $legacyId, 'targetId' => $targetId,
            'targetUsername' => $target['username'], 'targetName' => $target['name'],
            'fingerprint' => hash('sha256', json_encode([$records, $target, $state['projects'][$this->adapter->project()], $snapshot, $sourceRole, $reviewedRole, $effective], JSON_THROW_ON_ERROR))];
    }
    public function preview(string $actorId, int $version, string $legacyId, string $targetId, string $reviewedRole = ''): array
    {
        return $this->directory->withAdministrator($actorId, $version, function (array $state) use ($legacyId, $targetId, $reviewedRole): array {
            return $this->adapter->withSnapshot($legacyId, fn(array $snapshot): array =>
                $this->plan($state, $this->records($state), $legacyId, $targetId, $snapshot, $reviewedRole));
        });
    }
    public function apply(string $actorId, int $version, array $preview): void
    {
        $this->directory->updateAsAdministrator($actorId, $version, function (array $state) use ($actorId, $preview): array {
            return $this->adapter->withSnapshot($preview['legacyId'], function (array $snapshot) use ($state, $actorId, $preview): array {
                $r = $this->records($state);
                $fresh = $this->plan($state, $r, $preview['legacyId'], $preview['targetId'], $snapshot, $preview['reviewedRole']);
                if (!hash_equals($fresh['fingerprint'], $preview['fingerprint'])) {
                    throw new \InvalidArgumentException('Accounts, permissions, data, or links changed. Run preview again.');
                }
                $storage = $this->adapter->storage();
                if (!is_dir($storage) && !mkdir($storage, 0700, true)) throw new \RuntimeException('Cannot create private link storage.');
                $backupId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8));
                JsonStore::update($storage . '/backup-' . $backupId . '.json', fn(array $unused): array => [
                    'project' => $fresh['project'], 'actorId' => $actorId, 'targetId' => $fresh['targetId'],
                    'legacyId' => $fresh['legacyId'], 'snapshot' => $snapshot, 'previousLinks' => $r,
                    'previousDirectory' => $state,
                    'previousProjectRole' => $fresh['existingRole'], 'effectiveRole' => $fresh['effectiveRole'],
                ]);
                $link = ['legacyId' => $fresh['legacyId'], 'linkedAt' => gmdate(DATE_ATOM),
                    'linkedBy' => $actorId, 'backupId' => $backupId, 'role' => $fresh['effectiveRole'],
                    'sourceRole' => $fresh['sourceRole'], 'reviewedRole' => $fresh['reviewedRole']];
                $r['links'][$fresh['targetId']] = $link;
                $r['history'][] = ['action' => 'link', 'targetId' => $fresh['targetId']] + $link;
                $state['accountLinks'][$fresh['project']] = $r;
                // Preserve existing grants and account type. Only add missing project membership.
                if ($fresh['existingRole'] === null) {
                    $state['users'][$fresh['targetId']]['projects'][$fresh['project']] = $fresh['effectiveRole'];
                }
                // New ownership changes data access even when a project grant already exists.
                $state['users'][$fresh['targetId']]['version']++;
                return $state;
            });
        });
    }
    /** A link is a permission ceiling even for global super administrators.
     * Pre-permission links fail closed until reviewed separately.
     */
    public function can(string $centralId, string $minimum): bool
    {
        $state = $this->directory->read();
        $link = $this->records($state)['links'][$centralId] ?? null;
        $user = $state['users'][$centralId] ?? null;
        if (!$link || !$user || !$user['active'] || $user['mustChangePassword'] || !isset(Permissions::RANKS[$minimum])) return false;
        $centralRole = Permissions::projectRole($user, $this->adapter->project());
        $role = $link['role'] ?? null;
        if (!in_array($role, $this->adapter->supportedRoles(), true) || !isset(Permissions::RANKS[$centralRole ?? ''])) return false;
        return $this->adapter->withSnapshot($link['legacyId'], function (array $snapshot) use ($role, $centralRole, $minimum): bool {
            $source = $this->adapter->sourceRole($snapshot);
            if ($source !== null && !in_array($source, $this->adapter->supportedRoles(), true)) return false;
            return min(Permissions::RANKS[$role], Permissions::RANKS[$centralRole], Permissions::RANKS[$source ?? $role]) >= Permissions::RANKS[$minimum];
        });
    }
    /** Read-only validation; no folder, account, budget, backup, or mapping creation. */
    public function validate(string $actorId, int $version): array
    {
        return $this->directory->withAdministrator($actorId, $version, function (array $state): array {
            $inventory = $this->adapter->inventory();
            $records = $this->records();
            $rows = []; $seen = [];
            foreach ($inventory as $legacyId => $account) {
                $targets = [];
                foreach ($records['links'] as $id => $link) if (($link['legacyId'] ?? null) === (string)$legacyId) $targets[] = (string)$id;
                $errors = $account['errors'];
                if (count($targets) > 1) $errors[] = 'Duplicate ownership links.';
                foreach ($targets as $id) {
                    try { $this->target($state, $id); } catch (\InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
                    if (!in_array($records['links'][$id]['role'] ?? null, $this->adapter->supportedRoles(), true)) $errors[] = 'Linked permissions require review before shared access.';
                    $seen[$id] = true;
                }
                $rows[] = ['legacyId' => (string)$legacyId, 'targets' => $targets, 'errors' => $errors,
                    'status' => $errors ? 'Blocked' : ($targets ? 'Linked' : 'Not linked')];
            }
            foreach ($records['links'] as $id => $link) if (!isset($seen[$id])) {
                $rows[] = ['legacyId' => $link['legacyId'], 'targets' => [(string)$id], 'status' => 'Blocked', 'errors' => ['Legacy account no longer exists.']];
            }
            return $rows;
        });
    }
}
