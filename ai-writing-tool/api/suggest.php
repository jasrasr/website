<?php
/*
    Project      : AI Writing Tool
    File         : api/suggest.php
    Revision     : 1.1.0
    Created      : 2026-06-01
    Updated      : 2026-06-02
    Description  : Server-side API proxy that sends draft text to the OpenAI Responses API. Supports writing-review and project-insight modes.

    Important:
    - Do not put your API key in JavaScript.
    - Copy config/config.example.php to config/config.php and add your real key there.
*/

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(['error' => 'POST requests only.'], 405);
}

$configPath = dirname(__DIR__) . '/config/config.php';

if (!is_file($configPath)) {
    respondJson(['error' => 'Missing config/config.php. Copy config.example.php to config.php and add your API key.'], 500);
}

$config = require $configPath;

$apiKey = trim((string)($config['openai_api_key'] ?? ''));
$model = trim((string)($config['openai_model'] ?? 'gpt-4.1-mini'));
$maxInputCharacters = (int)($config['max_input_characters'] ?? 12000);
$rateLimitPerHour = (int)($config['rate_limit_per_hour'] ?? 60);
$timeoutSeconds = (int)($config['timeout_seconds'] ?? 45);

if ($apiKey === '' || $apiKey === 'PASTE-YOUR-OPENAI-API-KEY-HERE') {
    respondJson(['error' => 'OpenAI API key is not configured. Edit config/config.php.'], 500);
}

if (!extension_loaded('curl')) {
    respondJson(['error' => 'PHP cURL extension is required. Enable cURL in hosting PHP settings.'], 500);
}

applyBasicRateLimit($rateLimitPerHour);

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    respondJson(['error' => 'Invalid JSON request body.'], 400);
}

$text = trim((string)($payload['text'] ?? ''));
$reviewMode = sanitizeOption((string)($payload['reviewMode'] ?? 'balanced'), [
    'balanced',
    'grammar',
    'clarity',
    'professional',
    'concise',
    'friendly',
    'brain_dump',
    'task_breakdown',
    'technical_advisor',
    'sharpening_questions',
    'risks_gotchas'
], 'balanced');
$outputType = sanitizeOption((string)($payload['outputType'] ?? 'suggestions'), [
    'suggestions',
    'rewrite',
    'outline',
    'questions'
], 'suggestions');

if ($text === '') {
    respondJson(['error' => 'Text is required.'], 400);
}

if (mb_strlen($text) > $maxInputCharacters) {
    respondJson(['error' => "Draft is too long. Limit is {$maxInputCharacters} characters."], 413);
}

$instructions = buildInstructions($reviewMode, $outputType);
$userInput = buildUserInput($text, $reviewMode, $outputType);

$requestPayload = [
    'model' => $model,
    'instructions' => $instructions,
    'input' => $userInput
];

$result = callOpenAiResponsesApi($apiKey, $requestPayload, $timeoutSeconds);
$suggestions = extractOutputText($result);

if ($suggestions === '') {
    respondJson(['error' => 'OpenAI returned no readable text.'], 502);
}

$usage = $result['usage'] ?? [];

respondJson([
    'suggestions' => $suggestions,
    'model' => $model,
    'reviewMode' => $reviewMode,
    'outputType' => $outputType,
    'usage' => [
        'input_tokens' => (int)($usage['input_tokens'] ?? 0),
        'output_tokens' => (int)($usage['output_tokens'] ?? 0),
        'total_tokens' => (int)($usage['total_tokens'] ?? 0)
    ]
]);

