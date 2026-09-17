<?php
/** Revision 1.0.0 | 2026-09-17 | Initial event polling API and protected JSON storage. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}
function field(array $input, string $key, int $max, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max || ($required && trim($value) === '')) {
        fail(422, 'Please provide a valid ' . $key . '.');
    }
    return trim($value);
}
function dates(array $input): array {
    $values = $input['dates'] ?? null;
    if (!is_array($values) || count($values) < 1 || count($values) > 60) fail(422, 'Choose 1–60 dates.');
    foreach ($values as $value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) fail(422, 'Invalid date.');
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) fail(422, 'Invalid calendar date.');
    }
    $values = array_values(array_unique($values));
    sort($values);
    return $values;
}
function authorized(string $token, string $hash): bool {
    return $token !== '' && hash_equals($hash, hash('sha256', $token));
}
function publicEvent(array $event): array {
    unset($event['adminHash']);
    foreach ($event['responses'] as &$response) unset($response['tokenHash']);
    unset($response);
    $event['responses'] = array_values($event['responses']);
    return $event;
}

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) fail(405, 'Method not supported.');
$input = [];
if ($method === 'POST') {
    // JSON-only writes and no CORS prevent cross-origin browser form submissions.
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') fail(415, 'Send JSON.');
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) fail(413, 'Request too large.');
    try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { fail(400, 'Invalid JSON.'); }
    if (!is_array($input)) fail(400, 'Invalid request.');
}
$action = $method === 'GET' ? 'get' : ($input['action'] ?? '');
if (!in_array($action, ['get', 'create', 'update', 'vote'], true)) fail(400, 'Unknown action.');
$id = $action === 'create' ? bin2hex(random_bytes(16)) : ($input['id'] ?? $_GET['id'] ?? '');
if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) fail(404, 'Event not found.');
$directory = getenv('AVAILABILITY_DATA_DIR') ?: __DIR__ . '/data';
$lock = null;
$temporary = null;
try {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Create storage failed.');
    // A stable lock file survives atomic event-file replacement.
    $lock = fopen($directory . '/events.lock.php', 'c');
    if ($lock === false || !flock($lock, $method === 'GET' ? LOCK_SH : LOCK_EX)) throw new RuntimeException('Lock failed.');
    $path = $directory . '/' . $id . '.php';
    $prefix = "<?php http_response_code(404); exit; ?>\n";
    $extra = [];
    if ($action === 'create') {
        $adminToken = bin2hex(random_bytes(32));
        $event = ['id' => $id, 'title' => field($input, 'title', 150), 'description' => field($input, 'description', 2000, false),
            'adminName' => field($input, 'adminName', 100), 'dates' => dates($input), 'closed' => false,
            'revision' => 1, 'adminHash' => hash('sha256', $adminToken), 'responses' => [], 'createdAt' => gmdate('c')];
        $extra['adminToken'] = $adminToken;
    } else {
        if (!is_file($path)) fail(404, 'Event not found.');
        $stored = file_get_contents($path);
        if ($stored === false || !str_starts_with($stored, $prefix)) throw new RuntimeException('Invalid storage.');
        $event = json_decode(substr($stored, strlen($prefix)), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($event) || !isset($event['responses'], $event['dates'], $event['adminHash'])) throw new RuntimeException('Invalid event.');
    }
    if ($action === 'update') {
        if (!authorized(field($input, 'adminToken', 64), $event['adminHash'])) fail(403, 'The private admin link is required.');
        if (($input['revision'] ?? null) !== $event['revision']) fail(409, 'Event settings changed. Reload before editing.');
        $event['title'] = field($input, 'title', 150);
        $event['description'] = field($input, 'description', 2000, false);
        $event['adminName'] = field($input, 'adminName', 100);
        $event['dates'] = dates($input);
        if (!is_bool($input['closed'] ?? null)) fail(422, 'Invalid poll status.');
        $event['closed'] = $input['closed'];
        foreach ($event['responses'] as &$response) {
            $response['answers'] = array_intersect_key($response['answers'], array_flip($event['dates']));
        }
        unset($response);
        $event['revision']++;
    }
    if ($action === 'vote') {
        if ($event['closed']) fail(409, 'This poll is closed.');
        if (($input['revision'] ?? null) !== $event['revision']) fail(409, 'The organizer changed the dates. Reload to review them before saving.');
        $name = field($input, 'name', 100);
        $answers = $input['answers'] ?? null;
        if (!is_array($answers) || count($answers) > count($event['dates'])) fail(422, 'Invalid answers.');
        foreach ($answers as $date => $answer) {
            if (!in_array($date, $event['dates'], true) || !in_array($answer, ['yes', 'no'], true)) fail(422, 'Invalid answer.');
        }
        $responseId = field($input, 'responseId', 32, false);
        $token = field($input, 'responseToken', 64, false);
        if ($responseId !== '' && (!isset($event['responses'][$responseId]) || !authorized($token, $event['responses'][$responseId]['tokenHash']))) {
            fail(403, 'Use your original browser or private response link to edit.');
        }
        foreach ($event['responses'] as $existingId => $response) {
            if ($existingId !== $responseId && strcasecmp($response['name'], $name) === 0) fail(409, 'That name has already responded. Use your response link to edit, or add an initial to distinguish yourself.');
        }
        if ($responseId === '') {
            if (count($event['responses']) >= 500) fail(422, 'This event has reached 500 responses.');
            $responseId = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
        }
        $event['responses'][$responseId] = ['id' => $responseId, 'name' => $name, 'answers' => $answers,
            'tokenHash' => hash('sha256', $token), 'updatedAt' => gmdate('c')];
        $extra = ['responseId' => $responseId, 'responseToken' => $token];
    }
    if ($method === 'POST') {
        $event['updatedAt'] = gmdate('c');
        // The temporary file also has a PHP extension and a guard; interrupted writes cannot leak JSON.
        $temporary = $directory . '/tmp-' . bin2hex(random_bytes(16)) . '.php';
        $contents = $prefix . json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) throw new RuntimeException('Save failed.');
        $temporary = null;
    }
    echo json_encode(['event' => publicEvent($event)] + $extra, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Availability: ' . $e->getMessage());
    fail(500, 'Unable to access event storage. Please try again or contact the host administrator.');
} finally {
    if ($temporary !== null && is_file($temporary)) unlink($temporary);
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
