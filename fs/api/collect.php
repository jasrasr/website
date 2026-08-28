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
$stateFile = $root . '/storage/api-state.json';
$snapshotFile = $root . '/storage/api-snapshots.json';
$pullLogFile = $root . '/storage/pull-log.json';

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function readJsonFile(string $path, array $fallback): array
{
    if (!is_file($path)) return $fallback;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function writeJsonAtomically(string $path, array $payload): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create storage directory.');
    }
    $temporary = tempnam($directory, 'fs-');
    if ($temporary === false) throw new RuntimeException('Unable to create temporary data file.');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to save tracker data.');
    }
    @chmod($path, 0640);
}

function recordPull(string $path, array $entry): void
{
    try {
        $log = readJsonFile($path, ['entries' => []]);
        if (!isset($log['entries']) || !is_array($log['entries'])) $log['entries'] = [];
        $log['entries'][] = $entry;
        $log['entries'] = array_slice($log['entries'], -500);
        writeJsonAtomically($path, $log);
    } catch (Throwable) {
        // Pull logging must never hide the collector's actual result.
    }
}

function minimalTicket(array $ticket): array
{
    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'status' => (int) ($ticket['status'] ?? 0),
        'responderId' => isset($ticket['responder_id']) ? (int) $ticket['responder_id'] : null,
        'requesterId' => isset($ticket['requester_id']) ? (int) $ticket['requester_id'] : null,
        'priority' => (int) ($ticket['priority'] ?? 0),
        'category' => trim((string) ($ticket['category'] ?? '')),
        'subCategory' => trim((string) ($ticket['sub_category'] ?? '')),
        'itemCategory' => trim((string) ($ticket['item_category'] ?? '')),
        'type' => trim((string) ($ticket['type'] ?? '')),
        'source' => (int) ($ticket['source'] ?? 0),
        'createdAt' => (string) ($ticket['created_at'] ?? ''),
        'updatedAt' => (string) ($ticket['updated_at'] ?? ''),
    ];
}

function incrementCount(array &$counts, string $label): void
{
    $label = trim($label) !== '' ? trim($label) : 'Uncategorized';
    $counts[$label] = ($counts[$label] ?? 0) + 1;
}

function sortedCounts(array $counts): array
{
    arsort($counts, SORT_NUMERIC);
    return $counts;
}

function privacySafeCategoryCounts(array $counts): array
{
    $safe = [];
    $other = 0;
    foreach (sortedCounts($counts) as $label => $count) {
        if ($count < 3 || count($safe) >= 8) $other += $count;
        else $safe[$label] = $count;
    }
    if ($other > 0) $safe['Other'] = $other;
    return $safe;
}

function aggregateAnalytics(array $tickets, array $closedStatuses, DateTimeImmutable $now): array
{
    $statusLabels = [2 => 'Open', 3 => 'Pending', 4 => 'Resolved', 5 => 'Closed'];
    $priorityLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
    $statusCounts = [];
    $categoryCounts = [];
    $priorityCounts = [];
    $ageCounts = ['0–2 days' => 0, '3–7 days' => 0, '8–30 days' => 0, '31–90 days' => 0, '91+ days' => 0];
    $requesterLoads = [];

    foreach ($tickets as $ticket) {
        $status = (int) ($ticket['status'] ?? 0);
        if (in_array($status, $closedStatuses, true)) continue;

        incrementCount($statusCounts, $statusLabels[$status] ?? 'Status ' . $status);
        incrementCount($categoryCounts, (string) ($ticket['category'] ?? ''));
        $priority = (int) ($ticket['priority'] ?? 0);
        incrementCount($priorityCounts, $priorityLabels[$priority] ?? 'Priority ' . $priority);

        try {
            $createdAt = new DateTimeImmutable((string) ($ticket['createdAt'] ?? ''));
            $ageDays = max(0, (int) $createdAt->diff($now)->format('%a'));
            if ($ageDays <= 2) $ageCounts['0–2 days']++;
            elseif ($ageDays <= 7) $ageCounts['3–7 days']++;
            elseif ($ageDays <= 30) $ageCounts['8–30 days']++;
            elseif ($ageDays <= 90) $ageCounts['31–90 days']++;
            else $ageCounts['91+ days']++;
        } catch (Throwable) {
            // Ignore invalid creation timestamps in age analytics.
        }

        $requesterId = (int) ($ticket['requesterId'] ?? 0);
        if ($requesterId > 0) $requesterLoads[$requesterId] = ($requesterLoads[$requesterId] ?? 0) + 1;
    }

    $requesterDistribution = ['1 ticket' => 0, '2 tickets' => 0, '3 tickets' => 0, '4 tickets' => 0, '5+ tickets' => 0];
    foreach ($requesterLoads as $ticketCount) {
        $bucket = $ticketCount >= 5 ? '5+ tickets' : $ticketCount . ($ticketCount === 1 ? ' ticket' : ' tickets');
        $requesterDistribution[$bucket]++;
    }

    return [
        'status' => sortedCounts($statusCounts),
        'category' => privacySafeCategoryCounts($categoryCounts),
        'priority' => sortedCounts($priorityCounts),
        'age' => array_filter($ageCounts, static fn(int $count): bool => $count > 0),
        'requesterDistribution' => array_filter($requesterDistribution, static fn(int $count): bool => $count > 0),
    ];
}

