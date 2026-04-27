<?php
/*
    Timeclock Photo Logger
    Revision: 1.0.0
    Author: Jason Lamb (with help from ChatGPT)
    Created: 2026-04-27
    Description: Shared configuration for image upload, OCR review, JSON logging, and hour calculations.
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

const APP_NAME = 'Timeclock Photo Logger';
const APP_REVISION = '1.0.0';

const DATA_DIR = __DIR__ . '/data';
const UPLOAD_DIR = __DIR__ . '/uploads';
const LOG_FILE = DATA_DIR . '/hours.json';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

// OCR mode options:
// manual = no OCR; user types/corrects fields after upload
// tesseract = attempts local shell command: tesseract image stdout
// ocrspace = calls OCR.Space API if OCRSPACE_API_KEY is configured
const OCR_MODE = 'manual';
const OCRSPACE_API_KEY = '';

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

function ensureAppFolders(): void
{
    foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function readEntries(): array
{
    ensureAppFolders();
    $json = file_get_contents(LOG_FILE);
    $entries = json_decode($json ?: '[]', true);
    return is_array($entries) ? $entries : [];
}

function writeEntries(array $entries): void
{
    ensureAppFolders();
    file_put_contents(LOG_FILE, json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalizeDateInput(string $date): string
{
    $date = trim($date);
    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y'];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $date);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return date('Y-m-d');
}

function calculateShiftMinutes(string $date, string $timeIn, string $timeOut): int
{
    $date = normalizeDateInput($date);
    $in = new DateTime($date . ' ' . trim($timeIn));
    $out = new DateTime($date . ' ' . trim($timeOut));

    // Handles overnight shifts without needing payroll sorcery.
    if ($out <= $in) {
        $out->modify('+1 day');
    }

    return (int)(($out->getTimestamp() - $in->getTimestamp()) / 60);
}

function formatMinutes(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return sprintf('%d:%02d', $hours, $mins);
}

function parsePrintedHoursToMinutes(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^(\d{1,3})[:.](\d{2})$/', $value, $m)) {
        return ((int)$m[1] * 60) + (int)$m[2];
    }

    if (is_numeric($value)) {
        return (int)round(((float)$value) * 60);
    }

    return null;
}

function parseClockSlipText(string $text): array
{
    $clean = preg_replace('/\r\n|\r/', "\n", $text);

    $result = [
        'unit' => '',
        'date' => date('Y-m-d'),
        'employee' => '',
        'job' => '',
        'time_in' => '',
        'time_out' => '',
        'printed_shift_hours' => '',
        'printed_week_hours' => '',
        'carry_over_tips' => '0.00',
        'declared_tips' => '0.00',
        'charge_tips' => '0.00',
        'ocr_text' => $text,
    ];

    if (preg_match('/Unit\s*#?\s*(\d+)/i', $clean, $m)) {
        $result['unit'] = $m[1];
    }

    if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $clean, $m)) {
        $result['date'] = normalizeDateInput($m[1]);
    }

    if (preg_match('/Job\s*:\s*([^\n]+)/i', $clean, $m)) {
        $result['job'] = trim($m[1]);
    }

    if (preg_match('/Time\s*in\s*:\s*([^\n]+)/i', $clean, $m)) {
        $result['time_in'] = trim($m[1]);
    }

    if (preg_match('/Time\s*out\s*:\s*([^\n]+)/i', $clean, $m)) {
        $result['time_out'] = trim($m[1]);
    }

    if (preg_match('/Hours\s*this\s*shift\s*:\s*([^\n]+)/i', $clean, $m)) {
        $result['printed_shift_hours'] = trim($m[1]);
    }

    if (preg_match('/Hours\s*this\s*week\s*:\s*([^\n]+)/i', $clean, $m)) {
        $result['printed_week_hours'] = trim($m[1]);
    }

    if (preg_match('/Carry\s*Over\s*Tips\s*:\s*\$?([0-9.]+)/i', $clean, $m)) {
        $result['carry_over_tips'] = $m[1];
    }

    if (preg_match('/Declared\s*Tips\s*:\s*\$?([0-9.]+)/i', $clean, $m)) {
        $result['declared_tips'] = $m[1];
    }

    if (preg_match('/Charge\s*Tips\s*:\s*\$?([0-9.]+)/i', $clean, $m)) {
        $result['charge_tips'] = $m[1];
    }

    // Best-effort employee name: line after the date/unit/header area and before Job.
    $lines = array_values(array_filter(array_map('trim', explode("\n", $clean))));
    foreach ($lines as $line) {
        if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+$/', $line) && !preg_match('/Employee|Clock|Unit|Job|Time|Hours|Tips/i', $line)) {
            $result['employee'] = $line;
            break;
        }
    }

    return $result;
}
?>
