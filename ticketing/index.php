<?php
/**
 * Ticketing - index.php
 * File Revision: 1.0.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 1.0.0 - Added session authentication, requester/agent authorization, persistent people lists, and protected ticket APIs.
 * Initial build - JSON-backed tickets, requester/agent views, and public/private replies.
 */

declare(strict_types=1);
session_start();

const DATA_FILE = __DIR__ . '/data/tickets.json';
const USERS_FILE = __DIR__ . '/data/users.json';
const DIRECTORY_FILE = __DIR__ . '/data/directory.json';
const PROJECT_FILE = __DIR__ . '/project.json';
const PROJECT_REVISION = '1.0.0';
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
    if (!file_exists($path)) return $fallback;
    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') return $fallback;
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function writeJsonFile(string $path, array $data): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return false;
    $handle = fopen($path, 'c+');
    if ($handle === false) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        ftruncate($handle, 0);
        rewind($handle);
        $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || fwrite($handle, $json . PHP_EOL) === false) return false;
        fflush($handle);
        flock($handle, LOCK_UN);
        return true;
    } finally {
        fclose($handle);
    }
}

function readTickets(): array { return readJsonFile(DATA_FILE, []); }
function readUsers(): array { return readJsonFile(USERS_FILE, []); }
function readDirectory(): array { return readJsonFile(DIRECTORY_FILE, []); }
function readProject(): array {
    return readJsonFile(PROJECT_FILE, ['name' => 'Ticketing', 'revision' => PROJECT_REVISION, 'modified' => PROJECT_MODIFIED]);
}
function normalizeEmail(string $email): string { return strtolower(trim($email)); }
function currentUser(): ?array { return isset($_SESSION['ticketing_user']) && is_array($_SESSION['ticketing_user']) ? $_SESSION['ticketing_user'] : null; }
function requireUser(): array {
    $user = currentUser();
    if ($user === null) jsonResponse(['error' => 'Authentication required.'], 401);
    return $user;
}
function requireAgent(): array {
    $user = requireUser();
    if (($user['role'] ?? '') !== 'agent') jsonResponse(['error' => 'Agent access required.'], 403);
    return $user;
}
function publicUser(array $user): array {
    return ['id' => $user['id'] ?? '', 'name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'role' => $user['role'] ?? 'requester'];
}
function nextTicketNumber(array $tickets): int {
    $max = 0;
    foreach ($tickets as $ticket) $max = max($max, (int)($ticket['number'] ?? 0));
    return $max + 1;
}
function validChoice(string $value, array $allowed, string $fallback): string { return in_array($value, $allowed, true) ? $value : $fallback; }
function normalizeTicketForRequester(array $ticket): array {
    $ticket['comments'] = array_values(array_filter($ticket['comments'] ?? [], static fn(array $comment): bool => ($comment['visibility'] ?? 'public') === 'public'));
    return $ticket;
}
function findUserByEmail(array $users, string $email): ?array {
    $needle = normalizeEmail($email);
    foreach ($users as $user) if (normalizeEmail((string)($user['email'] ?? '')) === $needle) return $user;
    return null;
}
function hasAgent(array $users): bool {
    foreach ($users as $user) if (($user['role'] ?? '') === 'agent' && ($user['active'] ?? true)) return true;
    return false;
}
function upsertDirectory(array &$directory, string $role, string $name, string $email): array {
    $email = normalizeEmail($email);
    foreach ($directory as &$entry) {
        if (($entry['role'] ?? '') === $role && normalizeEmail((string)($entry['email'] ?? '')) === $email) {
            $entry['name'] = $name;
            $entry['active'] = true;
            $entry['updatedAt'] = gmdate('c');
            return $entry;
        }
    }
    unset($entry);
    $entry = ['id' => bin2hex(random_bytes(8)), 'role' => $role, 'name' => $name, 'email' => $email, 'active' => true, 'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
    $directory[] = $entry;
    return $entry;
}

if (isset($_GET['api'])) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = strtolower(trim((string)($_GET['action'] ?? 'tickets')));
    $payload = [];
    if ($method !== 'GET') {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($payload)) jsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    if ($action === 'session' && $method === 'GET') {
        $users = readUsers();
        jsonResponse(['user' => currentUser(), 'agentBootstrapAvailable' => !hasAgent($users), 'project' => readProject()]);
    }

    if ($action === 'login' && $method === 'POST') {
        $email = normalizeEmail((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $role = validChoice(strtolower((string)($payload['role'] ?? 'requester')), ['requester', 'agent'], 'requester');
        $user = findUserByEmail(readUsers(), $email);
        if ($user === null || ($user['role'] ?? '') !== $role || !($user['active'] ?? true) || !password_verify($password, (string)($user['passwordHash'] ?? ''))) {
            jsonResponse(['error' => 'Invalid email, password, or account type.'], 401);
        }
        $_SESSION['ticketing_user'] = publicUser($user);
        session_regenerate_id(true);
        jsonResponse(['user' => $_SESSION['ticketing_user']]);
    }

    if ($action === 'logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'register-requester' && $method === 'POST') {
        $name = trim((string)($payload['name'] ?? ''));
        $email = normalizeEmail((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) jsonResponse(['error' => 'Name, valid email, and a password of at least 8 characters are required.'], 422);
        $users = readUsers();
        if (findUserByEmail($users, $email) !== null) jsonResponse(['error' => 'An account with that email already exists.'], 409);
        $user = ['id' => bin2hex(random_bytes(8)), 'role' => 'requester', 'name' => $name, 'email' => $email, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT), 'active' => true, 'createdAt' => gmdate('c')];
        $users[] = $user;
        $directory = readDirectory();
        upsertDirectory($directory, 'requester', $name, $email);
        if (!writeJsonFile(USERS_FILE, $users) || !writeJsonFile(DIRECTORY_FILE, $directory)) jsonResponse(['error' => 'Could not save account data.'], 500);
        $_SESSION['ticketing_user'] = publicUser($user);
        session_regenerate_id(true);
        jsonResponse(['user' => $_SESSION['ticketing_user']], 201);
    }

    if ($action === 'bootstrap-agent' && $method === 'POST') {
        $users = readUsers();
        if (hasAgent($users)) jsonResponse(['error' => 'The first agent account has already been created.'], 403);
        $name = trim((string)($payload['name'] ?? ''));
        $email = normalizeEmail((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) jsonResponse(['error' => 'Name, valid email, and a password of at least 8 characters are required.'], 422);
        $user = ['id' => bin2hex(random_bytes(8)), 'role' => 'agent', 'name' => $name, 'email' => $email, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT), 'active' => true, 'createdAt' => gmdate('c')];
        $users[] = $user;
        $directory = readDirectory();
        upsertDirectory($directory, 'agent', $name, $email);
        if (!writeJsonFile(USERS_FILE, $users) || !writeJsonFile(DIRECTORY_FILE, $directory)) jsonResponse(['error' => 'Could not save agent account.'], 500);
        $_SESSION['ticketing_user'] = publicUser($user);
        session_regenerate_id(true);
        jsonResponse(['user' => $_SESSION['ticketing_user']], 201);
    }

    if ($action === 'directory') {
        if ($method === 'GET') {
            $user = requireUser();
            $directory = readDirectory();
            if (($user['role'] ?? '') !== 'agent') {
                $directory = array_values(array_filter($directory, static fn(array $entry): bool => normalizeEmail((string)($entry['email'] ?? '')) === normalizeEmail((string)($user['email'] ?? ''))));
            }
            jsonResponse(['directory' => array_values($directory)]);
        }
        if ($method === 'POST') {
            requireAgent();
            $role = validChoice(strtolower((string)($payload['role'] ?? 'requester')), ['requester', 'agent'], 'requester');
            $name = trim((string)($payload['name'] ?? ''));
            $email = normalizeEmail((string)($payload['email'] ?? ''));
            $password = (string)($payload['password'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Name and valid email are required.'], 422);
            $directory = readDirectory();
            $entry = upsertDirectory($directory, $role, $name, $email);
            if (!writeJsonFile(DIRECTORY_FILE, $directory)) jsonResponse(['error' => 'Could not save directory entry.'], 500);
            if ($password !== '') {
                if (strlen($password) < 8) jsonResponse(['error' => 'Password must be at least 8 characters when creating login access.'], 422);
                $users = readUsers();
                $existing = findUserByEmail($users, $email);
                if ($existing !== null) jsonResponse(['error' => 'A login account with that email already exists. Directory entry was saved.'], 409);
                $users[] = ['id' => bin2hex(random_bytes(8)), 'role' => $role, 'name' => $name, 'email' => $email, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT), 'active' => true, 'createdAt' => gmdate('c')];
                if (!writeJsonFile(USERS_FILE, $users)) jsonResponse(['error' => 'Directory saved, but login account could not be saved.'], 500);
            }
            jsonResponse(['entry' => $entry], 201);
        }
    }

    if ($action !== 'tickets') jsonResponse(['error' => 'Unknown API action.'], 404);

    $user = requireUser();
    $tickets = readTickets();
    $project = readProject();

    if ($method === 'GET') {
        if (($user['role'] ?? '') === 'requester') {
            $email = normalizeEmail((string)($user['email'] ?? ''));
            $mine = array_values(array_filter($tickets, static fn(array $ticket): bool => normalizeEmail((string)($ticket['email'] ?? '')) === $email));
            jsonResponse(['tickets' => array_map('normalizeTicketForRequester', $mine), 'project' => $project]);
        }
        jsonResponse(['tickets' => array_values($tickets), 'project' => $project]);
    }

    if ($method === 'POST') {
        $subject = trim((string)($payload['subject'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        if ($subject === '' || $description === '') jsonResponse(['error' => 'Subject and description are required.'], 422);
        if (($user['role'] ?? '') === 'requester') {
            $requester = (string)($user['name'] ?? 'Requester');
            $email = (string)($user['email'] ?? '');
            $assignedTo = '';
        } else {
            $requester = trim((string)($payload['requester'] ?? ''));
            $email = normalizeEmail((string)($payload['email'] ?? ''));
            $assignedTo = trim((string)($payload['assignedTo'] ?? ''));
            if ($requester === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Requester and valid requester email are required.'], 422);
        }
        $now = gmdate('c');
        $ticket = [
            'id' => bin2hex(random_bytes(8)), 'number' => nextTicketNumber($tickets), 'subject' => $subject, 'description' => $description,
            'requester' => $requester, 'email' => normalizeEmail($email), 'status' => 'Open',
            'priority' => validChoice((string)($payload['priority'] ?? 'Medium'), ['Low', 'Medium', 'High', 'Urgent'], 'Medium'),
            'category' => trim((string)($payload['category'] ?? 'General')) ?: 'General', 'assignedTo' => $assignedTo,
            'source' => trim((string)($payload['source'] ?? 'Portal')) ?: 'Portal', 'createdAt' => $now, 'updatedAt' => $now, 'comments' => []
        ];
        $tickets[] = $ticket;
        if (!writeJsonFile(DATA_FILE, $tickets)) jsonResponse(['error' => 'Could not save ticket data.'], 500);
        jsonResponse(['ticket' => (($user['role'] ?? '') === 'requester' ? normalizeTicketForRequester($ticket) : $ticket)], 201);
    }

    if ($method === 'PUT') {
        $id = trim((string)($payload['id'] ?? ''));
        $updatedTicket = null;
        foreach ($tickets as &$ticket) {
            if (($ticket['id'] ?? '') !== $id) continue;
            if (($user['role'] ?? '') === 'requester') {
                if (normalizeEmail((string)($ticket['email'] ?? '')) !== normalizeEmail((string)($user['email'] ?? ''))) jsonResponse(['error' => 'You do not have access to this ticket.'], 403);
            } else {
                foreach (['subject', 'description', 'requester', 'email', 'category', 'assignedTo', 'source'] as $field) if (array_key_exists($field, $payload)) $ticket[$field] = trim((string)$payload[$field]);
                if (array_key_exists('status', $payload)) $ticket['status'] = validChoice((string)$payload['status'], ['Open', 'Pending', 'Resolved', 'Closed'], 'Open');
                if (array_key_exists('priority', $payload)) $ticket['priority'] = validChoice((string)$payload['priority'], ['Low', 'Medium', 'High', 'Urgent'], 'Medium');
            }
            $comment = trim((string)($payload['comment'] ?? ''));
            if ($comment !== '') {
                $visibility = ($user['role'] ?? '') === 'requester' ? 'public' : validChoice(strtolower((string)($payload['visibility'] ?? 'public')), ['public', 'private'], 'public');
                $ticket['comments'][] = ['id' => bin2hex(random_bytes(6)), 'author' => (string)($user['name'] ?? ucfirst((string)$user['role'])), 'role' => $user['role'], 'visibility' => $visibility, 'body' => $comment, 'createdAt' => gmdate('c')];
            }
            $ticket['updatedAt'] = gmdate('c');
            $updatedTicket = $ticket;
            break;
        }
        unset($ticket);
        if ($updatedTicket === null) jsonResponse(['error' => 'Ticket not found.'], 404);
        if (!writeJsonFile(DATA_FILE, $tickets)) jsonResponse(['error' => 'Could not save ticket data.'], 500);
        jsonResponse(['ticket' => (($user['role'] ?? '') === 'requester' ? normalizeTicketForRequester($updatedTicket) : $updatedTicket)]);
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
    <link rel="stylesheet" href="styles.css?v=1.0.0">
</head>
<body>
<div id="loginScreen" class="login-screen">
    <div class="login-card">
        <div class="brand login-brand">Ticketing</div>
        <h1>Sign in</h1>
        <p class="muted">Use the requester or agent portal.</p>
        <div class="portal-switch login-switch">
            <button type="button" class="portal-button active" data-login-role="requester">Requester</button>
            <button type="button" class="portal-button" data-login-role="agent">Agent</button>
        </div>
        <form id="loginForm">
            <label id="loginNameWrap" class="hidden">Name<input id="loginName" autocomplete="name"></label>
            <label>Email<input id="loginEmail" type="email" autocomplete="email" required></label>
            <label>Password<input id="loginPassword" type="password" autocomplete="current-password" required></label>
            <button class="button primary full-button" type="submit" id="loginSubmitButton">Sign in</button>
        </form>
        <button type="button" class="link-button" id="registerRequesterButton">Create requester account</button>
        <button type="button" class="link-button hidden" id="bootstrapAgentButton">Create first agent account</button>
        <button type="button" class="link-button hidden" id="backToLoginButton">Back to sign in</button>
        <div id="loginMessage" class="form-message"></div>
    </div>
</div>

<div class="app-shell hidden" id="appShell">
    <aside class="sidebar">
        <div class="brand">Ticketing</div>
        <div class="signed-in"><span id="signedInName"></span><small id="signedInRole"></small></div>
        <nav id="requesterNav" class="hidden">
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
            <button class="link-button sidebar-link" id="logoutButton">Sign out</button>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div><h1 id="pageTitle">Dashboard</h1><p class="muted" id="pageSubtitle"></p></div>
            <button class="button" id="refreshButton">Refresh</button>
        </header>
        <section id="requesterDashboardView" class="hidden"><div class="stats" id="requesterStats"></div><div class="panel"><div class="panel-heading"><h2>My Recent Tickets</h2></div><div id="requesterRecentTickets"></div></div></section>
        <section id="myTicketsView" class="hidden"><div class="toolbar requester-toolbar"><input id="requesterSearchInput" type="search" placeholder="Search my tickets..."><select id="requesterStatusFilter"><option value="">All statuses</option><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></div><div class="panel"><div id="myTicketList"></div></div></section>
        <section id="agentDashboardView" class="hidden"><div class="stats" id="agentStats"></div><div class="panel"><div class="panel-heading"><h2>Recently Updated</h2></div><div id="agentRecentTickets"></div></div></section>
        <section id="allTicketsView" class="hidden"><div class="toolbar agent-toolbar"><input id="agentSearchInput" type="search" placeholder="Search ticket, requester, email, agent..."><select id="agentStatusFilter"><option value="">All statuses</option><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select><select id="agentPriorityFilter"><option value="">All priorities</option><option>Low</option><option>Medium</option><option>High</option><option>Urgent</option></select><select id="agentFilter"><option value="">All agents</option></select></div><div class="panel"><div id="agentTicketList"></div></div></section>
        <footer class="project-footer"><span id="projectRevision">Project rev --</span><span id="projectModified">Modified --</span><a href="CHANGELOG.md" target="_blank" rel="noopener">Changelog</a></footer>
    </main>
</div>

<dialog id="ticketDialog">
<form method="dialog" id="ticketForm">
<div class="dialog-header"><div><div class="eyebrow" id="ticketEyebrow">New ticket</div><h2 id="dialogTitle">Create Ticket</h2></div><button type="button" class="icon-button" id="closeDialogButton" aria-label="Close">×</button></div>
<input type="hidden" id="ticketId">
<div class="form-grid">
<label class="full">Subject<input id="subject" required></label>
<label class="agent-field">Requester<select id="requesterSelect"></select></label>
<label class="agent-field">Requester Email<input id="email" type="email"></label>
<label>Priority<select id="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></label>
<label class="agent-field">Status<select id="status"><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></label>
<label>Category<input id="category" value="General"></label>
<label class="agent-field">Assigned To<select id="assignedTo"></select></label>
<label class="agent-field">Source<input id="source" value="Portal"></label>
<label class="full">Description<textarea id="description" rows="5" required></textarea></label>
</div>
<div id="replySection" class="reply-section hidden"><div class="reply-heading"><h3>Reply</h3><div id="visibilityControl" class="visibility-control"><label><input type="radio" name="replyVisibility" value="public" checked> Public reply</label><label><input type="radio" name="replyVisibility" value="private"> Private note</label></div></div><p class="muted small" id="replyHint"></p><textarea id="comment" rows="4" placeholder="Write a reply or note..."></textarea></div>
<div id="commentHistory" class="comments hidden"></div>
<div class="dialog-actions"><button type="button" class="button secondary" id="cancelButton">Cancel</button><button type="submit" class="button primary" id="saveButton">Create Ticket</button></div>
</form>
</dialog>

<dialog id="personDialog">
<form method="dialog" id="personForm"><div class="dialog-header"><div><div class="eyebrow">Directory</div><h2 id="personDialogTitle">Add Person</h2></div><button type="button" class="icon-button" id="closePersonDialogButton">×</button></div><input type="hidden" id="personRole"><div class="form-grid"><label>Name<input id="personName" required></label><label>Email<input id="personEmail" type="email" required></label><label class="full">Login password <span class="muted small">optional; 8+ characters creates login access</span><input id="personPassword" type="password"></label></div><div class="dialog-actions"><button type="button" class="button secondary" id="cancelPersonButton">Cancel</button><button type="submit" class="button primary">Save</button></div></form>
</dialog>

<script src="app.js?v=1.0.0"></script>
</body>
</html>
