<?php
/**
 * File: includes/vision.php
 * Project: TV Binge Board
 * Description: Optional server-side vision extraction helpers for screenshot-assisted imports using an OpenAI-compatible chat-completions vision endpoint.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/tmdb.php';

function app_vision_endpoint(): string
{
    return defined('APP_VISION_ENDPOINT_LOCAL') ? trim((string)constant('APP_VISION_ENDPOINT_LOCAL')) : '';
}

function app_vision_token(): string
{
    return defined('APP_VISION_TOKEN_LOCAL') ? trim((string)constant('APP_VISION_TOKEN_LOCAL')) : '';
}

function app_vision_model(): string
{
    if (defined('APP_VISION_MODEL_LOCAL')) {
        $model = trim((string)constant('APP_VISION_MODEL_LOCAL'));
        if ($model !== '') { return $model; }
    }
    return 'gpt-5.5';
}

function app_vision_configured(): bool
{
    return app_vision_endpoint() !== '' && app_vision_token() !== '';
}

function app_vision_prompt(): string
{
    return <<<'PROMPT'
You are extracting watch-history import data from a screenshot of a TV/movie tracking app.
Return JSON only. Do not include markdown.

Extract visible shows, movies, seasons, episodes, status, and progress evidence.
Support apps such as TV Time, Trakt, IMDb, Letterboxd, Serializd, and similar trackers.

Return this exact shape:
{
  "source_app": "TV Time or unknown",
  "screen_type": "tv_detail|watchlist_grid|movie_detail|unknown",
  "items": [
    {
      "type": "tv|movie",
      "title": "visible title",
      "year": "optional year",
      "status": "watchlist|watching|watched|completed|dropped",
      "season": 1,
      "episode": 8,
      "episode_title": "optional episode title",
      "watched": true,
      "completion_status": "planned|in_progress|complete|unknown",
      "progress_percent": 99,
      "watched_count": 7,
      "total_count": 13,
      "source_section": "Continue tracking / Watch Next / Haven't watched for a while / etc.",
      "source_text": "short evidence visible in screenshot",
      "confidence": 0.0
    }
  ]
}

Rules:
- For TV detail screens, create one item for the show and prefer the current/next episode if visible.
- If specific episode rows are visible, include episode number/title and watched true/false when clear.
- Green checked episodes are watched. White/gray unchecked episodes are not watched or next-up.
- Section labels like Watch Next or Continue tracking imply status watching and completion_status in_progress.
- Upcoming implies watchlist/planned.
- 100% or all episodes checked implies completed.
- If a grid poster title is visible but episode details are not, still return the show/movie with lower confidence.
- Use null for unknown season/episode counts, not 0.
- Confidence must be a number from 0 to 1.
PROMPT;
}

function app_vision_json_from_text(string $text): array
{
    $text = trim($text);
    if ($text === '') { return []; }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) { return $decoded; }
    if (preg_match('/```(?:json)?\s*(.*?)```/is', $text, $match)) {
        $decoded = json_decode(trim($match[1]), true);
        if (is_array($decoded)) { return $decoded; }
    }
    $first = strpos($text, '{');
    $last = strrpos($text, '}');
    if ($first !== false && $last !== false && $last > $first) {
        $decoded = json_decode(substr($text, $first, $last - $first + 1), true);
        if (is_array($decoded)) { return $decoded; }
    }
    return [];
}

function app_vision_extract_message_text(array $response): string
{
    if (isset($response['choices'][0]['message']['content'])) {
        return trim((string)$response['choices'][0]['message']['content']);
    }
    if (isset($response['output_text'])) {
        return trim((string)$response['output_text']);
    }
    $parts = [];
    foreach (($response['output'] ?? []) as $output) {
        if (!is_array($output)) { continue; }
        foreach (($output['content'] ?? []) as $content) {
            if (is_array($content) && isset($content['text'])) { $parts[] = (string)$content['text']; }
        }
    }
    return trim(implode("\n", $parts));
}

function app_vision_request(string $imagePath, string $mime): array
{
    if (!app_vision_configured()) { throw new RuntimeException('Image AI extraction is not configured. Add APP_VISION_ENDPOINT_LOCAL and APP_VISION_TOKEN_LOCAL to includes/config.local.php.'); }
    if (!is_file($imagePath) || !is_readable($imagePath)) { throw new RuntimeException('Uploaded screenshot is not readable.'); }
    $bytes = file_get_contents($imagePath);
    if ($bytes === false || $bytes === '') { throw new RuntimeException('Unable to read uploaded screenshot.'); }
    $payload = [
        'model' => app_vision_model(),
        'messages' => [
            ['role' => 'system', 'content' => 'You extract structured TV and movie tracking data from screenshots. Return strict JSON only.'],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => app_vision_prompt()],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($bytes), 'detail' => 'high']],
                ],
            ],
        ],
        'response_format' => ['type' => 'json_object'],
        'max_completion_tokens' => 2500,
    ];
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . app_vision_token()];
    $ch = curl_init(app_vision_endpoint());
    if ($ch === false) { throw new RuntimeException('Unable to initialize image AI request.'); }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($body === false || $body === '') { throw new RuntimeException('Image AI request failed: ' . ($curlError !== '' ? $curlError : 'empty response')); }
    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) { throw new RuntimeException('Image AI response was not valid JSON.'); }
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = (string)($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
        throw new RuntimeException('Image AI request failed: ' . $message);
    }
    return $decoded;
}

function app_vision_item_to_guess(array $item, int $index, string $sourceApp = '', string $screenType = ''): ?array
{
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') { return null; }
    $type = strtolower(trim((string)($item['type'] ?? '')));
    $type = in_array($type, ['movie', 'tv'], true) ? $type : 'tv';
    $status = strtolower(trim((string)($item['status'] ?? '')));
    if (!array_key_exists($status, app_statuses())) {
        $completion = strtolower((string)($item['completion_status'] ?? ''));
        $status = match ($completion) {
            'complete', 'completed' => 'completed',
            'planned' => 'watchlist',
            'in_progress' => 'watching',
            default => $type === 'tv' ? 'watching' : 'watchlist',
        };
    }
    $confidenceRaw = $item['confidence'] ?? 0.7;
    $confidence = is_numeric($confidenceRaw) ? (float)$confidenceRaw : 0.7;
    if ($confidence <= 1) { $confidence *= 100; }
    $confidence = max(10, min(99, (int)round($confidence)));
    $season = isset($item['season']) && is_numeric($item['season']) ? max(0, (int)$item['season']) : null;
    $episode = isset($item['episode']) && is_numeric($item['episode']) ? max(0, (int)$item['episode']) : null;
    $notes = ['Parsed from uploaded screenshot image.'];
    if ($sourceApp !== '') { $notes[] = 'Source app: ' . $sourceApp . '.'; }
    if ($screenType !== '') { $notes[] = 'Screen: ' . $screenType . '.'; }
    if (!empty($item['source_section'])) { $notes[] = 'Section: ' . (string)$item['source_section'] . '.'; }
    if (!empty($item['episode_title'])) { $notes[] = 'Episode title: ' . (string)$item['episode_title'] . '.'; }
    if (isset($item['watched_count'], $item['total_count'])) { $notes[] = 'Progress: ' . (string)$item['watched_count'] . '/' . (string)$item['total_count'] . '.'; }
    if (isset($item['progress_percent']) && is_numeric($item['progress_percent'])) { $notes[] = 'Progress percent: ' . (string)$item['progress_percent'] . '%.'; }
    return [
        'id' => 'image-' . $index,
        'source_text' => trim((string)($item['source_text'] ?? $title)),
        'title' => $title,
        'type' => $type,
        'year' => trim((string)($item['year'] ?? '')),
        'status' => $status,
        'season' => $season,
        'episode' => $episode,
        'notes' => implode(' ', $notes),
        'confidence' => $confidence,
        'decision' => $confidence >= 70 ? 'approve' : 'review',
        'vision_raw' => $item,
    ];
}

function app_vision_guesses_from_image(string $imagePath, string $mime): array
{
    $response = app_vision_request($imagePath, $mime);
    $text = app_vision_extract_message_text($response);
    $parsed = app_vision_json_from_text($text);
    if ($parsed === []) { throw new RuntimeException('Image AI response did not contain parseable JSON.'); }
    $items = is_array($parsed['items'] ?? null) ? $parsed['items'] : [];
    if ($items === []) { throw new RuntimeException('Image AI did not find any importable shows or movies in this screenshot.'); }
    $sourceApp = trim((string)($parsed['source_app'] ?? ''));
    $screenType = trim((string)($parsed['screen_type'] ?? ''));
    $guesses = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) { continue; }
        $guess = app_vision_item_to_guess($item, (int)$index + 1, $sourceApp, $screenType);
        if ($guess !== null) { $guesses[] = $guess; }
    }
    if ($guesses === []) { throw new RuntimeException('Image AI response did not produce usable import guesses.'); }
    return ['guesses' => $guesses, 'raw_text' => $text, 'raw_json' => $parsed];
}
