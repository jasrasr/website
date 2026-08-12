<?php
// ============================================================================
// File: process_photos.php
// Purpose: Accept multiple fuel-stop images, extract values, station brand,
//          and optional photo GPS metadata
// Revision: 2.1
// Author: Jason Lamb
//
// Revision Notes:
// 2.1 - Add station sign/logo recognition and EXIF GPS extraction.
// 2.0 - Multi-image fuel-value classification and extraction.
// ============================================================================

header('Content-Type: application/json');

$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['OPENAI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'OPENAI_API_KEY not set in .env']);
    exit;
}

function imageToBase64Jpeg($tmpPath, $mimeType, $maxDim = 1200) {
    $isHeic = in_array(strtolower($mimeType), ['image/heic', 'image/heif']);
    if ($isHeic) {
        if (extension_loaded('imagick')) {
            try {
                $im = new Imagick($tmpPath);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(88);
                return base64_encode($im->getImageBlob());
            } catch (Exception $e) {
                // Fall through to raw bytes.
            }
        }
        return base64_encode(file_get_contents($tmpPath));
    }

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

function callVision($apiKey, $base64Jpeg, $prompt) {
    $payload = json_encode([
        'model'      => 'gpt-4o-mini',
        'max_tokens' => 250,
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
    curl_close($ch);

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

function extractFromImage($apiKey, $tmpPath, $mimeType) {
    $b64 = imageToBase64Jpeg($tmpPath, $mimeType);

    $prompt = <<<PROMPT
You are analyzing one photo taken during a vehicle fuel stop. Classify the photo and extract only information that is clearly visible.

The photo may be ONE of these types:
1. ODOMETER - vehicle dashboard showing total mileage
2. PRICE - gas pump or sign showing price per gallon
3. PUMP - pump display showing sale total AND gallons dispensed
4. STATION - station sign, canopy, logo, or branding that identifies the fuel-station brand
5. OTHER - none of the above or not reliable enough to identify

Rules:
- For PRICE: if the display uses a 9/10 fraction, convert it to a decimal (3.69 9/10 = 3.699).
- For STATION: return only the clearly identifiable brand/company name. Do not invent a street or location.
- If uncertain, return OTHER rather than guessing.
- Return ONLY valid JSON, no markdown and no extra text.

Response formats:
- Odometer: {"type":"odometer","odometer":84824.8}
- Price:    {"type":"price","pricePerGallon":3.699}
- Pump:     {"type":"pump","totalCost":42.76,"gallons":12.290}
- Station:  {"type":"station","stationBrand":"Speedway"}
- Other:    {"type":"other"}
PROMPT;

    $text = callVision($apiKey, $b64, $prompt);

    preg_match('/\{[^}]+\}/', $text, $matches);
    if (empty($matches[0])) return null;

    return json_decode($matches[0], true);
}

function exifFractionToFloat($value) {
    if (is_numeric($value)) return (float)$value;
    $parts = explode('/', (string)$value, 2);
    if (count($parts) === 2 && (float)$parts[1] != 0.0) {
        return (float)$parts[0] / (float)$parts[1];
    }
    return (float)$value;
}

function exifGpsToDecimal($coord, $hemisphere) {
    if (!is_array($coord) || count($coord) < 3) return null;

    $degrees = exifFractionToFloat($coord[0]);
    $minutes = exifFractionToFloat($coord[1]);
    $seconds = exifFractionToFloat($coord[2]);
    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if (in_array(strtoupper((string)$hemisphere), ['S', 'W'], true)) {
        $decimal *= -1;
    }

    return $decimal;
}

function extractExifGps($tmpPath, $mimeType) {
    if (strtolower($mimeType) !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return null;
    }

    $exif = @exif_read_data($tmpPath, 'GPS', true, false);
    if (!is_array($exif) || empty($exif['GPS'])) return null;

    $gps = $exif['GPS'];
    if (empty($gps['GPSLatitude']) || empty($gps['GPSLongitude'])) return null;

    $lat = exifGpsToDecimal($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N');
    $lon = exifGpsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');

    if ($lat === null || $lon === null) return null;
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return null;

    return [
        'latitude'  => round($lat, 6),
        'longitude' => round($lon, 6)
    ];
}

$result = [];

if (empty($_FILES['images'])) {
    echo json_encode(['error' => 'No images received']);
    exit;
}

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

if (count($files) > 4) {
    echo json_encode(['error' => 'A maximum of 4 photos can be processed per entry.']);
    exit;
}

foreach ($files as $file) {
    if (!isset($result['latitude'], $result['longitude'])) {
        $gps = extractExifGps($file['tmp_name'], $file['type']);
        if ($gps) {
            $result['latitude'] = $gps['latitude'];
            $result['longitude'] = $gps['longitude'];
            $result['locationSource'] = 'photo_exif';
        }
    }

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

        case 'station':
            if (!isset($result['stationBrand']) && !empty($extracted['stationBrand'])) {
                $result['stationBrand'] = trim((string)$extracted['stationBrand']);
            }
            break;
    }
}

echo json_encode($result);
