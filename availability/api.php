<?php
/** Revision 1.3.0 | 2026-09-17 | Optional attendee-added date/time options.
 * History: 1.3.0 — Admin-controlled attendee date suggestions; 1.2.0 — Optional hashed passwords for admin access and event/invite access; 1.1.1 — Single attendee name and Unicode-safe matching; 1.1.0 — Private contacts and event planning; 1.0.0 — Initial event polling API and protected JSON storage. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message, array $extra = []): never {
    http_response_code($status);
    echo json_encode(['error' => $message] + $extra);
    exit;
}
function field(array $input, string $key, int $max, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max || ($required && trim($value) === '')) {
        fail(422, 'Please provide a valid ' . $key . '.');
    }
    return trim($value);
}
function boolField(array $input, string $key, bool $default = false): bool {
    if (!array_key_exists($key, $input)) return $default;
    if (!is_bool($input[$key])) fail(422, 'Invalid ' . $key . ' setting.');
    return $input[$key];
}
function passwordField(array $input, string $key): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > 200) fail(422, 'Please provide a valid password.');
    if ($value !== '' && strlen($value) < 4) fail(422, 'Passwords must be at least 4 characters.');
    return $value;
}
function passwordRequired(string $kind, bool $invalid = false): never {
    $label = $kind === 'admin' ? 'admin' : 'event';
    fail(401, $invalid ? 'Incorrect ' . $label . ' password.' : ucfirst($label) . ' password required.', ['passwordRequired' => $kind]);
}
function passwordMatches(array $event, array $input, string $kind): bool {
    $hashKey = $kind === 'admin' ? 'adminPasswordHash' : 'eventPasswordHash';
    $inputKey = $kind === 'admin' ? 'adminPassword' : 'eventPassword';
    $hash = $event[$hashKey] ?? '';
    if ($hash === '') return true;
    $value = $input[$inputKey] ?? '';
    return is_string($value) && $value !== '' && password_verify($value, $hash);
}
function requirePassword(array $event, array $input, string $kind): void {
    $hashKey = $kind === 'admin' ? 'adminPasswordHash' : 'eventPasswordHash';
    if (($event[$hashKey] ?? '') === '') return;
    $inputKey = $kind === 'admin' ? 'adminPassword' : 'eventPassword';
    $supplied = isset($input[$inputKey]) && is_string($input[$inputKey]) && $input[$inputKey] !== '';
    if (!passwordMatches($event, $input, $kind)) passwordRequired($kind, $supplied);
}
function applyPasswordSettings(array $input, array $event, bool $creating = false): array {
    foreach (['admin', 'event'] as $kind) {
        $hashKey = $kind . 'PasswordHash';
        $passwordKey = $creating ? $kind . 'Password' : 'new' . ucfirst($kind) . 'Password';
        $removeKey = 'remove' . ucfirst($kind) . 'Password';
        $password = passwordField($input, $passwordKey);
        $remove = $input[$removeKey] ?? false;
        if (!is_bool($remove)) fail(422, 'Invalid password setting.');
        if ($password !== '' && $remove) fail(422, 'Choose either a new password or remove the existing password.');
        if ($password !== '') $event[$hashKey] = password_hash($password, PASSWORD_DEFAULT);
        elseif ($remove) unset($event[$hashKey]);
        elseif ($creating) unset($event[$hashKey]);
    }
    return $event;
}
function dates(array $input): array {
    $values = $input['dates'] ?? null;
    if (!is_array($values) || count($values) < 1 || count($values) > 60) fail(422, 'Choose 1–60 dates.');
    foreach ($values as $value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(T([01]\d|2[0-3]):[0-5]\d)?$/D', $value)) fail(422, 'Invalid date or time.');
        [$year, $month, $day] = array_map('intval', explode('-', substr($value, 0, 10)));
        if (!checkdate($month, $day, $year)) fail(422, 'Invalid calendar date.');
    }
    $values = array_values(array_unique($values));
    sort($values);
    return $values;
}
function requireUnicode(): void {
    if (!class_exists('Normalizer') || !function_exists('mb_convert_case')) {
        fail(503, 'The host must enable PHP intl and mbstring for name matching before creating polls or saving responses.');
    }
}
function nameKey(string $name): string {
    requireUnicode();
    $normalized = Normalizer::normalize($name, Normalizer::FORM_D);
    if ($normalized === false) fail(422, 'Please provide a valid UTF-8 name.');
    $folded = Normalizer::normalize(mb_convert_case($normalized, MB_CASE_FOLD, 'UTF-8'), Normalizer::FORM_D);
    if ($folded === false) fail(422, 'Please provide a valid UTF-8 name.');
    return $folded;
}
function authorized(string $token, string $hash): bool {
    return $token !== '' && hash_equals($hash, hash('sha256', $token));
}
function expired(array $event): bool {
    return !empty($event['expiresAt']) && strtotime($event['expiresAt']) <= time();
}
function settings(array $input, array $previous = []): array {
    $timezone = field($input + ['timezone' => $previous['timezone'] ?? 'America/New_York'], 'timezone', 80);
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) fail(422, 'Choose a valid time zone.');
    if (!empty($previous['responses']) && $timezone !== ($previous['timezone'] ?? 'America/New_York')) {
        fail(409, 'The time zone cannot change after people respond. Create a new poll for a different time zone.');
    }
    $local = field($input + ['expiresLocal' => $previous['expiresLocal'] ?? ''], 'expiresLocal', 16, false);
    $expires = null;
    if ($local !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone($timezone));
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $local) fail(422, 'Choose a valid voting deadline in the event time zone.');
        $expires = $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
    return ['location' => field($input + ['location' => $previous['location'] ?? ''], 'location', 300, false),
        'timezone' => $timezone, 'expiresLocal' => $local, 'expiresAt' => $expires];
}
function optionalCount(array $input, string $key): ?int {
    $value = $input[$key] ?? null;
    if ($value !== null && (!is_int($value) || $value < 0 || $value > 1000)) fail(422, 'Enter a whole number from 0 to 1000 for ' . $key . '.');
    return $value;
}
function validateTimes(array $event): void {
    $zone = new DateTimeZone($event['timezone'] ?? 'America/New_York');
    foreach ($event['dates'] as $option) {
        if (strlen($option) === 10) continue;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $option, $zone);
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $option) fail(422, 'A proposed time does not exist in this time zone (daylight saving change).');
    }
}
function safeResponse(array $response, bool $private = false): array {
    $keys = ['id', 'name', 'answers', 'adults', 'kids', 'foodType', 'foodNote', 'updatedAt'];
    if ($private) $keys = array_merge($keys, ['phone', 'email']);
    return array_intersect_key($response, array_flip($keys));
}
function publicEvent(array $event): array {
    $public = array_intersect_key($event, array_flip(['id', 'title', 'description', 'adminName', 'dates', 'closed', 'revision', 'createdAt', 'updatedAt', 'location', 'timezone', 'expiresLocal', 'expiresAt', 'allowAttendeeDates']));
    $public['responses'] = array_values(array_map(fn(array $r): array => safeResponse($r), $event['responses']));
    $public['expired'] = expired($event);
    $public['allowAttendeeDates'] = !empty($event['allowAttendeeDates']);
    $public['adminPasswordRequired'] = !empty($event['adminPasswordHash']);
    $public['eventPasswordRequired'] = !empty($event['eventPasswordHash']);
    return $public;
}

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) fail(405, 'Method not supported.');
$input = [];
if ($method === 'POST') {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') fail(415, 'Send JSON.');
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) fail(413, 'Request too large.');
    try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { fail(400, 'Invalid JSON.'); }
    if (!is_array($input)) fail(400, 'Invalid request.');
}
$action = $method === 'GET' ? 'get' : ($input['action'] ?? '');
if ($method === 'GET' && $action !== 'get') fail(400, 'Unknown action.');
if ($method === 'POST' && !in_array($action, ['view', 'create', 'update', 'vote', 'suggest_date'], true)) fail(400, 'Unknown action.');
if (in_array($action, ['create', 'vote'], true)) requireUnicode();
$id = $action === 'create' ? bin2hex(random_bytes(16)) : ($input['id'] ?? $_GET['id'] ?? '');
if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) fail(404, 'Event not found.');

$directory = getenv('AVAILABILITY_DATA_DIR') ?: __DIR__ . '/data';
$lock = null;
$temporary = null;

try {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Create storage failed.');
    $lock = fopen($directory . '/events.lock.php', 'c');
    if ($lock === false || !flock($lock, in_array($action, ['get', 'view'], true) ? LOCK_SH : LOCK_EX)) throw new RuntimeException('Lock failed.');
    $path = $directory . '/' . $id . '.php';
    $prefix = "<?php http_response_code(404); exit; ?>\n";
    $extra = [];
    $adminAccess = false;

    if ($action === 'create') {
        $adminToken = bin2hex(random_bytes(32));
        $event = ['id' => $id, 'title' => field($input, 'title', 150), 'description' => field($input, 'description', 2000, false),
            'adminName' => field($input, 'adminName', 100), 'dates' => dates($input), 'closed' => false,
            'allowAttendeeDates' => boolField($input, 'allowAttendeeDates'),
            'revision' => 1, 'adminHash' => hash('sha256', $adminToken), 'responses' => [], 'createdAt' => gmdate('c')];
        $event = array_merge($event, settings($input));
        $event = applyPasswordSettings($input, $event, true);
        $extra['adminToken'] = $adminToken;
        $adminAccess = true;
    } else {
        if (!is_file($path)) fail(404, 'Event not found.');
        $stored = file_get_contents($path);
        if ($stored === false || !str_starts_with($stored, $prefix)) throw new RuntimeException('Invalid storage.');
        $event = json_decode(substr($stored, strlen($prefix)), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($event) || !isset($event['responses'], $event['dates'], $event['adminHash'])) throw new RuntimeException('Invalid event.');
        if (!array_key_exists('allowAttendeeDates', $event)) $event['allowAttendeeDates'] = false;

        $adminCredential = field($input, 'adminToken', 64, false);
        if ($adminCredential !== '') {
            if (!authorized($adminCredential, $event['adminHash'])) fail(403, 'Invalid admin link for this event.');
            requirePassword($event, $input, 'admin');
            $adminAccess = true;
        }

        if (!$adminAccess && in_array($action, ['get', 'view', 'vote', 'suggest_date'], true)) requirePassword($event, $input, 'event');
    }

    if ($action === 'update') {
        if (!$adminAccess) fail(403, 'The private admin link is required.');
        if (($input['revision'] ?? null) !== $event['revision']) fail(409, 'Event settings changed. Reload before editing.');
        $event = array_merge($event, settings($input, $event));
        $event = applyPasswordSettings($input, $event);
        $event['title'] = field($input, 'title', 150);
        $event['description'] = field($input, 'description', 2000, false);
        $event['adminName'] = field($input, 'adminName', 100);
        $event['dates'] = dates($input);
        $event['allowAttendeeDates'] = boolField($input, 'allowAttendeeDates', !empty($event['allowAttendeeDates']));
        if (!is_bool($input['closed'] ?? null)) fail(422, 'Invalid poll status.');
        $event['closed'] = $input['closed'];
        foreach ($event['responses'] as &$response) $response['answers'] = array_intersect_key($response['answers'], array_flip($event['dates']));
        unset($response);
        $event['revision']++;
    }

    if ($action === 'suggest_date') {
        if ($event['closed'] || expired($event)) fail(409, expired($event) ? 'Voting has expired. New date options cannot be added.' : 'This poll is closed.');
        if (empty($event['allowAttendeeDates']) && !$adminAccess) fail(403, 'The organizer has not enabled attendee-added dates.');
        if (($input['revision'] ?? null) !== $event['revision']) fail(409, 'The event changed. Reload before adding another date.');
        if (count($event['dates']) >= 60) fail(422, 'This poll already has the maximum of 60 date/time options.');
        $candidate = dates(['dates' => [field($input, 'date', 16)]])[0];
        if (in_array($candidate, $event['dates'], true)) fail(409, 'That date/time is already an option.');
        $event['dates'][] = $candidate;
        sort($event['dates']);
        $event['revision']++;
    }

    if ($action === 'vote') {
        if ($event['closed'] || expired($event)) fail(409, expired($event) ? 'Voting has expired. Results remain visible.' : 'This poll is closed.');
        if (($input['revision'] ?? null) !== $event['revision']) fail(409, 'The organizer changed the dates. Reload to review them before saving.');
        $name = field($input, 'name', 100);
        $key = nameKey($name);
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
        $detailInput = $input + ($event['responses'][$responseId] ?? []);
        $email = field($detailInput, 'email', 254, false);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) fail(422, 'Enter a valid email address.');
        $details = ['phone' => field($detailInput, 'phone', 50, false), 'email' => $email,
            'adults' => optionalCount($detailInput, 'adults'), 'kids' => optionalCount($detailInput, 'kids'),
            'foodType' => field($detailInput, 'foodType', 30, false), 'foodNote' => field($detailInput, 'foodNote', 300, false)];
        if (!in_array($details['foodType'], ['', 'Main dish', 'Side dish', 'Dessert', 'Snack', 'Drinks', 'Other', 'Not bringing food'], true)) fail(422, 'Choose a valid food type.');
        foreach ($event['responses'] as $existingId => $response) {
            if ($existingId !== $responseId && nameKey($response['name']) === $key) fail(409, 'That name has already responded. Use your response link to edit, or add an initial to distinguish yourself.');
        }
        if ($responseId === '') {
            if (count($event['responses']) >= 500) fail(422, 'This event has reached 500 responses.');
            $responseId = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
        }
        $event['responses'][$responseId] = ['id' => $responseId, 'name' => $name, 'answers' => $answers,
            'tokenHash' => hash('sha256', $token), 'updatedAt' => gmdate('c')] + $details;
        $extra = ['responseId' => $responseId, 'responseToken' => $token];
    }

    if ($adminAccess) $extra['adminResponses'] = array_values(array_map(fn(array $r): array => safeResponse($r, true), $event['responses']));

    $ownId = $extra['responseId'] ?? field($input, 'responseId', 32, false);
    $ownToken = $extra['responseToken'] ?? field($input, 'responseToken', 64, false);
    if ($ownId !== '' || $ownToken !== '') {
        if (!isset($event['responses'][$ownId]) || !authorized($ownToken, $event['responses'][$ownId]['tokenHash'])) fail(403, 'Invalid private response link.');
        $extra['myResponse'] = safeResponse($event['responses'][$ownId], true);
    }

    if (in_array($action, ['create', 'update', 'vote', 'suggest_date'], true)) {
        validateTimes($event);
        $event['updatedAt'] = gmdate('c');
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
