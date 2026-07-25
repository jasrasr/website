<?php
/*
    License Plate Photo Logger
    Revision: 1.2.4
    Description: Shared configuration for batch license plate photo uploads, metadata/state extraction, changelog helpers, duplicate cleanup, and clarity scoring.
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

const APP_NAME = 'License Plate Photo Logger';
const APP_REVISION = '1.2.4';
const APP_UPDATED = '2026-07-25';

const DATA_DIR = __DIR__ . '/data';
const UPLOAD_DIR = __DIR__ . '/uploads';
const LOG_FILE = DATA_DIR . '/plate-log.json';
const HASH_INDEX_FILE = DATA_DIR . '/file-hashes.json';
const CHANGELOG_FILE = __DIR__ . '/changelog.md';
const MAX_UPLOAD_BYTES = 12 * 1024 * 1024;

// ai = OpenAI vision parser, ocrspace = OCR.Space text extraction plus local plate cleanup,
// tesseract = local shell OCR plus cleanup, manual = do not call external services.
const SCAN_MODE = 'ai';
const OPENAI_MODEL = 'gpt-4o-mini';

if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

$env = file_exists(__DIR__ . '/.env') ? parse_ini_file(__DIR__ . '/.env') : [];
if (!is_array($env)) {
    $env = [];
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', $env['OPENAI_API_KEY'] ?? '');
}
if (!defined('OCRSPACE_API_KEY')) {
    define('OCRSPACE_API_KEY', $env['OCRSPACE_API_KEY'] ?? '');
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'image/heif',
];

function ensureAppFolders(): void
{
    foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function readJsonFile(string $file, array $fallback = []): array
{
    if (!file_exists($file)) {
        return $fallback;
    }
    $json = file_get_contents($file);
    $data = json_decode($json ?: '', true);
    return is_array($data) ? $data : $fallback;
}

function writeJsonFile(string $file, array $data): void
{
    ensureAppFolders();
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $file);
}

function readLogEntries(): array
{
    $entries = readJsonFile(LOG_FILE);
    usort($entries, fn($a, $b) => strcmp(($b['processed_at'] ?? ''), ($a['processed_at'] ?? '')));
    return $entries;
}

function readHashIndex(): array
{
    return readJsonFile(HASH_INDEX_FILE);
}

function readProjectRevision(): string
{
    $versionFile = __DIR__ . '/VERSION.txt';
    if (is_file($versionFile)) {
        $version = trim((string)file_get_contents($versionFile));
        if ($version !== '') {
            return $version;
        }
    }
    return APP_REVISION;
}

function readProjectModifiedAt(): string
{
    if (!is_file(CHANGELOG_FILE)) {
        return '';
    }
    $timestamp = filemtime(CHANGELOG_FILE);
    return $timestamp ? date('Y-m-d H:i:s T', $timestamp) : '';
}

function changelogHtml(): string
{
    if (!is_file(CHANGELOG_FILE)) {
        return '<p class="small">No changelog is available.</p>';
    }

    $lines = preg_split("/\r\n|\n|\r/", (string)file_get_contents(CHANGELOG_FILE)) ?: [];
    $html = [];
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<h2>' . h($matches[1]) . '</h2>';
            continue;
        }

        if (preg_match('/^###\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<h3>' . h($matches[1]) . '</h3>';
            continue;
        }

        if (preg_match('/^- (.+)$/', $trimmed, $matches)) {
            if (!$inList) {
                $html[] = '<ul>';
                $inList = true;
            }
            $html[] = '<li>' . h($matches[1]) . '</li>';
            continue;
        }

        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
        $html[] = '<p>' . h($trimmed) . '</p>';
    }

    if ($inList) {
        $html[] = '</ul>';
    }

    return implode("\n", $html);
}

function normalizePlateText(string $value): string
{
    $value = strtoupper($value);
    $value = preg_replace('/[^A-Z0-9]/', '', $value);
    return trim($value ?? '');
}

function normalizeConfidenceValue(float|int|string|null $value): int
{
    $numeric = (float)($value ?? 0);
    if ($numeric >= 0 && $numeric <= 1) {
        $numeric *= 100;
    }
    $normalized = (int)round($numeric);
    return max(0, min(100, $normalized));
}

function scanModeLabel(string $mode): string
{
    return match($mode) {
        'ai' => 'OpenAI',
        'ocrspace' => 'OCR.Space',
        'tesseract' => 'Tesseract',
        'manual' => 'Manual',
        default => $mode,
    };
}

function usStateMap(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];
}

function normalizeUsStateName(string|null $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $map = usStateMap();
    $upper = strtoupper($value);
    if (isset($map[$upper])) {
        return $map[$upper];
    }

    $normalized = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]+/', ' ', $upper) ?? '') ?? '');
    if ($normalized === '') {
        return '';
    }

    foreach ($map as $name) {
        if (strtoupper($name) === $normalized) {
            return $name;
        }
    }

    return '';
}

function extractPlateStateFromText(string $text): string
{
    $text = strtoupper($text);
    $clean = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]+/', ' ', $text) ?? '') ?? '');
    if ($clean === '') {
        return '';
    }

    foreach (usStateMap() as $abbr => $name) {
        if (preg_match('/\b' . preg_quote(strtoupper($name), '/') . '\b/', $clean)) {
            return $name;
        }
    }

    foreach (usStateMap() as $abbr => $name) {
        if (preg_match('/\b' . preg_quote($abbr, '/') . '\b/', $clean)) {
            return $name;
        }
    }

    return '';
}

function parseExifFraction(mixed $value): ?float
{
    if (is_numeric($value)) {
        return (float)$value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    if (!str_contains($value, '/')) {
        return is_numeric($value) ? (float)$value : null;
    }

    [$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, '1');
    if (!is_numeric($numerator) || !is_numeric($denominator) || (float)$denominator === 0.0) {
        return null;
    }

    return (float)$numerator / (float)$denominator;
}

function parseExifGpsCoordinate(mixed $parts, string $ref): ?float
{
    if (!is_array($parts) || count($parts) < 3) {
        return null;
    }

    $degrees = parseExifFraction($parts[0]);
    $minutes = parseExifFraction($parts[1]);
    $seconds = parseExifFraction($parts[2]);
    if ($degrees === null || $minutes === null || $seconds === null) {
        return null;
    }

    $value = $degrees + ($minutes / 60) + ($seconds / 3600);
    if (in_array(strtoupper($ref), ['S', 'W'], true)) {
        $value *= -1;
    }
    return round($value, 6);
}

function normalizeDateTaken(string|null $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    foreach (['Y:m:d H:i:s', 'Y-m-d H:i:s', DATE_ATOM] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('c');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('c', $timestamp) : '';
}

function displayDateTime(string|null $value): string
{
    $normalized = normalizeDateTaken($value);
    if ($normalized === '') {
        return '';
    }
    $timestamp = strtotime($normalized);
    return $timestamp ? date('Y-m-d H:i:s T', $timestamp) : '';
}

function formatGpsDisplay(?float $latitude, ?float $longitude): string
{
    if ($latitude === null || $longitude === null) {
        return '';
    }
    return number_format($latitude, 6, '.', '') . ', ' . number_format($longitude, 6, '.', '');
}

function extractImageMetadata(string $imagePath): array
{
    $metadata = [
        'date_taken' => '',
        'photo_state' => '',
        'gps_latitude' => null,
        'gps_longitude' => null,
        'gps_display' => '',
    ];

    $info = [];
    if (function_exists('getimagesize')) {
        @getimagesize($imagePath, $info);
        if (!empty($info['APP13']) && function_exists('iptcparse')) {
            $iptc = @iptcparse($info['APP13']);
            if (is_array($iptc)) {
                $metadata['photo_state'] = normalizeUsStateName($iptc['2#095'][0] ?? '');
                $metadata['date_taken'] = normalizeDateTaken($iptc['2#055'][0] ?? '');
            }
        }
    }

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($imagePath, null, true, false);
        if (is_array($exif)) {
            if ($metadata['date_taken'] === '') {
                $metadata['date_taken'] = normalizeDateTaken($exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? '');
            }

            $latitude = parseExifGpsCoordinate($exif['GPS']['GPSLatitude'] ?? null, (string)($exif['GPS']['GPSLatitudeRef'] ?? ''));
            $longitude = parseExifGpsCoordinate($exif['GPS']['GPSLongitude'] ?? null, (string)($exif['GPS']['GPSLongitudeRef'] ?? ''));
            $metadata['gps_latitude'] = $latitude;
            $metadata['gps_longitude'] = $longitude;
            $metadata['gps_display'] = formatGpsDisplay($latitude, $longitude);
        }
    }

    return $metadata;
}

function extractPlateCandidatesFromText(string $text): array
{
    $clean = strtoupper($text);
    preg_match_all('/\b[A-Z0-9][A-Z0-9\-\s]{2,10}[A-Z0-9]\b/', $clean, $matches);
    $candidates = [];
    foreach ($matches[0] ?? [] as $match) {
        $plate = normalizePlateText($match);
        $length = strlen($plate);
        if ($length >= 3 && $length <= 8 && preg_match('/[A-Z]/', $plate) && preg_match('/\d/', $plate)) {
            $candidates[] = $plate;
        }
    }
    return array_values(array_unique($candidates));
}

function plateCounts(array $entries): array
{
    $counts = [];
    foreach ($entries as $entry) {
        $plate = $entry['plate_normalized'] ?? '';
        if ($plate !== '') {
            $counts[$plate] = ($counts[$plate] ?? 0) + 1;
        }
    }
    arsort($counts);
    return $counts;
}

function createImageResource(string $path, string $mimeType): mixed
{
    return match(strtolower($mimeType)) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function computeImageClarityScore(string $path, string $mimeType): int
{
    if (!function_exists('imagecreatetruecolor')) {
        return 0;
    }

    $src = createImageResource($path, $mimeType);
    if (!$src) {
        return 0;
    }

    $width = imagesx($src);
    $height = imagesy($src);
    if ($width < 2 || $height < 2) {
        imagedestroy($src);
        return 0;
    }

    $maxDim = 220;
    $scale = min($maxDim / $width, $maxDim / $height, 1);
    $sampleW = max(2, (int)round($width * $scale));
    $sampleH = max(2, (int)round($height * $scale));
    $sample = imagecreatetruecolor($sampleW, $sampleH);
    imagecopyresampled($sample, $src, 0, 0, 0, 0, $sampleW, $sampleH, $width, $height);
    imagedestroy($src);

    $grayscale = [];
    for ($y = 0; $y < $sampleH; $y++) {
        $grayscale[$y] = [];
        for ($x = 0; $x < $sampleW; $x++) {
            $rgb = imagecolorat($sample, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $grayscale[$y][$x] = (int)round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
        }
    }
    imagedestroy($sample);

    $edgeTotal = 0.0;
    $samples = 0;
    for ($y = 0; $y < $sampleH - 1; $y++) {
        for ($x = 0; $x < $sampleW - 1; $x++) {
            $current = $grayscale[$y][$x];
            $edgeTotal += abs($current - $grayscale[$y][$x + 1]);
            $edgeTotal += abs($current - $grayscale[$y + 1][$x]);
            $samples += 2;
        }
    }

    if ($samples === 0) {
        return 0;
    }

    return (int)round(($edgeTotal / $samples) * 10);
}

function recalculatePlateClarityFlags(array &$entries): void
{
    $groups = [];

    foreach ($entries as $index => $entry) {
        $entries[$index]['best_plate_photo'] = false;
        $status = $entry['scan_status'] ?? (empty($entry['error']) ? 'complete' : 'pending');
        $plate = normalizePlateText((string)($entry['plate_normalized'] ?? $entry['plate'] ?? ''));
        if ($status !== 'complete' || $plate === '') {
            continue;
        }
        $groups[$plate][] = $index;
    }

    foreach ($groups as $indexes) {
        if (count($indexes) < 2) {
            continue;
        }

        $bestScore = null;
        foreach ($indexes as $index) {
            $score = (int)($entries[$index]['clarity_score'] ?? 0);
            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
            }
        }

        if ($bestScore === null) {
            continue;
        }

        foreach ($indexes as $index) {
            $entries[$index]['best_plate_photo'] = (int)($entries[$index]['clarity_score'] ?? 0) === $bestScore;
        }
    }
}

function refreshDuplicatePlateFlags(array &$entries): void
{
    $totals = [];
    foreach ($entries as $entry) {
        $status = $entry['scan_status'] ?? (empty($entry['error']) ? 'complete' : 'pending');
        $plate = normalizePlateText((string)($entry['plate_normalized'] ?? $entry['plate'] ?? ''));
        if ($status === 'complete' && $plate !== '') {
            $totals[$plate] = ($totals[$plate] ?? 0) + 1;
        }
    }

    foreach ($entries as $index => $entry) {
        $plate = normalizePlateText((string)($entry['plate_normalized'] ?? $entry['plate'] ?? ''));
        $entries[$index]['duplicate_plate'] = $plate !== '' && ($totals[$plate] ?? 0) > 1;
    }
}

function rebuildHashIndexFromEntries(array $entries): array
{
    $index = [];
    foreach ($entries as $entry) {
        $hash = (string)($entry['sha256'] ?? '');
        if ($hash === '' || isset($index[$hash])) {
            continue;
        }

        $index[$hash] = [
            'id' => $entry['id'] ?? '',
            'stored_file' => $entry['stored_file'] ?? '',
            'original_file' => $entry['original_file'] ?? '',
            'plate' => $entry['plate'] ?? '',
            'plate_state' => $entry['plate_state'] ?? '',
            'confidence' => $entry['confidence'] ?? 0,
            'processed_at' => $entry['processed_at'] ?? '',
            'scan_mode' => $entry['scan_mode'] ?? '',
            'scan_status' => $entry['scan_status'] ?? '',
            'error' => $entry['error'] ?? '',
            'clarity_score' => $entry['clarity_score'] ?? 0,
            'best_plate_photo' => $entry['best_plate_photo'] ?? false,
            'photo_state' => $entry['photo_state'] ?? '',
            'date_taken' => $entry['date_taken'] ?? '',
            'gps_latitude' => $entry['gps_latitude'] ?? null,
            'gps_longitude' => $entry['gps_longitude'] ?? null,
            'gps_display' => $entry['gps_display'] ?? '',
        ];
    }
    return $index;
}

function deleteLogEntries(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), fn($id) => trim($id) !== '')));
    if ($ids === []) {
        return ['deleted' => 0, 'missing' => [], 'removed_files' => []];
    }

    $entries = readLogEntries();
    $byId = [];
    foreach ($entries as $entry) {
        $byId[(string)($entry['id'] ?? '')] = $entry;
    }

    $missing = array_values(array_filter($ids, fn($id) => !isset($byId[$id])));
    $remaining = array_values(array_filter($entries, fn($entry) => !in_array((string)($entry['id'] ?? ''), $ids, true)));

    $removedStoredFiles = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $storedFile = basename((string)($byId[$id]['stored_file'] ?? ''));
            if ($storedFile !== '') {
                $removedStoredFiles[] = $storedFile;
            }
        }
    }

    refreshDuplicatePlateFlags($remaining);
    recalculatePlateClarityFlags($remaining);
    writeJsonFile(LOG_FILE, $remaining);
    writeJsonFile(HASH_INDEX_FILE, rebuildHashIndexFromEntries($remaining));

    $remainingFiles = array_map(fn($entry) => basename((string)($entry['stored_file'] ?? '')), $remaining);
    $removedFiles = [];
    foreach (array_unique($removedStoredFiles) as $storedFile) {
        if ($storedFile === '' || in_array($storedFile, $remainingFiles, true)) {
            continue;
        }
        $path = UPLOAD_DIR . '/' . $storedFile;
        if (is_file($path) && @unlink($path)) {
            $removedFiles[] = $storedFile;
        }
    }

    return [
        'deleted' => count($ids) - count($missing),
        'missing' => $missing,
        'removed_files' => $removedFiles,
    ];
}

function imageToBase64Jpeg(string $path, string $mimeType, int $maxDim = 1400): string
{
    $mimeType = strtolower($mimeType);
    if (in_array($mimeType, ['image/heic', 'image/heif'], true)) {
        if (extension_loaded('imagick')) {
            try {
                $im = new Imagick($path);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(88);
                return base64_encode($im->getImageBlob());
            } catch (Exception $e) {
                return base64_encode(file_get_contents($path) ?: '');
            }
        }
        return base64_encode(file_get_contents($path) ?: '');
    }

    if (!function_exists('imagecreatefromjpeg')) {
        return base64_encode(file_get_contents($path) ?: '');
    }

    $src = match($mimeType) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        default => false,
    };

    if (!$src) {
        return base64_encode(file_get_contents($path) ?: '');
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > $maxDim || $h > $maxDim) {
        $scale = min($maxDim / $w, $maxDim / $h);
        $newW = (int)round($w * $scale);
        $newH = (int)round($h * $scale);
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    ob_start();
    imagejpeg($src, null, 88);
    $data = ob_get_clean();
    imagedestroy($src);
    return base64_encode($data ?: '');
}

function callOpenAiVision(string $imagePath, string $mimeType): array
{
    if (OPENAI_API_KEY === '') {
        return ['plate' => '', 'confidence' => 0, 'raw_text' => '', 'error' => 'OPENAI_API_KEY is not configured.'];
    }

    $payload = json_encode([
        'model' => OPENAI_MODEL,
        'max_tokens' => 180,
        'messages' => [[
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'data:image/jpeg;base64,' . imageToBase64Jpeg($imagePath, $mimeType),
                        'detail' => 'high',
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'Read the license plate number/word/text from this photo. Return only JSON with keys plate, confidence, state, notes. Use uppercase letters and digits. If the plate state is visible, return the full state name in state. If no plate is readable, use an empty plate and confidence 0.',
                ],
            ],
        ]],
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return ['plate' => '', 'confidence' => 0, 'raw_text' => '', 'error' => 'OpenAI request failed: ' . $curlError];
    }

    $data = json_decode($response, true);
    $content = trim($data['choices'][0]['message']['content'] ?? '');
    preg_match('/\{.*\}/s', $content, $matches);
    $parsed = json_decode($matches[0] ?? $content, true);

    if (!is_array($parsed)) {
        $candidates = extractPlateCandidatesFromText($content);
        return [
            'plate' => $candidates[0] ?? '',
            'confidence' => ($candidates[0] ?? '') !== '' ? 50 : 0,
            'state' => extractPlateStateFromText($content),
            'raw_text' => $content,
            'error' => '',
        ];
    }

    return [
        'plate' => normalizePlateText((string)($parsed['plate'] ?? '')),
        'confidence' => normalizeConfidenceValue($parsed['confidence'] ?? 0),
        'state' => normalizeUsStateName((string)($parsed['state'] ?? '')),
        'raw_text' => $content,
        'error' => '',
    ];
}

function callOcrSpace(string $imagePath): array
{
    if (OCRSPACE_API_KEY === '') {
        return ['plate' => '', 'confidence' => 0, 'raw_text' => '', 'error' => 'OCRSPACE_API_KEY is not configured.'];
    }

    $post = [
        'apikey' => OCRSPACE_API_KEY,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'scale' => 'true',
        'OCREngine' => '2',
        'file' => new CURLFile($imagePath),
    ];
    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return ['plate' => '', 'confidence' => 0, 'raw_text' => '', 'error' => 'OCR.Space request failed: ' . $curlError];
    }

    $data = json_decode($response ?: '', true);
    $text = $data['ParsedResults'][0]['ParsedText'] ?? '';
    $candidates = extractPlateCandidatesFromText($text);
    return [
        'plate' => $candidates[0] ?? '',
        'confidence' => ($candidates[0] ?? '') !== '' ? 65 : 0,
        'state' => extractPlateStateFromText($text),
        'raw_text' => $text,
        'error' => '',
    ];
}

function callTesseract(string $imagePath): array
{
    $cmd = 'tesseract ' . escapeshellarg($imagePath) . ' stdout 2>&1';
    $text = shell_exec($cmd) ?: '';
    $candidates = extractPlateCandidatesFromText($text);
    return [
        'plate' => $candidates[0] ?? '',
        'confidence' => ($candidates[0] ?? '') !== '' ? 55 : 0,
        'state' => extractPlateStateFromText($text),
        'raw_text' => $text,
        'error' => '',
    ];
}

function scanImage(string $imagePath, string $mimeType): array
{
    return match(SCAN_MODE) {
        'ai' => callOpenAiVision($imagePath, $mimeType),
        'ocrspace' => callOcrSpace($imagePath),
        'tesseract' => callTesseract($imagePath),
        default => ['plate' => '', 'confidence' => 0, 'raw_text' => '', 'error' => 'Manual mode: no scanner was run.'],
    };
}
