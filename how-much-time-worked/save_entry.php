<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$date = normalizeDateInput($_POST['date'] ?? '');
$timeIn = trim($_POST['time_in'] ?? '');
$timeOut = trim($_POST['time_out'] ?? '');

try {
    $shiftMinutes = calculateShiftMinutes($date, $timeIn, $timeOut);
} catch (Throwable $e) {
    die('Invalid time format. Use something like 3:35 PM and 10:10 PM.');
}

$printedShift = trim($_POST['printed_shift_hours'] ?? '');
$printedShiftMinutes = parsePrintedHoursToMinutes($printedShift);

$entry = [
    'id' => bin2hex(random_bytes(8)),
    'employee' => trim($_POST['employee'] ?? ''),
    'unit' => trim($_POST['unit'] ?? ''),
    'date' => $date,
    'job' => trim($_POST['job'] ?? ''),
    'time_in' => $timeIn,
    'time_out' => $timeOut,
    'shift_minutes' => $shiftMinutes,
    'display_shift_hours' => formatMinutes($shiftMinutes),
    'printed_shift_hours' => $printedShift,
    'printed_shift_minutes' => $printedShiftMinutes,
    'printed_week_hours' => trim($_POST['printed_week_hours'] ?? ''),
    'carry_over_tips' => (float)($_POST['carry_over_tips'] ?? 0),
    'declared_tips' => (float)($_POST['declared_tips'] ?? 0),
    'charge_tips' => (float)($_POST['charge_tips'] ?? 0),
    'source_file' => basename($_POST['source_file'] ?? ''),
    'ocr_text' => trim($_POST['ocr_text'] ?? ''),
    'review_warning' => ($printedShiftMinutes !== null && $printedShiftMinutes !== $shiftMinutes)
        ? 'Printed shift hours do not match calculated shift hours.'
        : '',
    'submitted_at' => date('Y-m-d H:i:s T'),
];

$entries = readEntries();

// Duplicate protection: same employee/date/time-in/time-out is considered same shift.
foreach ($entries as $existing) {
    if (($existing['employee'] ?? '') === $entry['employee']
        && ($existing['date'] ?? '') === $entry['date']
        && ($existing['time_in'] ?? '') === $entry['time_in']
        && ($existing['time_out'] ?? '') === $entry['time_out']) {
        header('Location: view.php?duplicate=1');
        exit;
    }
}

$entries[] = $entry;
usort($entries, fn($a, $b) => strcmp(($b['date'] ?? ''), ($a['date'] ?? '')));
writeEntries($entries);

header('Location: view.php?saved=1');
exit;
?>
