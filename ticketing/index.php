<?php
/**
 * Ticketing - index.php
 * File Revision: 0.2.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 0.2.0 - Added requester/agent views, public/private replies, project metadata API.
 * 0.1.1 - Fixed PUT response to return the updated ticket.
 * 0.1.0 - Initial JSON-backed ticket API and interface.
 */

declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data/tickets.json';
const PROJECT_FILE = __DIR__ . '/project.json';
const PROJECT_REVISION = '0.2.0';
const PROJECT_MODIFIED = '2026-09-14';

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJsonFile(string $path, array $fallback = []): array
{
    if (!file_exists($path)) {
        return $fallback;
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $fallback;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function readTickets(): array
{
    return readJsonFile(DATA_FILE, []);
}

function readProject(): array
{
    return readJsonFile(PROJECT_FILE, [
        'name' => 'Ticketing',
        'revision' => PROJECT_REVISION,
        'modified' => PROJECT_MODIFIED,
    ]);
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

function normalizeTicketForRequester(array $ticket): array
{
    $ticket['comments'] = array_values(array_filter(
        $ticket['comments'] ?? [],
        static fn(array $comment): bool => ($comment['visibility'] ?? 'public') === 'public'
    ));
    return $ticket;
}

function validChoice(string $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

if (isset($_GET['api'])) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $tickets = readTickets();
    $project = readProject();

    if ($method === 'GET') {
        $portal = strtolower(trim((string)($_GET['portal'] ?? 'agent')));

        if ($portal === 'requester') {
            $email = strtolower(trim((string)($_GET['email'] ?? '')));
            if ($email === '') {
                jsonResponse(['tickets' => [], 'project' => $project]);
            }

            $mine = array_values(array_filter(
                $tickets,
                static fn(array $ticket): bool => strtolower(trim((string)($ticket['email'] ?? ''))) === $email
            ));
            $mine = array_map('normalizeTicketForRequester', $mine);
            jsonResponse(['tickets' => $mine, 'project' => $project]);
        }

        jsonResponse(['tickets' => array_values($tickets), 'project' => $project]);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        jsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    if ($method === 'POST') {
        $subject = trim((string)($payload['subject'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $requester = trim((string)($payload['requester'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));

        if ($subject === '' || $description === '' || $requester === '' || $email === '') {
            jsonResponse(['error' => 'Subject, description, requester, and email are required.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Enter a valid requester email address.'], 422);
        }

        $now = gmdate('c');
        $ticket = [
            'id' => bin2hex(random_bytes(8)),
            'number' => nextTicketNumber($tickets),
            'subject' => $subject,
            'description' => $description,
            'requester' => $requester,
            'email' => $email,
            'status' => 'Open',
            'priority' => validChoice((string)($payload['priority'] ?? 'Medium'), ['Low', 'Medium', 'High', 'Urgent'], 'Medium'),
            'category' => trim((string)($payload['category'] ?? 'General')) ?: 'General',
            'assignedTo' => '',
            'source' => trim((string)($payload['source'] ?? 'Portal')) ?: 'Portal',
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
        $actorRole = strtolower(trim((string)($payload['actorRole'] ?? 'agent')));
        $updatedTicket = null;

        foreach ($tickets as &$ticket) {
            if (($ticket['id'] ?? '') !== $id) {
                continue;
            }

            if ($actorRole === 'requester') {
                $requesterEmail = strtolower(trim((string)($payload['requesterEmail'] ?? '')));
                if ($requesterEmail === '' || $requesterEmail !== strtolower(trim((string)($ticket['email'] ?? '')))) {
                    jsonResponse(['error' => 'Requester email does not match this ticket.'], 403);
                }
            } else {
                foreach (['subject', 'description', 'requester', 'email', 'category', 'assignedTo', 'source'] as $field) {
                    if (array_key_exists($field, $payload)) {
                        $ticket[$field] = trim((string)$payload[$field]);
                    }
                }

                if (array_key_exists('status', $payload)) {
                    $ticket['status'] = validChoice((string)$payload['status'], ['Open', 'Pending', 'Resolved', 'Closed'], 'Open');
                }
                if (array_key_exists('priority', $payload)) {
                    $ticket['priority'] = validChoice((string)$payload['priority'], ['Low', 'Medium', 'High', 'Urgent'], 'Medium');
                }
            }

            $comment = trim((string)($payload['comment'] ?? ''));
            if ($comment !== '') {
                $visibility = $actorRole === 'requester'
                    ? 'public'
                    : validChoice(strtolower((string)($payload['visibility'] ?? 'public')), ['public', 'private'], 'public');

                $ticket['comments'][] = [
                    'id' => bin2hex(random_bytes(6)),
                    'author' => trim((string)($payload['commentAuthor'] ?? ($actorRole === 'requester' ? $ticket['requester'] : 'Agent'))) ?: 'Agent',
                    'role' => $actorRole === 'requester' ? 'requester' : 'agent',
                    'visibility' => $visibility,
                    'body' => $comment,
                    'createdAt' => gmdate('c'),
                ];
            }

            $ticket['updatedAt'] = gmdate('c');
            $updatedTicket = $ticket;
            break;
        }
        unset($ticket);

        if ($updatedTicket === null) {
            jsonResponse(['error' => 'Ticket not found.'], 404);
        }
        if (!writeTickets($tickets)) {
            jsonResponse(['error' => 'Could not save ticket data.'], 500);
        }

        if ($actorRole === 'requester') {
            $updatedTicket = normalizeTicketForRequester($updatedTicket);
        }
        jsonResponse(['ticket' => $updatedTicket]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lightweight JSON-backed ticketing system">
    <title>Ticketing</title>
    <link rel="stylesheet" href="styles.css?v=0.2.0">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">Ticketing</div>
        <div class="portal-switch" role="group" aria-label="Portal mode">
            <button class="portal-button active" data-portal="requester">Requester</button>
            <button class="portal-button" data-portal="agent">Agent</button>
        </div>
        <nav id="requesterNav">
            <button class="nav-button active" data-requester-view="requesterDashboard">My Dashboard</button>
            <button class="nav-button" data-requester-view="myTickets">My Tickets</button>
            <button class="nav-button primary" id="requesterNewTicketButton">+ Submit Ticket</button>
        </nav>
        <nav id="agentNav" class="hidden">
            <button class="nav-button active" data-agent-view="agentDashboard">Agent Dashboard</button>
            <button class="nav-button" data-agent-view="allTickets">All Tickets</button>
            <button class="nav-button primary" id="agentNewTicketButton">+ New Ticket</button>
        </nav>
        <div class="sidebar-footer">
            <a href="CHANGELOG.md" target="_blank" rel="noopener">View Changelog</a>
            <a href="TODO.md" target="_blank" rel="noopener">Future Features</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1 id="pageTitle">My Dashboard</h1>
                <p class="muted" id="pageSubtitle">Track the tickets you submitted.</p>
            </div>
            <button class="button" id="refreshButton">Refresh</button>
        </header>

        <section id="requesterIdentity" class="identity-card">
            <div>
                <strong>Requester portal</strong>
                <span class="muted small">Enter your email to load tickets submitted with that address.</span>
            </div>
            <div class="identity-controls">
                <input id="requesterEmailLookup" type="email" placeholder="you@example.com" autocomplete="email">
                <button class="button primary" id="loadMyTicketsButton">Load My Tickets</button>
            </div>
        </section>

        <section id="requesterDashboardView">
            <div class="stats" id="requesterStats"></div>
            <div class="panel">
                <div class="panel-heading"><h2>My Recent Tickets</h2></div>
                <div id="requesterRecentTickets"></div>
            </div>
        </section>

        <section id="myTicketsView" class="hidden">
            <div class="toolbar requester-toolbar">
                <input id="requesterSearchInput" type="search" placeholder="Search my tickets...">
                <select id="requesterStatusFilter">
                    <option value="">All statuses</option>
                    <option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option>
                </select>
            </div>
            <div class="panel"><div id="myTicketList"></div></div>
        </section>

        <section id="agentDashboardView" class="hidden">
            <div class="notice warning"><strong>Prototype agent portal:</strong> authentication and role enforcement are not implemented yet.</div>
            <div class="stats" id="agentStats"></div>
            <div class="panel">
                <div class="panel-heading"><h2>Recently Updated</h2></div>
                <div id="agentRecentTickets"></div>
            </div>
        </section>

        <section id="allTicketsView" class="hidden">
            <div class="toolbar agent-toolbar">
                <input id="agentSearchInput" type="search" placeholder="Search ticket, requester, email, agent...">
                <select id="agentStatusFilter">
                    <option value="">All statuses</option>
                    <option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option>
                </select>
                <select id="agentPriorityFilter">
                    <option value="">All priorities</option>
                    <option>Low</option><option>Medium</option><option>High</option><option>Urgent</option>
                </select>
                <input id="agentFilter" placeholder="Filter agent...">
            </div>
            <div class="panel"><div id="agentTicketList"></div></div>
        </section>

        <footer class="project-footer">
            <span id="projectRevision">Project rev --</span>
            <span id="projectModified">Modified --</span>
            <a href="CHANGELOG.md" target="_blank" rel="noopener">Changelog</a>
        </footer>
    </main>
</div>

<dialog id="ticketDialog">
    <form method="dialog" id="ticketForm">
        <div class="dialog-header">
            <div>
                <div class="eyebrow" id="ticketEyebrow">New ticket</div>
                <h2 id="dialogTitle">Create Ticket</h2>
            </div>
            <button type="button" class="icon-button" id="closeDialogButton" aria-label="Close">×</button>
        </div>
        <input type="hidden" id="ticketId">
        <div class="form-grid">
            <label class="full">Subject<input id="subject" required></label>
            <label>Requester<input id="requester" required></label>
            <label>Email<input id="email" type="email" required></label>
            <label>Priority<select id="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></label>
            <label class="agent-field">Status<select id="status"><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></label>
            <label>Category<input id="category" value="General"></label>
            <label class="agent-field">Assigned To<input id="assignedTo" placeholder="Unassigned"></label>
            <label class="agent-field">Source<input id="source" value="Portal"></label>
            <label class="full">Description<textarea id="description" rows="5" required></textarea></label>
        </div>

        <div id="replySection" class="reply-section hidden">
            <div class="reply-heading">
                <h3 id="replyHeading">Reply</h3>
                <div id="visibilityControl" class="visibility-control">
                    <label><input type="radio" name="replyVisibility" value="public" checked> Public reply</label>
                    <label><input type="radio" name="replyVisibility" value="private"> Private note</label>
                </div>
            </div>
            <textarea id="comment" rows="4" placeholder="Write a reply or note..."></textarea>
            <p class="muted small" id="replyHint">Public replies are visible to the requester. Private notes are agent-only.</p>
        </div>

        <div id="commentHistory" class="comments hidden"></div>
        <div class="dialog-actions">
            <button type="button" class="button secondary" id="cancelButton">Cancel</button>
            <button type="submit" class="button primary" id="saveButton">Create Ticket</button>
        </div>
    </form>
</dialog>

<script src="app.js?v=0.2.0"></script>
</body>
</html>