function buildInstructions(string $reviewMode, string $outputType): string
{
    $writingModes = ['balanced', 'grammar', 'clarity', 'professional', 'concise', 'friendly'];
    $insightModes = ['brain_dump', 'task_breakdown', 'technical_advisor', 'sharpening_questions', 'risks_gotchas'];

    $modeInstructions = [
        // Writing review modes
        'balanced' => 'Review for grammar, clarity, structure, and usefulness.',
        'grammar' => 'Focus only on spelling, grammar, punctuation, and obvious wording issues.',
        'clarity' => 'Focus on making the writing easier to understand without changing the core meaning.',
        'professional' => 'Focus on making the writing polished, direct, and professional.',
        'concise' => 'Focus on removing unnecessary words while preserving meaning.',
        'friendly' => 'Focus on making the writing warmer and more approachable without becoming goofy.',

        // Project insight modes
        'brain_dump' => 'Treat the input as a project brain dump or work notes. Surface useful things the writer may have forgotten, relevant tips, common risks for this kind of work, and 1-2 sharpening questions. Adapt to the topic (technical, business, personal, IT, etc).',
        'task_breakdown' => 'Treat the input as a task or work item. Suggest concrete subtasks, hidden dependencies, prerequisites, and risks. Be specific to the domain mentioned.',
        'technical_advisor' => 'Treat the input as technical or IT-related notes (servers, Active Directory, Microsoft 365, PowerShell, networking, security, scripting, etc). Offer best practices, security considerations, and common gotchas relevant to the specific technology mentioned. Cite specific cmdlets, commands, or settings when useful.',
        'sharpening_questions' => 'Ask 3-5 focused Socratic questions that challenge assumptions, clarify intent, or expose gaps in thinking. No suggestions or advice. Questions only.',
        'risks_gotchas' => 'Identify the most likely things that could go wrong with what is described. Cover failure modes, security implications, performance pitfalls, edge cases, and operational risks. Be specific to the domain.'
    ];

    $outputInstructions = [
        'suggestions' => 'Return a concise numbered list of practical items. Include a short example only where useful.',
        'rewrite' => 'Return a clean rewritten version first, then a short list of what changed.',
        'outline' => 'Return a structured outline based on the input.',
        'questions' => 'Return the most important questions the writer should answer to improve the input.'
    ];

    $isWritingMode = in_array($reviewMode, $writingModes, true);
    $isInsightMode = in_array($reviewMode, $insightModes, true);

    if ($isInsightMode) {
        $basePrompt = "You are a thinking partner for a browser-based notes tool. " .
            "The user is jotting project notes, tasks, or technical work, not drafting prose. " .
            "Be direct, accurate, and practical. Do not invent facts. ";
    } else {
        $basePrompt = "You are an editing assistant for a browser-based writing tool. " .
            "Be direct, accurate, and practical. Do not invent facts. ";
    }

    return $basePrompt .
        ($modeInstructions[$reviewMode] ?? $modeInstructions['balanced']) . ' ' .
        ($outputInstructions[$outputType] ?? $outputInstructions['suggestions']) . ' ' .
        "Avoid markdown tables. Keep the response useful and compact.";
}

function buildUserInput(string $text, string $reviewMode, string $outputType): string
{
    return "Review mode : {$reviewMode}\n" .
        "Output type : {$outputType}\n\n" .
        "Draft text:\n" .
        $text;
}

function callOpenAiResponsesApi(string $apiKey, array $requestPayload, int $timeoutSeconds): array
{
    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => $timeoutSeconds
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        respondJson(['error' => 'cURL error : ' . $curlError], 502);
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        respondJson(['error' => 'OpenAI returned invalid JSON.'], 502);
    }

    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = $decoded['error']['message'] ?? 'OpenAI API request failed.';
        respondJson(['error' => $message], $httpStatus);
    }

    return $decoded;
}

function extractOutputText(array $result): string
{
    if (isset($result['output_text']) && is_string($result['output_text'])) {
        return trim($result['output_text']);
    }

    $parts = [];

    foreach (($result['output'] ?? []) as $outputItem) {
        foreach (($outputItem['content'] ?? []) as $contentItem) {
            if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                $parts[] = (string)$contentItem['text'];
            }
        }
    }

    return trim(implode("\n", $parts));
}

function sanitizeOption(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function applyBasicRateLimit(int $limitPerHour): void
{
    if ($limitPerHour <= 0) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $safeIpHash = hash('sha256', $ip);
    $folder = dirname(__DIR__) . '/data/rate-limit';

    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }

    if (!is_writable($folder)) {
        return;
    }

    $file = $folder . '/' . $safeIpHash . '.json';
    $now = time();
    $windowStart = $now - 3600;
    $timestamps = [];

    if (is_file($file)) {
        $existing = json_decode((string)file_get_contents($file), true);
        if (is_array($existing)) {
            $timestamps = array_values(array_filter($existing, static function ($timestamp) use ($windowStart) {
                return is_int($timestamp) && $timestamp >= $windowStart;
            }));
        }
    }

    if (count($timestamps) >= $limitPerHour) {
        respondJson(['error' => 'Rate limit reached. Try again later.'], 429);
    }

    $timestamps[] = $now;
    file_put_contents($file, json_encode($timestamps, JSON_PRETTY_PRINT));
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
