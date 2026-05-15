<?php
/*
    License Plate Photo Logger
    Revision: 1.0.0
    Description: Shared configuration for batch license plate photo uploads, extraction, duplicate detection, and JSON logging.
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

const APP_NAME = 'License Plate Photo Logger';
const APP_REVISION = '1.0.0';
const APP_UPDATED = '2026-05-08';

const DATA_DIR = __DIR__ . '/data';
const UPLOAD_DIR = __DIR__ . '/uploads';
const LOG_FILE = DATA_DIR . '/plate-log.json';
const HASH_INDEX_FILE = DATA_DIR . '/file-hashes.json';
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

function normalizePlateText(string $value): string
{
    $value = strtoupper($value);
    $value = preg_replace('/[^A-Z0-9]/', '', $value);
    return trim($value ?? '');
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
                    'text' => 'Read the license plate number/word/text from this photo. Return only JSON with keys plate, confidence, notes. Use uppercase letters and digits. If no plate is readable, use an empty plate and confidence 0.',
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
            'raw_text' => $content,
            'error' => '',
        ];
    }

    return [
        'plate' => normalizePlateText((string)($parsed['plate'] ?? '')),
        'confidence' => (int)round((float)($parsed['confidence'] ?? 0)),
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
