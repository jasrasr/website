<?php
/**
 * Project: Family GPS Tracker
 * File: includes/invite-store.php
 * Revision: 1.4.8
 * Description: Expiring, limited-use, revocable group invite helpers with legacy-code compatibility.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

function invite_records(array $family): array
{
    return is_array($family['invites'] ?? null) ? $family['invites'] : [];
}

function public_invite_record(array $invite): array
{
    $maxUses = max(0, (int)($invite['maxUses'] ?? 0));
    $uses = max(0, (int)($invite['uses'] ?? 0));
    $expiresAt = $invite['expiresAt'] ?? null;
    $expired = is_string($expiresAt) && $expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time();
    $exhausted = $maxUses > 0 && $uses >= $maxUses;
    return [
        'id' => safe_id((string)($invite['id'] ?? '')),
        'label' => (string)($invite['label'] ?? 'Invite'),
        'last4' => (string)($invite['last4'] ?? ''),
        'createdAt' => $invite['createdAt'] ?? null,
        'expiresAt' => $expiresAt,
        'maxUses' => $maxUses,
        'uses' => $uses,
        'active' => !empty($invite['active']) && !$expired && !$exhausted,
        'expired' => $expired,
        'exhausted' => $exhausted,
        'lastUsedAt' => $invite['lastUsedAt'] ?? null,
    ];
}

function create_group_invite(array $family, array $user, string $label, int $expiresSeconds, int $maxUses): array
{
    $code = generate_invite_code();
    $normalized = normalize_invite_code($code);
    $now = now_iso();
    $invite = [
        'id' => 'inv_' . bin2hex(random_bytes(10)),
        'label' => $label !== '' ? substr($label, 0, 80) : 'Group invite',
        'codeHash' => password_hash($normalized, PASSWORD_DEFAULT),
        'last4' => substr($normalized, -4),
        'createdAt' => $now,
        'createdByUserId' => (string)$user['id'],
        'expiresAt' => $expiresSeconds > 0 ? gmdate('c', time() + $expiresSeconds) : null,
        'maxUses' => max(0, $maxUses),
        'uses' => 0,
        'active' => true,
    ];
    $family['invites'] = invite_records($family);
    array_unshift($family['invites'], $invite);
    $family['invites'] = array_slice($family['invites'], 0, 50);
    $family['updatedAt'] = $now;
    write_family($family);
    return [$family, $invite, $code];
}

function revoke_group_invite(array $family, string $inviteId): array
{
    $inviteId = safe_id($inviteId);
    $records = invite_records($family);
    $found = false;
    foreach ($records as &$invite) {
        if (safe_id((string)($invite['id'] ?? '')) === $inviteId) {
            $invite['active'] = false;
            $invite['revokedAt'] = now_iso();
            $found = true;
            break;
        }
    }
    unset($invite);
    if (!$found) {
        fail('Invite not found.', 404);
    }
    $family['invites'] = $records;
    $family['updatedAt'] = now_iso();
    write_family($family);
    return $family;
}

function consume_group_invite_code(string $inputCode): ?array
{
    $normalized = normalize_invite_code($inputCode);
    if ($normalized === '') {
        return null;
    }

    foreach (list_json_records('families') as $candidate) {
        $familyId = safe_id((string)($candidate['id'] ?? ''));
        foreach (invite_records($candidate) as $invite) {
            $hash = (string)($invite['codeHash'] ?? '');
            if ($hash === '' || !password_verify($normalized, $hash)) {
                continue;
            }

            return with_named_lock('invite_consume_' . $familyId, function () use ($familyId, $normalized): ?array {
                $family = read_family($familyId);
                if (!$family) {
                    return null;
                }
                $records = invite_records($family);
                foreach ($records as &$record) {
                    $hash = (string)($record['codeHash'] ?? '');
                    if ($hash === '' || !password_verify($normalized, $hash)) {
                        continue;
                    }
                    $expiresAt = $record['expiresAt'] ?? null;
                    $expired = is_string($expiresAt) && $expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time();
                    $maxUses = max(0, (int)($record['maxUses'] ?? 0));
                    $uses = max(0, (int)($record['uses'] ?? 0));
                    if (empty($record['active']) || $expired || ($maxUses > 0 && $uses >= $maxUses)) {
                        return null;
                    }
                    $record['uses'] = $uses + 1;
                    $record['lastUsedAt'] = now_iso();
                    if ($maxUses > 0 && $record['uses'] >= $maxUses) {
                        $record['active'] = false;
                    }
                    $family['invites'] = $records;
                    $family['updatedAt'] = now_iso();
                    write_family($family);
                    return $family;
                }
                unset($record);
                return null;
            });
        }

        $legacyHash = (string)($candidate['inviteCodeHash'] ?? '');
        if ($legacyHash !== '' && password_verify($normalized, $legacyHash)) {
            return $candidate;
        }
    }

    return null;
}
