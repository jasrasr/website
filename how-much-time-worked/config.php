<?php
/*
    Timeclock Photo Logger
    Revision: 1.2.3
    Author: Jason Lamb (with help from Claude Code CLI)
    Created: 2026-04-27
    Modified: 2026-04-27
    Description: Shared configuration for image upload, OCR review, per-employee JSON logging, and hour calculations.
    Changelog:
    1.0.0 initial release
    1.1.0 per-employee JSON files instead of single hours.json
    1.2.0 remove unused fields: unit, job, carry_over_tips, declared_tips, charge_tips
    1.2.1 improved employee name parsing — positional (line after Unit #) instead of regex guess
    1.2.2 fix OCR field parsing for two-column receipt layout (times, shift/week hours)
    1.2.3 handle truncated "Hours this" label for week hours; allow bare number values
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

const APP_NAME = 'Timeclock Photo Logger';
const APP_REVISION = '1.2.3';
const APP_UPDATED = '2026-04-27';

const DATA_DIR = __DIR__ . '/data';
const DATA_EMPLOYEES_DIR = DATA_DIR . '/employees';
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

// OCR mode options:
// manual = no OCR; user types/corrects fields after upload
// tesseract = attempts local shell command: tesseract image stdout
// ocrspace = calls OCR.Space API if OCRSPACE_API_KEY is configured
const OCR_MODE = 'ocrspace'; //manual or ocrspace

// API keys are loaded from secrets.php (not committed to git).
// Copy secrets.example.php to secrets.php and fill in your key.
if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}
if (!defined('OCRSPACE_API_KEY')) {
    define('OCRSPACE_API_KEY', '');
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

function ensureAppFolders(): void
{
    foreach ([DATA_DIR, DATA_EMPLOYEES_DIR, UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function slugifyEmployee(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    return trim($slug, '_') ?: 'unknown';
}

function getEmployeeLogFile(string $employee): string
{
    return DATA_EMPLOYEES_DIR . '/' . slugifyEmployee($employee) . '.json';
}

function readEmployeeEntries(string $employee): array
{
    ensureAppFolders();
    $file = getEmployeeLogFile($employee);
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $entries = json_decode($json ?: '[]', true);
    return is_array($entries) ? $entries : [];
}

function writeEmployeeEntries(string $employee, array $entries): void
{
    ensureAppFolders();
    $file = getEmployeeLogFile($employee);
    file_put_contents($file, json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Reads all employee files merged and sorted by date descending.
function readEntries(): array
{
    ensureAppFolders();
    $entries = [];
    $files = glob(DATA_EMPLOYEES_DIR . '/*.json') ?: [];
    foreach ($files as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json ?: '[]', true);
        if (is_array($data)) {
            $entries = array_merge($entries, $data);
        }
    }
    usort($entries, fn($a, $b) => strcmp(($b['date'] ?? ''), ($a['date'] ?? '')));
    return $entries;
}

function writeEntries(array $entries): void
{
    ensureAppFolders();
    file_put_contents(DATA_DIR . '/hours.json', json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
        'date' => date('Y-m-d'),
        'employee' => '',
        'time_in' => '',
        'time_out' => '',
        'printed_shift_hours' => '',
        'printed_week_hours' => '',
        'ocr_text' => $text,
    ];

    if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $clean, $m)) {
        $result['date'] = normalizeDateInput($m[1]);
    }

    // Times — find all H:MM AM/PM patterns in order; first = time_in, second = time_out.
    // Handles OCR layouts where labels and values land on separate lines.
    preg_match_all('/\b(\d{1,2}:\d{2}[ \t]*(AM|PM))\b/i', $clean, $timeMatches);
    if (!empty($timeMatches[1])) {
        $result['time_in'] = trim($timeMatches[1][0]);
        if (isset($timeMatches[1][1])) {
            $result['time_out'] = trim($timeMatches[1][1]);
        }
    }

    // Shift hours — value may appear on the line before the label, same line, or line after.
    if (preg_match('/(\d{1,3}:\d{2})[ \t]*\n[ \t]*[^\n]*hours[ \t]*this[ \t]*shift/i', $clean, $m)) {
        $result['printed_shift_hours'] = trim($m[1]);
    } elseif (preg_match('/hours[ \t]*this[ \t]*shift[ \t]*:[ \t]*(\d[^\n]+)/i', $clean, $m)) {
        $result['printed_shift_hours'] = trim($m[1]);
    } elseif (preg_match('/hours[ \t]*this[ \t]*shift[ \t]*:[ \t]*\n[ \t]*(\d[^\n]+)/i', $clean, $m)) {
        $result['printed_shift_hours'] = trim($m[1]);
    }

    // Week hours — same variations. Also handles OCR truncating "Hours this week:" to "Hours this".
    if (preg_match('/(\d{1,3}(?::\d{2})?)[ \t]*\n[ \t]*hours[ \t]*this(?![ \t]*shift)/i', $clean, $m)) {
        $result['printed_week_hours'] = trim($m[1]);
    } elseif (preg_match('/hours[ \t]*this[ \t]*week[ \t]*:[ \t]*(\d[^\n]+)/i', $clean, $m)) {
        $result['printed_week_hours'] = trim($m[1]);
    } elseif (preg_match('/hours[ \t]*this[ \t]*week[ \t]*:[ \t]*\n[ \t]*(\d[^\n]+)/i', $clean, $m)) {
        $result['printed_week_hours'] = trim($m[1]);
    }

    // Employee name is the line immediately after the "Unit #XXXX  DATE" line.
    $lines = array_values(array_filter(array_map('trim', explode("\n", $clean))));
    foreach ($lines as $i => $line) {
        if (preg_match('/Unit\s*#/i', $line) && isset($lines[$i + 1])) {
            $candidate = $lines[$i + 1];
            if (!preg_match('/^Job|^Time|^Hours|^Employee|^Clock/i', $candidate)) {
                $result['employee'] = $candidate;
            }
            break;
        }
    }

    return $result;
}
?>
