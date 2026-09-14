<?php

declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data/tickets.json';

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function readTickets(): array
{
    if (!file_exists(DATA_FILE)) {
        return [];
    }

    $contents = file_get_contents(DATA_FILE);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $tickets = json_decode($contents, true);
    return is_array($tickets) ? $tickets : [];
}

function writeTickets(array $tickets): bool
{
    $directory = dirname(DATA_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $handle = fopen(DATA_FILE, 'c+');
    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }

        ftruncate($handle, 0);
        rewind($handle);
        $json = json_encode(array_values($tickets), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || fwrite($handle, $json . PHP_EOL) === false) {
            return false;
        }
        fflush($handle);
        flock($handle, LOCK_UN);
        return true;
    } finally {
        fclose($handle);
    }
}

function nextTicketNumber(array $tickets): int
{
    $max = 0;
    foreach ($tickets as $ticket) {
        $max = max($max, (int)($ticket['number'] ?? 0));
    }
    return $max + 1;
}

if (isset($_GET['api'])) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $tickets = readTickets();

    if ($method === 'GET') {
        jsonResponse(['tickets' => array_values($tickets)]);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        jsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    if ($method === 'POST') {
        $subject = trim((string)($payload['subject'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $requester = trim((string)($payload['requester'] ?? ''));

        if ($subject === '' || $description === '' || $requester === '') {
            jsonResponse(['error' => 'Subject, description, and requester are required.'], 422);
        }

        $now = gmdate('c');
        $ticket = [
            'id' => bin2hex(random_bytes(8)),
            'number' => nextTicketNumber($tickets),
            'subject' => $subject,
            'description' => $description,
            'requester' => $requester,
            'email' => trim((string)($payload['email'] ?? '')),
            'status' => (string)($payload['status'] ?? 'Open'),
            'priority' => (string)($payload['priority'] ?? 'Medium'),
            'category' => trim((string)($payload['category'] ?? 'General')) ?: 'General',
            'assignedTo' => trim((string)($payload['assignedTo'] ?? '')),
            'createdAt' => $now,
            'updatedAt' => $now,
            'comments' => [],
        ];

        $tickets[] = $ticket;
        if (!writeTickets($tickets)) {
            jsonResponse(['error' => 'Could not save ticket data. Check write permissions on ticketing/data.'], 500);
        }
        jsonResponse(['ticket' => $ticket], 201);
    }

    if ($method === 'PUT') {
        $id = trim((string)($payload['id'] ?? ''));
        $found = false;

        foreach ($tickets as &$ticket) {
            if (($ticket['id'] ?? '') !== $id) {
                continue;
            }

            foreach (['subject', 'description', 'requester', 'email', 'status', 'priority', 'category', 'assignedTo'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $ticket[$field] = trim((string)$payload[$field]);
                }
            }

            $comment = trim((string)($payload['comment'] ?? ''));
            if ($comment !== '') {
                $ticket['comments'][] = [
                    'id' => bin2hex(random_bytes(6)),
                    'author' => trim((string)($payload['commentAuthor'] ?? 'Agent')) ?: 'Agent',
                    'body' => $comment,
                    'createdAt' => gmdate('c'),
                ];
            }

            $ticket['updatedAt'] = gmdate('c');
            $found = true;
            break;
        }
        unset($ticket);

        if (!$found) {
            jsonResponse(['error' => 'Ticket not found.'], 404);
        }
        if (!writeTickets($tickets)) {
            jsonResponse(['error' => 'Could not save ticket data.'], 500);
        }
        jsonResponse(['ticket' => $ticket ?? null]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticketing</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">Ticketing</div>
        <nav>
            <button class="nav-button active" data-view="dashboard">Dashboard</button>
            <button class="nav-button" data-view="tickets">Tickets</button>
            <button class="nav-button primary" id="newTicketButton">+ New Ticket</button>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1 id="pageTitle">Dashboard</h1>
                <p class="muted">JSON-backed help desk starter</p>
            </div>
            <button class="button" id="refreshButton">Refresh</button>
        </header>

        <section id="dashboardView">
            <div class="stats" id="stats"></div>
            <div class="panel">
                <div class="panel-heading">
                    <h2>Recent Tickets</h2>
                </div>
                <div id="recentTickets"></div>
            </div>
        </section>

        <section id="ticketsView" class="hidden">
            <div class="toolbar">
                <input id="searchInput" type="search" placeholder="Search tickets...">
                <select id="statusFilter">
                    <option value="">All statuses</option>
                    <option>Open</option>
                    <option>Pending</option>
                    <option>Resolved</option>
                    <option>Closed</option>
                </select>
                <select id="priorityFilter">
                    <option value="">All priorities</option>
                    <option>Low</option>
                    <option>Medium</option>
                    <option>High</option>
                    <option>Urgent</option>
                </select>
            </div>
            <div class="panel"><div id="ticketList"></div></div>
        </section>
    </main>
</div>

<dialog id="ticketDialog">
    <form method="dialog" id="ticketForm">
        <div class="dialog-header">
            <div>
                <div class="eyebrow" id="ticketEyebrow">New ticket</div>
                <h2 id="dialogTitle">Create Ticket</h2>
            </div>
            <button type="button" class="icon-button" id="closeDialogButton">×</button>
        </div>
        <input type="hidden" id="ticketId">
        <div class="form-grid">
            <label class="full">Subject<input id="subject" required></label>
            <label>Requester<input id="requester" required></label>
            <label>Email<input id="email" type="email"></label>
            <label>Priority<select id="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></label>
            <label>Status<select id="status"><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></label>
            <label>Category<input id="category" value="General"></label>
            <label>Assigned To<input id="assignedTo" placeholder="Unassigned"></label>
            <label class="full">Description<textarea id="description" rows="5" required></textarea></label>
            <label class="full edit-only hidden" id="commentWrap">Add comment<textarea id="comment" rows="3"></textarea></label>
        </div>
        <div id="commentHistory" class="comments hidden"></div>
        <div class="dialog-actions">
            <button type="button" class="button secondary" id="cancelButton">Cancel</button>
            <button type="submit" class="button primary" id="saveButton">Create Ticket</button>
        </div>
    </form>
</dialog>

<script src="app.js"></script>
</body>
</html>
