<?php
/**
 * Project: Family GPS Tracker
 * File: security-maintenance.php
 * Revision: 1.5.4
 * Description: Consent review and signed-in cleanup of expired device, throttle, and old audit records.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/login-throttle.php';

init_app_storage();

try {
    $user = require_user();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(security_maintenance_payload($user));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 60);

    switch ($action) {
        case 'accept_consent':
            accept_current_consent($user);
            break;
        case 'cleanup_records':
            cleanup_security_records($user);
            break;
        default:
            fail('Unknown security-maintenance action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker security-maintenance error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function security_maintenance_payload(array $user, array $extra = []): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'consentVersion' => CONSENT_VERSION,
        'acceptedConsentVersion' => $user['consentVersion'] ?? null,
        'consentReviewRequired' => (($user['consentVersion'] ?? '') !== CONSENT_VERSION),
        'auditRetentionDays' => AUDIT_RETENTION_DAYS,
    ] + $extra;
}

function accept_current_consent(array $user): void
{
    $user['consentVersion'] = CONSENT_VERSION;
    $user['consentAcceptedAt'] = now_iso();
    $user['updatedAt'] = now_iso();
    write_user($user);
    audit_event('consent_review_accepted', ['userId' => $user['id'], 'consentVersion' => CONSENT_VERSION]);
    ok(security_maintenance_payload($user, ['message' => 'Privacy and location-sharing consent reviewed and accepted.']));
}

function cleanup_security_records(array $user): void
{
    $devices = 0;
    foreach (list_json_records('persistent_logins') as $record) {
        $selector = safe_id((string)($record['selector'] ?? ''));
        $expiresAt = isset($record['expiresAt']) ? strtotime((string)$record['expiresAt']) : false;
        if ($selector !== '' && (!$expiresAt || $expiresAt < time())) {
            delete_json_file(remember_token_path($selector));
            $devices++;
        }
    }

    $throttles = cleanup_login_throttle_records();
    $audits = 0;
    $cutoff = strtotime('-' . AUDIT_RETENTION_DAYS . ' days 00:00:00 UTC');
    foreach (glob(DATA_DIR . '/audit/*.json') ?: [] as $path) {
        $date = pathinfo($path, PATHINFO_FILENAME);
        $timestamp = strtotime($date . ' 00:00:00 UTC');
        if ($timestamp !== false && $timestamp < $cutoff && @unlink($path)) $audits++;
    }

    audit_event('cleanup_security_records', [
        'userId' => $user['id'],
        'expiredDevicesDeleted' => $devices,
        'throttleRecordsDeleted' => $throttles,
        'auditFilesDeleted' => $audits,
    ]);

    ok(security_maintenance_payload($user, [
        'message' => 'Cleanup completed.',
        'deleted' => ['expiredDevices' => $devices, 'throttleRecords' => $throttles, 'auditFiles' => $audits],
    ]));
}
