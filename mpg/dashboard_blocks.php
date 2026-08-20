<?php
// ============================================================================
// File: dashboard_blocks.php
// Purpose: Admin-only view of public dashboard lookup failures/cooldowns.
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die('<h2>Access denied.</h2><p>Password login is required to view dashboard lookup blocks.</p><p><a href="login.php">Admin Login</a></p>');
}

$attemptFile = __DIR__ . '/dashboard_attempts.json';
$data = [];
if (file_exists($attemptFile)) {
    $decoded = json_decode((string)file_get_contents($attemptFile), true);
    if (is_array($decoded)) $data = $decoded;
}

$now = time();
$rows = array_values($data);
usort($rows, static function($a, $b) {
    $aTime = strtotime((string)($a['last_failed_at'] ?? $a['last_success_at'] ?? '1970-01-01')) ?: 0;
    $bTime = strtotime((string)($b['last_failed_at'] ?? $b['last_success_at'] ?? '1970-01-01')) ?: 0;
    return $bTime <=> $aTime;
});

function statusFor(array $row, int $now): array {
    $until = !empty($row['blocked_until']) ? strtotime((string)$row['blocked_until']) : false;
    if ($until !== false && $until > $now) {
        return ['Active', $until - $now];
    }
    if (!empty($row['block_count'])) {
        return ['Historical', 0];
    }
    return ['Never blocked', 0];
}

function remainingLabel(int $seconds): string {
    if ($seconds <= 0) return '—';
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return (int)ceil($seconds / 60) . 'm';
    return round($seconds / 3600, 1) . 'h';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Lookup Blocks</title>
<style>
body{font-family:sans-serif;max-width:1200px;margin:auto;padding:2rem 1rem}table{width:100%;border-collapse:collapse;margin-top:1rem}th,td{border:1px solid #ddd;padding:.55rem;text-align:left;font-size:.9rem;vertical-align:top}th{background:#f5f5f5}.active{color:#b00020;font-weight:bold}.historical{color:#8a5a00;font-weight:bold}.never{color:#666}.wrap{overflow-x:auto}.history{font-size:.8rem;color:#666;margin-top:.4rem}code{font-size:.82rem}
</style>
</head>
<body>
<h2>Dashboard Lookup Blocks</h2>
<p>This list tracks failed public dashboard lookups by IP, including active cooldowns and historical blocks.</p>

<div class="wrap">
<table>
<thead>
<tr>
    <th>IP</th>
    <th>Status</th>
    <th>Cooldown Remaining</th>
    <th>Total Failures</th>
    <th>Failures Toward Next Block</th>
    <th>Blocks</th>
    <th>Successful Lookups</th>
    <th>Last Attempt Plate</th>
    <th>Last Failed</th>
    <th>Blocked Until</th>
    <th>History</th>
</tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="11">No dashboard lookup attempts have been logged yet.</td></tr>
<?php else: ?>
<?php foreach ($rows as $row): [$status,$remaining]=statusFor($row,$now); $class=$status==='Active'?'active':($status==='Historical'?'historical':'never'); ?>
<tr>
    <td><code><?=htmlspecialchars((string)($row['ip'] ?? 'UNKNOWN'))?></code></td>
    <td class="<?=$class?>"><?=htmlspecialchars($status)?></td>
    <td><?=htmlspecialchars(remainingLabel($remaining))?></td>
    <td><?=number_format((int)($row['total_failures'] ?? 0))?></td>
    <td><?=number_format((int)($row['failures_since_block'] ?? 0))?> / 3</td>
    <td><?=number_format((int)($row['block_count'] ?? 0))?></td>
    <td><?=number_format((int)($row['successful_lookups'] ?? 0))?></td>
    <td><?=htmlspecialchars((string)($row['last_attempt_plate'] ?? '—'))?></td>
    <td><?=htmlspecialchars((string)($row['last_failed_at'] ?? '—'))?></td>
    <td><?=htmlspecialchars((string)($row['blocked_until'] ?? '—'))?></td>
    <td>
        <?php $history = is_array($row['history'] ?? null) ? $row['history'] : []; ?>
        <?php if (!$history): ?>—<?php else: ?>
            <?php foreach (array_reverse(array_slice($history, -5)) as $event): ?>
                <div class="history">
                    #<?=htmlspecialchars((string)($event['block_number'] ?? '?'))?> —
                    <?=htmlspecialchars((string)($event['started_at'] ?? ''))?> →
                    <?=htmlspecialchars((string)($event['blocked_until'] ?? ''))?>
                    (<?=number_format((int)($event['duration_seconds'] ?? 0))?>s)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<?php include 'menu.php'; ?>
</body>
</html>
