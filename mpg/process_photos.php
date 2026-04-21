<?php
// ============================================================================
// File: process_photos.php
// Purpose: Receive uploaded images, call OpenAI Vision API, return JSON
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

header('Content-Type: application/json');

// Load API key from .env
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo json_encode(['error' => '.env file not found on server']);
    exit;
}

$env    = parse_ini_file($envPath);
$apiKey = $env['OPENAI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'OPENAI_API_KEY not set in .env']);
    exit;
}

// ─────────────────────────────────────────
// Resize image and return base64-encoded JPEG
// ─────────────────────────────────────────
function imageToBase64Jpeg($tmpPath, $mimeType, $maxDim = 1200) {
    if (!function_exists('imagecreatefromjpeg')) {
        return base64_encode(file_get_contents($tmpPath));
    }

    switch ($mimeType) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($tmpPath); break;
        case 'image/png':  $src = @imagecreatefrompng($tmpPath);  break;
        case 'image/webp': $src = @imagecreatefromwebp($tmpPath); break;
        default:           $src = false;
    }

    if (!$src) {
        return base64_encode(file_get_contents($tmpPath));
    }

    $w = imagesx($src);
    $h = imagesy($src);

    if ($w > $maxDim || $h > $maxDim) {
        if ($w >= $h) {
            $newW = $maxDim;
            $newH = (int)round($h * $maxDim / $w);
        } else {
            $newH = $maxDim;
            $newW = (int)round($w * $maxDim / $h);
        }
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    ob_start();
    imagejpeg($src, null, 88);
    $data = ob_get_clean();
    imagedestroy($src);

    return base64_encode($data);
}

// ─────────────────────────────────────────
// Call OpenAI Vision API
// ─────────────────────────────────────────
function callVision($apiKey, $base64Jpeg, $prompt) {
    $payload = json_encode([
        'model'      => 'gpt-4o-mini',
        'max_tokens' => 150,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                [
                    'type'      => 'image_url',
                    'image_url' => [
                        'url'    => 'data:image/jpeg;base64,' . $base64Jpeg,
                        'detail' => 'high'
                    ]
                ],
                ['type' => 'text', 'text' => $prompt]
            ]
        ]]
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return null;

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

function validUpload($key) {
    return isset($_FILES[$key])
        && $_FILES[$key]['error'] === UPLOAD_ERR_OK
        && !empty($_FILES[$key]['tmp_name']);
}

$result = [];

// --- Odometer ---
if (validUpload('odometer')) {
    $b64  = imageToBase64Jpeg($_FILES['odometer']['tmp_name'], $_FILES['odometer']['type']);
    $text = callVision($apiKey, $b64,
        "This is a photo of a vehicle odometer or instrument cluster. " .
        "What is the total mileage reading? Return ONLY the number with up to one decimal place. " .
        "No units, no extra text. Example: 84824.8"
    );
    // Strip any non-numeric characters except decimal point
    $clean = preg_replace('/[^0-9.]/', '', $text);
    $result['odometer'] = is_numeric($clean) ? $clean : null;
}

// --- Price per Gallon ---
if (validUpload('price')) {
    $b64  = imageToBase64Jpeg($_FILES['price']['tmp_name'], $_FILES['price']['type']);
    $text = callVision($apiKey, $b64,
        "This is a photo of a gas pump showing the price per gallon. " .
        "What is the price per gallon for Regular Unleaded? " .
        "If it shows a fraction like '9/10', convert to decimal (e.g. 3.69 9/10 = 3.699). " .
        "Return ONLY the decimal number, no dollar sign, no extra text. Example: 3.699"
    );
    $clean = preg_replace('/[^0-9.]/', '', $text);
    $result['pricePerGallon'] = is_numeric($clean) ? $clean : null;
}

// --- Pump Total & Gallons ---
if (validUpload('pump')) {
    $b64  = imageToBase64Jpeg($_FILES['pump']['tmp_name'], $_FILES['pump']['type']);
    $text = callVision($apiKey, $b64,
        "This is a photo of a gas pump display showing two numbers: " .
        "the total dollar amount of the sale (labeled 'THIS SALE \$' or similar) " .
        "and the total gallons dispensed (labeled 'GALLONS'). " .
        "Return ONLY valid JSON with no markdown, no extra text. " .
        "Example: {\"totalCost\": 42.76, \"gallons\": 12.290}"
    );

    // Extract JSON even if model wraps it in markdown
    preg_match('/\{[^}]+\}/', $text, $matches);
    if (!empty($matches[0])) {
        $parsed = json_decode($matches[0], true);
        if ($parsed) {
            $result['totalCost'] = isset($parsed['totalCost']) ? (float)$parsed['totalCost'] : null;
            $result['gallons']   = isset($parsed['gallons'])   ? (float)$parsed['gallons']   : null;
        }
    }
}

echo json_encode($result);
