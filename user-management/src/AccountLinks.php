<?php
declare(strict_types=1);
namespace Jasr\Users;

use Jasr\Framework\JsonStore;

/** Links identities without changing legacy identifiers, credentials, or data files. */
final class AccountLinks
{
    public function __construct(private Directory $directory, private AccountLinkAdapter $adapter) {}
    private function path(): string { return $this->adapter->storage() . '/links.json'; }
    public function records(): array
    {
        return $this->validateRecords(JsonStore::read($this->path())['records']);
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
        if (!isset($state['projects'][$project]) || !$user || !$user['active'] ||
            Permissions::projectRole($user, $project) === null) {
            throw new \InvalidArgumentException('The target must be active and have access to the registered project.');
        }
        return $user;
    }
    private function plan(array $state, array $records, string $legacyId, string $targetId, array $snapshot): array
    {
        $target = $this->target($state, $targetId);
        if (isset($records['links'][$targetId])) throw new \InvalidArgumentException('This central account is already linked. Reassignment is not allowed.');
        foreach ($records['links'] as $link) {
            if (($link['legacyId'] ?? null) === $legacyId) throw new \InvalidArgumentException('This legacy account is already linked.');
        }
        return ['project' => $this->adapter->project(), 'legacyId' => $legacyId, 'targetId' => $targetId,
            'targetUsername' => $target['username'], 'targetName' => $target['name'],
            'fingerprint' => hash('sha256', json_encode([$records, $target, $state['projects'][$this->adapter->project()], $snapshot], JSON_THROW_ON_ERROR))];
    }
    public function preview(string $actorId, int $version, string $legacyId, string $targetId): array
    {
        return $this->directory->withAdministrator($actorId, $version, function (array $state) use ($legacyId, $targetId): array {
            return $this->adapter->withSnapshot($legacyId, fn(array $snapshot): array =>
                $this->plan($state, $this->records(), $legacyId, $targetId, $snapshot));
        });
    }
    public function apply(string $actorId, int $version, array $preview): void
    {
        $this->directory->withAdministrator($actorId, $version, function (array $state) use ($actorId, $preview): void {
            $this->adapter->withSnapshot($preview['legacyId'], function (array $snapshot) use ($state, $actorId, $preview): void {
                $storage = $this->adapter->storage();
                if (!is_dir($storage) && !mkdir($storage, 0700, true)) throw new \RuntimeException('Cannot create private link storage.');
                JsonStore::update($this->path(), function (array $r) use ($state, $actorId, $preview, $snapshot, $storage): array {
                    $r = $this->validateRecords($r);
                    $fresh = $this->plan($state, $r, $preview['legacyId'], $preview['targetId'], $snapshot);
                    if (!hash_equals($fresh['fingerprint'], $preview['fingerprint'])) {
                        throw new \InvalidArgumentException('Accounts, data, or links changed. Run preview again.');
                    }
                    // Backup must succeed before the mapping is committed. No legacy writes occur.
                    $backupId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8));
                    $backup = $storage . '/backup-' . $backupId . '.json';
                    JsonStore::update($backup, fn(array $unused): array => [
                        'project' => $this->adapter->project(), 'actorId' => $actorId,
                        'targetId' => $preview['targetId'], 'legacyId' => $preview['legacyId'],
                        'snapshot' => $snapshot, 'previousLinks' => $r,
                    ]);
                    $link = ['legacyId' => $preview['legacyId'], 'linkedAt' => gmdate(DATE_ATOM),
                        'linkedBy' => $actorId, 'backupId' => $backupId];
                    $r['links'][$preview['targetId']] = $link;
                    $r['history'][] = ['action' => 'link', 'targetId' => $preview['targetId']] + $link;
                    return $r;
                });
            });
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
