<?php
// ============================================================================
// File: fill_baseline.php
// Purpose: Return robust per-vehicle fill-volume baseline for prompt heuristics
// Revision: 1.0.0
// Author: Jason Lamb
// ============================================================================

header('Content-Type: application/json');

$plate = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_GET['plate'] ?? '')));
if ($plate === '') {
    echo json_encode(['error' => 'Plate is required.']);
    exit;
}

$logFile = __DIR__ . "/logs/{$plate}.json";
if (!file_exists($logFile)) {
    echo json_encode(['success' => true, 'count' => 0, 'ready' => false]);
    exit;
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Fuel log is unreadable.']);
    exit;
}

$values = [];
foreach ($data as $entry) {
    $fillType = strtolower((string)($entry['fill_type'] ?? 'full'));
    $gallons = (float)($entry['gallons'] ?? 0);
    if ($fillType === 'partial' || $gallons <= 0) continue;
    $values[] = $gallons;
}

sort($values, SORT_NUMERIC);
$count = count($values);

function median($values) {
    $n = count($values);
    if ($n === 0) return null;
    $mid = intdiv($n, 2);
    return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
}

$med = median($values);
$ready = $count >= 5 && $med !== null;
$threshold = $ready ? max(2.0, round($med * 0.45, 2)) : null;

echo json_encode([
    'success' => true,
    'ready' => $ready,
    'count' => $count,
    'medianGallons' => $med !== null ? round($med, 3) : null,
    'promptBelowGallons' => $threshold,
    'rule' => 'Prompt only when gallons are below 45% of the historical median; never auto-classify.'
]);