if (!is_file($configFile)) {
    respond(503, ['ok' => false, 'error' => 'Missing config.local.php. Copy config.local.example.php and add your settings.']);
}

$config = require $configFile;
if (!is_array($config)) respond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$required = ['domain', 'api_key', 'agent_id', 'collector_token'];
foreach ($required as $key) {
    if (!isset($config[$key]) || $config[$key] === '' || str_contains((string) $config[$key], 'REPLACE_') || str_contains((string) $config[$key], 'PASTE_')) {
        respond(503, ['ok' => false, 'error' => 'Configuration value is missing: ' . $key]);
    }
}

$expectedToken = (string) $config['collector_token'];
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ')
    ? substr($authorization, 7)
    : (string) ($_SERVER['HTTP_X_COLLECTOR_TOKEN'] ?? '');
if (!hash_equals($expectedToken, $providedToken)) {
    respond(401, ['ok' => false, 'error' => 'Unauthorized collector request.']);
}

$pullStartedAt = new DateTimeImmutable('now', new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York')));

try {
    require_once $root . '/lib/FreshserviceClient.php';
    $timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York'));
    $now = new DateTimeImmutable('now', $timezone);
    $closedStatuses = array_map('intval', (array) ($config['resolved_status_ids'] ?? [4, 5]));
    $agentId = (int) $config['agent_id'];
    $client = new FreshserviceClient((string) $config['domain'], (string) $config['api_key'], (int) ($config['workspace_id'] ?? 0));

    $state = readJsonFile($stateFile, ['lastRun' => null, 'tickets' => []]);
    $previousTickets = isset($state['tickets']) && is_array($state['tickets']) ? $state['tickets'] : [];
    $isInitialRun = empty($state['lastRun']);
    $lastRun = $isInitialRun ? null : new DateTimeImmutable((string) $state['lastRun']);

    $currentTickets = [];
    foreach ($client->listTicketsForAgent($agentId) as $ticket) {
        $minimal = minimalTicket($ticket);
        if ($minimal['id'] > 0) $currentTickets[(string) $minimal['id']] = $minimal;
    }

    $activity = [
        'enteredUnresolved' => 0,
        'exitedUnresolved' => 0,
        'newTickets' => 0,
        'assignedIn' => 0,
        'reopened' => 0,
        'resolved' => 0,
        'closed' => 0,
        'reassignedAway' => 0,
        'otherExit' => 0,
    ];
    $startingUnresolved = 0;
    foreach ($previousTickets as $ticket) {
        if (!in_array((int) ($ticket['status'] ?? 0), $closedStatuses, true)) $startingUnresolved++;
    }

    if (!$isInitialRun) {
        foreach ($currentTickets as $id => $ticket) {
            $isUnresolved = !in_array((int) $ticket['status'], $closedStatuses, true);
            $previous = $previousTickets[$id] ?? null;
            if ($previous === null) {
                if ($isUnresolved) {
                    $activity['enteredUnresolved']++;
                    $createdAt = $ticket['createdAt'] !== '' ? new DateTimeImmutable($ticket['createdAt']) : null;
                    if ($createdAt !== null && $lastRun !== null && $createdAt >= $lastRun) $activity['newTickets']++;
                    else $activity['assignedIn']++;
                }
                continue;
            }

            $wasUnresolved = !in_array((int) ($previous['status'] ?? 0), $closedStatuses, true);
            if (!$wasUnresolved && $isUnresolved) {
                $activity['enteredUnresolved']++;
                $activity['reopened']++;
            } elseif ($wasUnresolved && !$isUnresolved) {
                $activity['exitedUnresolved']++;
                if ((int) $ticket['status'] === 4) $activity['resolved']++;
                elseif ((int) $ticket['status'] === 5) $activity['closed']++;
                else $activity['otherExit']++;
            }
        }

        foreach ($previousTickets as $id => $previous) {
            if (isset($currentTickets[$id])) continue;
            $wasUnresolved = !in_array((int) ($previous['status'] ?? 0), $closedStatuses, true);
            if (!$wasUnresolved) continue;

            $latest = $client->getTicket((int) $id);
            $activity['exitedUnresolved']++;
            if ($latest === null) {
                $activity['otherExit']++;
            } elseif ((int) ($latest['responder_id'] ?? 0) !== $agentId) {
                $activity['reassignedAway']++;
            } elseif ((int) ($latest['status'] ?? 0) === 4) {
                $activity['resolved']++;
            } elseif ((int) ($latest['status'] ?? 0) === 5) {
                $activity['closed']++;
            } else {
                $activity['otherExit']++;
            }
        }
    }

    $endingUnresolved = 0;
    foreach ($currentTickets as $ticket) {
        if (!in_array((int) $ticket['status'], $closedStatuses, true)) $endingUnresolved++;
    }
    if ($isInitialRun) $startingUnresolved = $endingUnresolved;
    $analytics = aggregateAnalytics($currentTickets, $closedStatuses, $now);

    $snapshots = readJsonFile($snapshotFile, ['entries' => []]);
    if (!isset($snapshots['entries']) || !is_array($snapshots['entries'])) $snapshots['entries'] = [];
    $snapshots['entries'][] = [
        'capturedAt' => $now->format(DateTimeInterface::ATOM),
        'unresolved' => $endingUnresolved,
        'source' => 'freshservice-api',
        'recordedAt' => $now->format(DateTimeInterface::ATOM),
        'note' => $isInitialRun ? 'Freshservice API baseline initialized.' : 'Automated Freshservice API snapshot.',
        'activity' => $activity,
        'analytics' => $analytics,
        'startingUnresolved' => $startingUnresolved,
    ];

    writeJsonAtomically($stateFile, [
        'lastRun' => $now->format(DateTimeInterface::ATOM),
        'tickets' => $currentTickets,
    ]);
    writeJsonAtomically($snapshotFile, $snapshots);

    recordPull($pullLogFile, [
        'attemptedAt' => $pullStartedAt->format(DateTimeInterface::ATOM),
        'completedAt' => (new DateTimeImmutable('now', $timezone))->format(DateTimeInterface::ATOM),
        'ok' => true,
        'startingUnresolved' => $startingUnresolved,
        'endingUnresolved' => $endingUnresolved,
        'netChange' => $endingUnresolved - $startingUnresolved,
        'initialRun' => $isInitialRun,
        'activity' => $activity,
    ]);

    respond(200, [
        'ok' => true,
        'capturedAt' => $now->format(DateTimeInterface::ATOM),
        'startingUnresolved' => $startingUnresolved,
        'endingUnresolved' => $endingUnresolved,
        'netChange' => $endingUnresolved - $startingUnresolved,
        'activity' => $activity,
        'initialRun' => $isInitialRun,
    ]);
} catch (Throwable $exception) {
    recordPull($pullLogFile, [
        'attemptedAt' => $pullStartedAt->format(DateTimeInterface::ATOM),
        'completedAt' => (new DateTimeImmutable('now', $pullStartedAt->getTimezone()))->format(DateTimeInterface::ATOM),
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
    respond(500, ['ok' => false, 'error' => $exception->getMessage()]);
}
