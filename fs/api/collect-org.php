<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$configFile = $root . '/config.local.php';
$legacyConfigFile = dirname($root) . '/FS/config.local.php';
if (!is_file($configFile) && is_file($legacyConfigFile)) {
    $configFile = $legacyConfigFile;
}
$stateFile = $root . '/storage/org-api-state.json';
$snapshotFile = $root . '/storage/org-api-snapshots.json';
$pullLogFile = $root . '/storage/org-pull-log.json';

function orgRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function orgReadJsonFile(string $path, array $fallback): array
{
    if (!is_file($path)) return $fallback;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function orgWriteJsonAtomically(string $path, array $payload): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create storage directory.');
    }
    $temporary = tempnam($directory, 'fs-org-');
    if ($temporary === false) throw new RuntimeException('Unable to create temporary data file.');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to save org tracker data.');
    }
    @chmod($path, 0640);
}

function orgRecordPull(string $path, array $entry): void
{
    try {
        $log = orgReadJsonFile($path, ['entries' => []]);
        if (!isset($log['entries']) || !is_array($log['entries'])) $log['entries'] = [];
        $log['entries'][] = $entry;
        $log['entries'] = array_slice($log['entries'], -500);
        orgWriteJsonAtomically($path, $log);
    } catch (Throwable) {
        // Pull logging must never hide the collector's actual result.
    }
}

function orgEmailDomain(?string $email): string
{
    $email = trim((string) $email);
    $at = strrpos($email, '@');
    if ($at === false) return 'Unknown';
    $domain = strtolower(trim(substr($email, $at + 1)));
    return $domain !== '' ? $domain : 'Unknown';
}

function orgMinimalTicket(array $ticket): array
{
    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'status' => (int) ($ticket['status'] ?? 0),
        'createdAt' => (string) ($ticket['created_at'] ?? ''),
    ];
}

function orgIncrementCount(array &$counts, string $label): void
{
    $label = trim($label) !== '' ? trim($label) : 'Uncategorized';
    $counts[$label] = ($counts[$label] ?? 0) + 1;
}

function orgBoundedCounts(array $counts, int $max = 8): array
{
    arsort($counts, SORT_NUMERIC);
    $bounded = [];
    $other = 0;
    foreach ($counts as $label => $count) {
        if (count($bounded) >= $max) $other += $count;
        else $bounded[$label] = $count;
    }
    if ($other > 0) $bounded['Other'] = $other;
    return $bounded;
}

if (!is_file($configFile)) {
    orgRespond(503, ['ok' => false, 'error' => 'Missing config.local.php. Copy config.local.example.php and add your settings.']);
}

$config = require $configFile;
if (!is_array($config)) orgRespond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$required = ['domain', 'api_key', 'org_agent_ids', 'collector_token'];
foreach ($required as $key) {
    $value = $config[$key] ?? null;
    if ($value === null || $value === '' || (is_string($value) && (str_contains($value, 'REPLACE_') || str_contains($value, 'PASTE_')))) {
        orgRespond(503, ['ok' => false, 'error' => 'Configuration value is missing: ' . $key]);
    }
}

$orgAgentIds = array_values(array_unique(array_filter(
    array_map('intval', (array) $config['org_agent_ids']),
    static fn(int $id): bool => $id > 0
)));
if ($orgAgentIds === []) {
    orgRespond(503, ['ok' => false, 'error' => 'Configuration value org_agent_ids must list at least one positive agent ID.']);
}

$expectedToken = (string) $config['collector_token'];
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ')
    ? substr($authorization, 7)
    : (string) ($_SERVER['HTTP_X_COLLECTOR_TOKEN'] ?? '');
$internallyAuthorized = defined('FRESHSERVICE_INTERNAL_COLLECT') && FRESHSERVICE_INTERNAL_COLLECT === true;
if (!$internallyAuthorized && !hash_equals($expectedToken, $providedToken)) {
    orgRespond(401, ['ok' => false, 'error' => 'Unauthorized collector request.']);
}

$pullStartedAt = new DateTimeImmutable('now', new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York')));

try {
    require_once $root . '/lib/FreshserviceClient.php';
    $timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York'));
    $now = new DateTimeImmutable('now', $timezone);
    $client = new FreshserviceClient((string) $config['domain'], (string) $config['api_key'], (int) ($config['workspace_id'] ?? 0));

    $state = orgReadJsonFile($stateFile, ['lastRun' => null, 'tickets' => []]);
    $previousTickets = isset($state['tickets']) && is_array($state['tickets']) ? $state['tickets'] : [];
    $isInitialRun = empty($state['lastRun']);
    $lastRun = $isInitialRun ? null : new DateTimeImmutable((string) $state['lastRun']);

    $currentTickets = [];
    $newTicketsByOrg = [];
    foreach ($client->listTicketsForAgents($orgAgentIds) as $ticket) {
        $minimal = orgMinimalTicket($ticket);
        if ($minimal['id'] <= 0) continue;
        $id = (string) $minimal['id'];

        // Only tickets new to local state, created since the last pull, count as "new"
        // activity, so restrict the extra per-ticket requester lookup to that small set.
        if (!$isInitialRun && !isset($previousTickets[$id])) {
            $createdAt = $minimal['createdAt'] !== '' ? new DateTimeImmutable($minimal['createdAt']) : null;
            if ($createdAt !== null && $lastRun !== null && $createdAt >= $lastRun) {
                orgIncrementCount($newTicketsByOrg, orgEmailDomain($client->getTicketRequesterEmail($minimal['id'])));
            }
        }
        $currentTickets[$id] = $minimal;
    }

    $snapshots = orgReadJsonFile($snapshotFile, ['entries' => []]);
    if (!isset($snapshots['entries']) || !is_array($snapshots['entries'])) $snapshots['entries'] = [];
    $snapshots['entries'][] = [
        'capturedAt' => $now->format(DateTimeInterface::ATOM),
        'source' => 'freshservice-api-org',
        'note' => $isInitialRun ? 'Org tracker baseline initialized.' : 'Automated org tracker snapshot.',
        'initialRun' => $isInitialRun,
        'activity' => ['newTicketsByOrg' => orgBoundedCounts($newTicketsByOrg)],
    ];

    orgWriteJsonAtomically($stateFile, [
        'lastRun' => $now->format(DateTimeInterface::ATOM),
        'tickets' => $currentTickets,
    ]);
    orgWriteJsonAtomically($snapshotFile, $snapshots);

    orgRecordPull($pullLogFile, [
        'attemptedAt' => $pullStartedAt->format(DateTimeInterface::ATOM),
        'completedAt' => (new DateTimeImmutable('now', $timezone))->format(DateTimeInterface::ATOM),
        'ok' => true,
        'newTickets' => (int) array_sum($newTicketsByOrg),
        'initialRun' => $isInitialRun,
    ]);

    orgRespond(200, [
        'ok' => true,
        'capturedAt' => $now->format(DateTimeInterface::ATOM),
        'newTicketsByOrg' => orgBoundedCounts($newTicketsByOrg),
        'initialRun' => $isInitialRun,
    ]);
} catch (Throwable $exception) {
    orgRecordPull($pullLogFile, [
        'attemptedAt' => $pullStartedAt->format(DateTimeInterface::ATOM),
        'completedAt' => (new DateTimeImmutable('now', $pullStartedAt->getTimezone()))->format(DateTimeInterface::ATOM),
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
    orgRespond(500, ['ok' => false, 'error' => $exception->getMessage()]);
}
