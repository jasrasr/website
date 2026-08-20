<?php
// ============================================================================
// File: dashboard.php
// Purpose: Public vehicle dashboard lookup by license plate with progressive
//          IP cooldown after repeated failed lookups.
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

$attemptFile = __DIR__ . '/dashboard_attempts.json';
$logDir = __DIR__ . '/logs/';
$visitorIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$now = time();

// Cooldown schedule: 1m, 5m, 15m, 1h, 3h, 6h, 12h, 24h.
$cooldownSchedule = [60, 300, 900, 3600, 10800, 21600, 43200, 86400];

function loadAttempts(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveAttempts(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function normalizePlate(string $value): string {
    return strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', trim($value)));
}

function findPlateCaseInsensitive(string $enteredPlate, string $logDir): ?string {
    if ($enteredPlate === '' || !is_dir($logDir)) return null;
    foreach (glob($logDir . '*.json') ?: [] as $file) {
        $existingPlate = basename($file, '.json');
        if (strcasecmp($enteredPlate, $existingPlate) === 0) {
            return $existingPlate;
        }
    }
    return null;
}

function formatDuration(int $seconds): string {
    if ($seconds < 60) return $seconds . ' second' . ($seconds === 1 ? '' : 's');
    if ($seconds < 3600) {
        $m = (int)ceil($seconds / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's');
    }
    $h = (int)ceil($seconds / 3600);
    return $h . ' hour' . ($h === 1 ? '' : 's');
}

$attempts = loadAttempts($attemptFile);
$key = hash('sha256', $visitorIP);
if (!isset($attempts[$key]) || !is_array($attempts[$key])) {
    $attempts[$key] = [
        'ip' => $visitorIP,
        'failures_since_block' => 0,
        'total_failures' => 0,
        'successful_lookups' => 0,
        'cooldown_level' => 0,
        'block_count' => 0,
        'blocked_until' => null,
        'last_failed_at' => null,
        'last_success_at' => null,
        'last_attempt_plate' => null,
        'history' => []
    ];
}
$record =& $attempts[$key];

// Reset escalation if the IP has been quiet for 24 hours.
if (!empty($record['last_failed_at'])) {
    $lastFailTs = strtotime((string)$record['last_failed_at']);
    if ($lastFailTs !== false && ($now - $lastFailTs) >= 86400) {
        $record['failures_since_block'] = 0;
        $record['cooldown_level'] = 0;
    }
}

$blockedUntilTs = !empty($record['blocked_until']) ? strtotime((string)$record['blocked_until']) : false;
$isBlocked = ($blockedUntilTs !== false && $blockedUntilTs > $now);
$remainingSeconds = $isBlocked ? ($blockedUntilTs - $now) : 0;
$error = '';
$notFoundPlate = '';

// Allow the immediately offered "start first entry" action even if the failed
// lookup itself triggered a cooldown. This does not expose any existing plate.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_first') {
    $plate = normalizePlate((string)($_POST['plate'] ?? ''));
    $pending = normalizePlate((string)($_SESSION['dashboard_pending_plate'] ?? ''));
    if ($plate !== '' && $pending !== '' && hash_equals($pending, $plate)) {
        $_SESSION['active_plate'] = $plate;
        unset($_SESSION['dashboard_pending_plate']);
        header('Location: fuel_form.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'lookup') === 'lookup') {
    if ($isBlocked) {
        http_response_code(429);
        $error = 'Too many failed lookups. Try again in ' . formatDuration($remainingSeconds) . '.';
    } else {
        $enteredPlate = normalizePlate((string)($_POST['plate'] ?? ''));
        if ($enteredPlate === '') {
            $error = 'Enter a license plate.';
        } else {
            $matchedPlate = findPlateCaseInsensitive($enteredPlate, $logDir);
            if ($matchedPlate !== null) {
                $record['successful_lookups'] = (int)($record['successful_lookups'] ?? 0) + 1;
                $record['failures_since_block'] = 0;
                $record['cooldown_level'] = 0;
                $record['blocked_until'] = null;
                $record['last_success_at'] = date('c');
                $record['last_attempt_plate'] = $enteredPlate;
                saveAttempts($attemptFile, $attempts);

                $_SESSION['active_plate'] = $matchedPlate;
                unset($_SESSION['dashboard_pending_plate']);
                header('Location: view_stats.php?plate=' . urlencode($matchedPlate));
                exit;
            }

            $record['failures_since_block'] = (int)($record['failures_since_block'] ?? 0) + 1;
            $record['total_failures'] = (int)($record['total_failures'] ?? 0) + 1;
            $record['last_failed_at'] = date('c');
            $record['last_attempt_plate'] = $enteredPlate;
            $notFoundPlate = $enteredPlate;
            $_SESSION['dashboard_pending_plate'] = $enteredPlate;

            if ($record['failures_since_block'] >= 3) {
                $level = max(0, (int)($record['cooldown_level'] ?? 0));
                $duration = $cooldownSchedule[min($level, count($cooldownSchedule) - 1)];
                $untilTs = $now + $duration;
                $record['blocked_until'] = date('c', $untilTs);
                $record['cooldown_level'] = $level + 1;
                $record['block_count'] = (int)($record['block_count'] ?? 0) + 1;
                $record['failures_since_block'] = 0;
                $record['history'][] = [
                    'started_at' => date('c', $now),
                    'blocked_until' => date('c', $untilTs),
                    'duration_seconds' => $duration,
                    'trigger_plate' => $enteredPlate,
                    'block_number' => $record['block_count']
                ];
                if (count($record['history']) > 100) {
                    $record['history'] = array_slice($record['history'], -100);
                }
                $error = 'No dashboard found for that plate. This IP is now in a ' . formatDuration($duration) . ' cooldown after 3 failed lookups.';
                $isBlocked = true;
                $remainingSeconds = $duration;
            } else {
                $triesLeft = 3 - (int)$record['failures_since_block'];
                $error = 'No dashboard found for that plate. ' . $triesLeft . ' failed attempt' . ($triesLeft === 1 ? '' : 's') . ' remain before cooldown.';
            }

            saveAttempts($attemptFile, $attempts);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle MPG Dashboard</title>
<style>
body{font-family:sans-serif;max-width:620px;margin:auto;padding:2rem 1rem;color:#222}
.card{border:1px solid #ddd;border-radius:10px;padding:1.2rem;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06)}
label{display:block;margin-bottom:.35rem;font-weight:600}input{width:100%;box-sizing:border-box;padding:.7rem;font-size:1rem;text-transform:uppercase}button{margin-top:.8rem;padding:.65rem 1rem;font-size:1rem;cursor:pointer}.error{background:#fff3cd;color:#664d03;padding:.8rem;border-radius:7px;margin-bottom:1rem}.note{color:#666;font-size:.9rem;margin-top:.65rem}.first-entry{margin-top:1rem;padding-top:1rem;border-top:1px solid #ddd}.first-entry button{background:#198754;color:#fff;border:0;border-radius:6px}
</style>
</head>
<body>
<h2>Vehicle MPG Dashboard</h2>
<p>Enter the vehicle's license plate to open its dashboard.</p>

<?php if ($error !== ''): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<div class="card">
<?php if (!$isBlocked): ?>
<form method="post" action="dashboard.php" autocomplete="off">
    <input type="hidden" name="action" value="lookup">
    <label for="plate">License Plate</label>
    <input type="text" id="plate" name="plate" maxlength="16" required autofocus autocomplete="off" autocapitalize="characters" value="<?=htmlspecialchars($notFoundPlate)?>">
    <button type="submit">Open Dashboard</button>
</form>
<div class="note">Plate matching is case-insensitive. Existing license plates are not listed publicly.</div>
<?php else: ?>
<p><strong>Lookup temporarily unavailable for this IP.</strong></p>
<p class="note">Cooldown remaining: <?=htmlspecialchars(formatDuration($remainingSeconds))?></p>
<?php endif; ?>

<?php if ($notFoundPlate !== ''): ?>
<div class="first-entry">
    <strong>New vehicle?</strong>
    <p>No existing log matched <strong><?=htmlspecialchars($notFoundPlate)?></strong>. You can start its first MPG entry instead.</p>
    <form method="post" action="dashboard.php">
        <input type="hidden" name="action" value="start_first">
        <input type="hidden" name="plate" value="<?=htmlspecialchars($notFoundPlate)?>">
        <button type="submit">Enter First MPG Entry</button>
    </form>
</div>
<?php endif; ?>
</div>

<?php include 'menu.php'; ?>
</body>
</html>
