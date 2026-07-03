<?php
/**
 * File: includes/screenshot-vision.php
 * Project: TV Binge Board
 * Description: Optional direct screenshot image processing helpers using a configured AI vision endpoint, with local OCR fallback when available.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function app_screenshot_ai_key(): string
{
    if (defined('OPENAI_API_KEY_LOCAL') && OPENAI_API_KEY_LOCAL !== '') { return (string)OPENAI_API_KEY_LOCAL; }
    if (defined('AI_VISION_API_KEY_LOCAL') && AI_VISION_API_KEY_LOCAL !== '') { return (string)AI_VISION_API_KEY_LOCAL; }
    $env = getenv('OPENAI_API_KEY');
    if (is_string($env) && $env !== '') { return $env; }
    $env = getenv('AI_VISION_API_KEY');
    return is_string($env) ? $env : '';
}

function app_screenshot_ai_model(): string
{
    if (defined('OPENAI_VISION_MODEL_LOCAL') && OPENAI_VISION_MODEL_LOCAL !== '') { return (string)OPENAI_VISION_MODEL_LOCAL; }
    if (defined('AI_VISION_MODEL_LOCAL') && AI_VISION_MODEL_LOCAL !== '') { return (string)AI_VISION_MODEL_LOCAL; }
    $env = getenv('OPENAI_VISION_MODEL');
    if (is_string($env) && $env !== '') { return $env; }
    $env = getenv('AI_VISION_MODEL');
    return is_string($env) && $env !== '' ? $env : 'gpt-4o-mini';
}

function app_screenshot_ai_endpoint(): string
{
    if (defined('OPENAI_CHAT_COMPLETIONS_URL_LOCAL') && OPENAI_CHAT_COMPLETIONS_URL_LOCAL !== '') { return (string)OPENAI_CHAT_COMPLETIONS_URL_LOCAL; }
    if (defined('AI_VISION_ENDPOINT_LOCAL') && AI_VISION_ENDPOINT_LOCAL !== '') { return (string)AI_VISION_ENDPOINT_LOCAL; }
    $env = getenv('OPENAI_CHAT_COMPLETIONS_URL');
    if (is_string($env) && $env !== '') { return $env; }
    $env = getenv('AI_VISION_ENDPOINT');
    return is_string($env) && $env !== '' ? $env : 'https://api.openai.com/v1/chat/completions';
}

function app_screenshot_ai_configured(): bool
{
    return app_screenshot_ai_key() !== '' && function_exists('curl_init');
}

function app_screenshot_image_data_url(string $path, string $mime): string
{
    if (!is_file($path)) { throw new RuntimeException('Screenshot file was not found.'); }
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') { throw new RuntimeException('Unable to read screenshot file.'); }
    if (strlen($bytes) > APP_MAX_UPLOAD_BYTES) { throw new RuntimeException('Screenshot is larger than the configured upload limit.'); }
    $mime = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

function app_screenshot_json_from_ai_text(string $text): array
{
    $text = trim($text);
    if ($text === '') { return []; }
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $match)) { $text = trim($match[1]); }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) { return $decoded; }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($decoded)) { return $decoded; }
    }
    return [];
}

function app_screenshot_map_status(string $status, ?int $completionPercent = null, ?int $season = null, ?int $episode = null): string
{
    $normalized = strtolower(trim($status));
    if ($completionPercent !== null && $completionPercent >= 100) { return 'completed'; }
    if (str_contains($normalized, 'drop')) { return 'dropped'; }
    if (str_contains($normalized, 'complete') || str_contains($normalized, 'finished') || str_contains($normalized, 'watched all')) { return 'completed'; }
    if (str_contains($normalized, 'want') || str_contains($normalized, 'watchlist') || str_contains($normalized, 'planned') || str_contains($normalized, 'future') || str_contains($normalized, 'upcoming')) { return 'watchlist'; }
    if ($season !== null || $episode !== null || str_contains($normalized, 'watch') || str_contains($normalized, 'progress') || str_contains($normalized, 'tracking') || str_contains($normalized, 'continue')) { return 'watching'; }
    return 'watchlist';
}

function app_screenshot_guess_from_ai_item(array $item, int $index): ?array
{
    $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
    if ($title === '' || strlen($title) < 2) { return null; }
    $type = strtolower(trim((string)($item['type'] ?? $item['media_type'] ?? '')));
    $season = isset($item['season']) && $item['season'] !== '' ? max(1, (int)$item['season']) : null;
    $episode = isset($item['episode']) && $item['episode'] !== '' ? max(1, (int)$item['episode']) : null;
    if (!in_array($type, ['movie', 'tv'], true)) { $type = ($season !== null || $episode !== null) ? 'tv' : 'movie'; }
    $year = trim((string)($item['year'] ?? ''));
    if ($year === '' && !empty($item['release_year'])) { $year = trim((string)$item['release_year']); }
    if ($year !== '' && !preg_match('/^(19|20)\d{2}$/', $year)) { $year = ''; }
    $completionPercent = null;
    if (isset($item['completion_percent']) && $item['completion_percent'] !== '') {
        $completionPercent = max(0, min(100, (int)round((float)$item['completion_percent'])));
    }
    $status = app_screenshot_map_status((string)($item['status'] ?? $item['tracking_status'] ?? ''), $completionPercent, $season, $episode);
    $confidence = (float)($item['confidence'] ?? 80);
    if ($confidence > 0 && $confidence <= 1) { $confidence *= 100; }
    $confidence = max(35, min(98, (int)round($confidence)));
    $sourceText = trim((string)($item['source_text'] ?? $item['source'] ?? $title));
    $notes = ['Processed directly from uploaded screenshot image.'];
    $context = trim((string)($item['tracking_context'] ?? $item['context'] ?? ''));
    if ($context !== '') { $notes[] = 'Context: ' . $context . '.'; }
    if ($completionPercent !== null) { $notes[] = 'Screenshot completion: ' . $completionPercent . '%.'; }
    if (!empty($item['app_source'])) { $notes[] = 'Source app: ' . trim((string)$item['app_source']) . '.'; }
    return [
        'id' => 'guess-' . $index,
        'source_text' => $sourceText,
        'title' => $title,
        'type' => $type,
        'year' => $year,
        'status' => $status,
        'season' => $type === 'tv' ? $season : null,
        'episode' => $type === 'tv' ? $episode : null,
        'completion_percent' => $completionPercent,
        'notes' => implode(' ', $notes),
        'confidence' => $confidence,
        'decision' => $confidence >= 70 ? 'approve' : 'review',
    ];
}

function app_screenshot_guesses_from_ai_json(array $decoded): array
{
    $items = $decoded['items'] ?? $decoded['titles'] ?? $decoded;
    if (!is_array($items)) { return []; }
    $guesses = [];
    $index = 1;
    foreach ($items as $item) {
        if (!is_array($item)) { continue; }
        $guess = app_screenshot_guess_from_ai_item($item, $index);
        if ($guess !== null) { $guesses[] = $guess; $index++; }
        if (count($guesses) >= 100) { break; }
    }
    return $guesses;
}

function app_screenshot_process_image_with_ai(string $path, string $mime): array
{
    if (!app_screenshot_ai_configured()) { throw new RuntimeException('AI vision processing is not configured. Add OPENAI_API_KEY_LOCAL to includes/config.local.php, or set OPENAI_API_KEY in the environment.'); }
    $payload = [
        'model' => app_screenshot_ai_model(),
        'temperature' => 0,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You extract TV and movie watch-tracking data from screenshots. Return strict JSON only.'
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Read this screenshot from TV Time or a similar app. Extract only real TV shows or movies that the screenshot indicates are being tracked. Return JSON with this schema: {"items":[{"title":"","type":"tv|movie","status":"watching|watchlist|completed|dropped","season":null,"episode":null,"year":"","completion_percent":null,"tracking_context":"","source_text":"","confidence":0}],"raw_text":""}. For TV, use the next/last visible season and episode when visible. For watch-next/continue-tracking rows use status watching. For future/upcoming/not-started rows use watchlist. For 100% complete use completed. Do not include navigation labels, app tabs, phone status text, buttons, or unrelated UI labels.'
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => app_screenshot_image_data_url($path, $mime)]
                    ]
                ]
            ]
        ],
        'max_tokens' => 1800,
    ];
    $json = json_encode($payload);
    if (!is_string($json)) { throw new RuntimeException('Unable to encode AI request.'); }
    $curl = curl_init(app_screenshot_ai_endpoint());
    if ($curl === false) { throw new RuntimeException('Unable to initialize AI vision request.'); }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . app_screenshot_ai_key()],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_TIMEOUT => 45,
    ]);
    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if (!is_string($response) || $response === '') { throw new RuntimeException('AI vision request failed' . ($curlError !== '' ? ': ' . $curlError : '.')); }
    $decodedResponse = json_decode($response, true);
    if (!is_array($decodedResponse)) { throw new RuntimeException('AI vision returned an unreadable response.'); }
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = (string)($decodedResponse['error']['message'] ?? 'AI vision request failed.');
        throw new RuntimeException($message);
    }
    $content = (string)($decodedResponse['choices'][0]['message']['content'] ?? '');
    $decoded = app_screenshot_json_from_ai_text($content);
    $guesses = app_screenshot_guesses_from_ai_json($decoded);
    if ($guesses === []) { throw new RuntimeException('AI vision did not return usable show or movie guesses.'); }
    return ['method' => 'ai-vision', 'raw_text' => (string)($decoded['raw_text'] ?? $content), 'guesses' => $guesses];
}

function app_screenshot_tesseract_text(string $path): string
{
    if (!function_exists('shell_exec') || !function_exists('escapeshellarg')) { return ''; }
    $binary = trim((string)@shell_exec('command -v tesseract 2>/dev/null'));
    if ($binary === '') { return ''; }
    $cmd = escapeshellarg($binary) . ' ' . escapeshellarg($path) . ' stdout --psm 6 2>/dev/null';
    $output = @shell_exec($cmd);
    return is_string($output) ? trim($output) : '';
}

function app_screenshot_process_image_direct(string $path, string $mime): array
{
    $errors = [];
    if (app_screenshot_ai_configured()) {
        try { return app_screenshot_process_image_with_ai($path, $mime); }
        catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }
    $ocrText = app_screenshot_tesseract_text($path);
    if ($ocrText !== '' && function_exists('app_screenshot_parse_text')) {
        $guesses = app_screenshot_parse_text($ocrText);
        if ($guesses !== []) { return ['method' => 'local-ocr', 'raw_text' => $ocrText, 'guesses' => $guesses]; }
        $errors[] = 'Local OCR ran, but no usable guesses were found.';
    }
    if (!app_screenshot_ai_configured()) { $errors[] = 'AI vision is not configured. Add OPENAI_API_KEY_LOCAL to includes/config.local.php or set OPENAI_API_KEY in the environment.'; }
    throw new RuntimeException(implode(' ', array_unique(array_filter($errors))) ?: 'Direct image processing is unavailable on this server.');
}
