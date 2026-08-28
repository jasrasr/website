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

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function freshserviceGet(string $baseUrl, string $apiKey, string $path): array
{
    $handle = curl_init($baseUrl . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $apiKey . ':X',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($body === false || $error !== '') {
        return ['ok' => false, 'httpStatus' => $status, 'error' => $error];
    }

    $decoded = json_decode($body, true);
    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'httpStatus' => $status,
            'error' => is_array($decoded) ? ($decoded['description'] ?? $decoded['message'] ?? 'Freshservice request failed.') : 'Freshservice request failed.',
        ];
    }

    return ['ok' => true, 'httpStatus' => $status, 'data' => is_array($decoded) ? $decoded : []];
}

function safeTicket(array $ticket): array
{
    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'status' => (int) ($ticket['status'] ?? 0),
        'responderId' => isset($ticket['responder_id']) ? (int) $ticket['responder_id'] : null,
        'groupId' => isset($ticket['group_id']) ? (int) $ticket['group_id'] : null,
        'workspaceId' => isset($ticket['workspace_id']) ? (int) $ticket['workspace_id'] : null,
    ];
}

if (!is_file($configFile)) {
    respond(503, ['ok' => false, 'error' => 'Missing config.local.php.']);
}

$config = require $configFile;
if (!is_array($config)) respond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$expectedToken = (string) ($config['collector_token'] ?? '');
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ')
    ? substr($authorization, 7)
    : (string) ($_SERVER['HTTP_X_COLLECTOR_TOKEN'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    respond(401, ['ok' => false, 'error' => 'Unauthorized diagnostic request.']);
}

if (!function_exists('curl_init')) {
    respond(500, ['ok' => false, 'error' => 'The PHP cURL extension is required.']);
}

$domain = trim((string) ($config['domain'] ?? ''));
if (!preg_match('#^https?://#i', $domain)) $domain = 'https://' . $domain;
$host = (string) (parse_url($domain, PHP_URL_HOST) ?: '');
if ($host === '') respond(500, ['ok' => false, 'error' => 'Invalid Freshservice domain.']);

$baseUrl = 'https://' . $host;
$apiKey = (string) ($config['api_key'] ?? '');
$agentId = (int) ($config['agent_id'] ?? 0);
$workspaceId = (int) ($config['workspace_id'] ?? 0);
$ticketId = max(0, (int) ($_GET['ticket_id'] ?? 0));

$agentResult = freshserviceGet($baseUrl, $apiKey, '/api/v2/agents/' . $agentId);
$anyTicketsResult = freshserviceGet($baseUrl, $apiKey, '/api/v2/tickets?' . http_build_query([
    'workspace_id' => $workspaceId,
    'per_page' => 1,
], '', '&', PHP_QUERY_RFC3986));
$assignedResult = freshserviceGet($baseUrl, $apiKey, '/api/v2/tickets/filter?' . http_build_query([
    'query' => sprintf('"agent_id:%d"', $agentId),
    'workspace_id' => $workspaceId,
    'page' => 1,
], '', '&', PHP_QUERY_RFC3986));
$ticketResult = $ticketId > 0
    ? freshserviceGet($baseUrl, $apiKey, '/api/v2/tickets/' . $ticketId)
    : null;

$agentData = (array) ($agentResult['data']['agent'] ?? []);
$anyTickets = (array) ($anyTicketsResult['data']['tickets'] ?? []);
$assignedTickets = (array) ($assignedResult['data']['tickets'] ?? []);
$statusCounts = [];
foreach ($assignedTickets as $ticket) {
    if (!is_array($ticket)) continue;
    $status = (string) ((int) ($ticket['status'] ?? 0));
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}

respond(200, [
    'ok' => $agentResult['ok'] && $anyTicketsResult['ok'] && $assignedResult['ok'],
    'configuration' => [
        'domain' => $host,
        'agentId' => $agentId,
        'workspaceId' => $workspaceId,
    ],
    'agentLookup' => [
        'ok' => $agentResult['ok'],
        'httpStatus' => $agentResult['httpStatus'],
        'error' => $agentResult['error'] ?? null,
        'returnedId' => isset($agentData['id']) ? (int) $agentData['id'] : null,
        'active' => isset($agentData['active']) ? (bool) $agentData['active'] : null,
    ],
    'accessibleTickets' => [
        'ok' => $anyTicketsResult['ok'],
        'httpStatus' => $anyTicketsResult['httpStatus'],
        'error' => $anyTicketsResult['error'] ?? null,
        'sample' => isset($anyTickets[0]) && is_array($anyTickets[0]) ? safeTicket($anyTickets[0]) : null,
    ],
    'agentFilter' => [
        'ok' => $assignedResult['ok'],
        'httpStatus' => $assignedResult['httpStatus'],
        'error' => $assignedResult['error'] ?? null,
        'returnedOnFirstPage' => count($assignedTickets),
        'total' => (int) ($assignedResult['data']['total'] ?? count($assignedTickets)),
        'statusCounts' => $statusCounts,
        'sample' => isset($assignedTickets[0]) && is_array($assignedTickets[0]) ? safeTicket($assignedTickets[0]) : null,
    ],
    'requestedTicket' => $ticketResult === null ? null : [
        'ok' => $ticketResult['ok'],
        'httpStatus' => $ticketResult['httpStatus'],
        'error' => $ticketResult['error'] ?? null,
        'ticket' => isset($ticketResult['data']['ticket']) && is_array($ticketResult['data']['ticket'])
            ? safeTicket($ticketResult['data']['ticket'])
            : null,
    ],
]);
