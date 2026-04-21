<?php
// ============================================================================
// File: process_photos.php
// Purpose: Accept multiple images, classify each with AI, return extracted values
// Revision: 2.0
// Author: Jason Lamb
// ============================================================================

header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['OPENAI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'OPENAI_API_KEY not set in .env']);
    exit;
}

// ─────────────────────────────────────────
// Resize and return base64 JPEG
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
    if (!$src) return base64_encode(file_get_contents($tmpPath));

    $w = imagesx($src); $h = imagesy($src);
    if ($w > $maxDim || $h > $maxDim) {
        if ($w >= $h) { $newW = $maxDim; $newH = (int)round($h * $maxDim / $w); }
        else          { $newH = $maxDim; $newW = (int)round($w * $maxDim / $h); }
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    ob_start(); imagejpeg($src, null, 88); $data = ob_get_clean();
    imagedestroy($src);
    return base64_encode($data);
}

// ─────────────────────────────────────────
// Call OpenAI Vision API
// ─────────────────────────────────────────
function callVision($apiKey, $base64Jpeg, $prompt) {
    $payload = json_encode([
        'model'      => 'gpt-4o-mini',
        'max_tokens' => 200,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:image/jpeg;base64,' . $base64Jpeg, 'detail' => 'high']
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
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

// ─────────────────────────────────────────
// Classify and extract from one image
// ─────────────────────────────────────────
function extractFromImage($apiKey, $tmpPath, $mimeType) {
    $b64 = imageToBase64Jpeg($tmpPath, $mimeType);

    $prompt = <<<PROMPT
You are analyzing a photo taken at a gas station during refueling. Determine what this photo shows and extract the relevant numbers.

The photo will be ONE of these types:
1. ODOMETER - vehicle dashboard showing total mileage (e.g. 84824.8 mi)
2. PRICE - gas pump face showing price per gallon (e.g. Regular $3.69 9/10)
3. PUMP - gas pump display showing sale total in dollars AND total gallons dispensed

Rules:
- For PRICE: if it shows 9/10 fraction, convert to decimal (3.69 9/10 = 3.699)
- Return ONLY valid JSON, no markdown, no extra text

Response format by type:
- Odometer: {"type":"odometer","odometer":84824.8}
- Price:    {"type":"price","pricePerGallon":3.699}
- Pump:     {"type":"pump","totalCost":42.76,"gallons":12.290}
PROMPT;

    $text = callVision($apiKey, $b64, $prompt);

    // Extract JSON (handle markdown code blocks if model adds them)
    preg_match('/\{[^}]+\}/', $text, $matches);
    if (empty($matches[0])) return null;

    return json_decode($matches[0], true);
}

// ─────────────────────────────────────────
// Process all uploaded images
// ─────────────────────────────────────────
$result = [];

if (empty($_FILES['images'])) {
    echo json_encode(['error' => 'No images received']);
    exit;
}

// Normalize $_FILES array for multiple uploads
$files = [];
foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK && !empty($tmpName)) {
        $files[] = [
            'tmp_name' => $tmpName,
            'type'     => $_FILES['images']['type'][$i]
        ];
    }
}

if (empty($files)) {
    echo json_encode(['error' => 'No valid images uploaded']);
    exit;
}

foreach ($files as $file) {
    $extracted = extractFromImage($apiKey, $file['tmp_name'], $file['type']);
    if (!$extracted || !isset($extracted['type'])) continue;

    switch ($extracted['type']) {
        case 'odometer':
            if (!isset($result['odometer']) && isset($extracted['odometer'])) {
                $result['odometer'] = (float)$extracted['odometer'];
            }
            break;
        case 'price':
            if (!isset($result['pricePerGallon']) && isset($extracted['pricePerGallon'])) {
                $result['pricePerGallon'] = (float)$extracted['pricePerGallon'];
            }
            break;
        case 'pump':
            if (!isset($result['totalCost']) && isset($extracted['totalCost'])) {
                $result['totalCost'] = (float)$extracted['totalCost'];
            }
            if (!isset($result['gallons']) && isset($extracted['gallons'])) {
                $result['gallons'] = (float)$extracted['gallons'];
            }
            break;
    }
}

echo json_encode($result);
