<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const MAX_TAGS = 100;

$dataDirectory = __DIR__ . '/data';
$dataFile = $dataDirectory . '/service-tags.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function readDataFromHandle($handle): array
{
    rewind($handle);
    $contents = stream_get_contents($handle);
    $decoded = $contents ? json_decode($contents, true) : null;

    if (!is_array($decoded) || !isset($decoded['tags']) || !is_array($decoded['tags'])) {
        return ['updatedAt' => null, 'tags' => []];
    }

    return $decoded;
}

if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0755, true) && !is_dir($dataDirectory)) {
    respond(500, ['error' => 'Unable to create the data directory.']);
}

$handle = fopen($dataFile, 'c+');
if ($handle === false) {
    respond(500, ['error' => 'Unable to open the history file.']);
}

if ($method === 'GET') {
    if (!flock($handle, LOCK_SH)) {
        fclose($handle);
        respond(500, ['error' => 'Unable to read history.']);
    }

    $data = readDataFromHandle($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    respond(200, $data);
}

if ($method !== 'POST') {
    fclose($handle);
    header('Allow: GET, POST');
    respond(405, ['error' => 'Method not allowed.']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || strlen($rawBody) > 1024) {
    fclose($handle);
    respond(400, ['error' => 'Invalid request body.']);
}

$request = json_decode($rawBody, true);
$tag = strtoupper(trim((string) ($request['tag'] ?? '')));

if (!preg_match('/^[A-Z0-9]{5,20}$/', $tag)) {
    fclose($handle);
    respond(422, ['error' => 'A valid service tag is required.']);
}

if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    respond(500, ['error' => 'Unable to update history.']);
}

$data = readDataFromHandle($handle);
$tags = array_values(array_filter(
    $data['tags'],
    static fn($item): bool => is_array($item) && ($item['tag'] ?? null) !== $tag
));

array_unshift($tags, [
    'tag' => $tag,
    'addedAt' => gmdate('c'),
]);

$data = [
    'updatedAt' => gmdate('c'),
    'tags' => array_slice($tags, 0, MAX_TAGS),
];

$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
rewind($handle);
ftruncate($handle, 0);
$written = $json !== false ? fwrite($handle, $json . PHP_EOL) : false;
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($written === false) {
    respond(500, ['error' => 'Unable to save history.']);
}

respond(201, $data);
