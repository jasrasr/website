<?php
/**
 * Ticketing - csv.php
 * File Revision: 1.0.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 1.0.0 - Added agent-only CSV import, export, validation, sample-template access, and sequential ticket numbering.
 */

declare(strict_types=1);
session_start();

const DATA_FILE = __DIR__ . '/data/tickets.json';
const PROJECT_REVISION = '1.2.0';

function currentUser(): ?array
{
    return isset($_SESSION['ticketing_user']) && is_array($_SESSION['ticketing_user'])
        ? $_SESSION['ticketing_user']
        : null;
}

function requireAgent(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: index.php');
        exit;
    }
    if (($user['role'] ?? '') !== 'agent') {
        http_response_code(403);
        echo 'Agent access required.';
        exit;
    }
    return $user;
}

function validChoice(string $value, array $allowed, string $fallback): string
{
    $value = trim($value);
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function normalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function safeCsvCell(string $value): string
{
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function normalizeImportedCell(string $value): string
{
    if (preg_match("/^'([=+\\-@].*)$/s", $value, $matches)) {
        return $matches[1];
    }
    return $value;
}

function normalizeDate(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $fallback : gmdate('c', $timestamp);
}

function nextTicketNumber(array $tickets): int
{
    $max = 0;
    foreach ($tickets as $ticket) {
        $max = max($max, (int)($ticket['number'] ?? 0));
    }
    return $max + 1;
}

function normalizeComments(string $json, string $fallbackDate): array
{
    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('commentsJson is not valid JSON.');
    }

    $comments = [];
    foreach ($decoded as $comment) {
        if (!is_array($comment)) {
            continue;
        }
        $body = trim((string)($comment['body'] ?? ''));
        if ($body === '') {
            continue;
        }
        $role = validChoice(strtolower((string)($comment['role'] ?? 'agent')), ['requester', 'agent'], 'agent');
        $visibility = $role === 'requester'
            ? 'public'
            : validChoice(strtolower((string)($comment['visibility'] ?? 'public')), ['public', 'private'], 'public');

        $comments[] = [
            'id' => bin2hex(random_bytes(6)),
            'author' => trim((string)($comment['author'] ?? ucfirst($role))) ?: ucfirst($role),
            'role' => $role,
            'visibility' => $visibility,
            'body' => $body,
            'createdAt' => normalizeDate((string)($comment['createdAt'] ?? ''), $fallbackDate),
        ];
    }
    return $comments;
}

function ticketCsvHeaders(): array
{
    return [
        'ticketNumber', 'subject', 'description', 'requester', 'email', 'status', 'priority',
        'category', 'assignedTo', 'source', 'createdAt', 'updatedAt', 'commentsJson'
    ];
}

function exportTickets(): never
{
    requireAgent();
    $tickets = [];
    if (file_exists(DATA_FILE)) {
        $decoded = json_decode(file_get_contents(DATA_FILE) ?: '[]', true);
        if (is_array($decoded)) {
            $tickets = $decoded;
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ticketing-export-' . gmdate('Y-m-d-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ticketCsvHeaders());

    foreach ($tickets as $ticket) {
        $commentsJson = json_encode($ticket['comments'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        fputcsv($out, [
            (string)($ticket['number'] ?? ''),
            safeCsvCell((string)($ticket['subject'] ?? '')),
            safeCsvCell((string)($ticket['description'] ?? '')),
            safeCsvCell((string)($ticket['requester'] ?? '')),
            (string)($ticket['email'] ?? ''),
            (string)($ticket['status'] ?? 'Open'),
            (string)($ticket['priority'] ?? 'Medium'),
            safeCsvCell((string)($ticket['category'] ?? 'General')),
            safeCsvCell((string)($ticket['assignedTo'] ?? '')),
            safeCsvCell((string)($ticket['source'] ?? 'CSV Import')),
            (string)($ticket['createdAt'] ?? ''),
            (string)($ticket['updatedAt'] ?? ''),
            safeCsvCell($commentsJson === false ? '[]' : $commentsJson),
        ]);
    }
    fclose($out);
    exit;
}

function importTicketsFromCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not read uploaded CSV.');
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('CSV is empty.');
    }

    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
    }
    $header = array_map(static fn($value) => trim((string)$value), $header);
    $required = ['subject', 'description', 'requester', 'email'];
    foreach ($required as $field) {
        if (!in_array($field, $header, true)) {
            fclose($handle);
            throw new RuntimeException("Missing required CSV column: {$field}");
        }
    }

    $rows = [];
    $line = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if (count($data) === 1 && trim((string)$data[0]) === '') {
            continue;
        }
        if (count($data) !== count($header)) {
            $rows[] = ['line' => $line, 'error' => 'Column count does not match header.'];
            continue;
        }
        $row = array_combine($header, array_map(static fn($v) => normalizeImportedCell((string)$v), $data));
        if (!is_array($row)) {
            $rows[] = ['line' => $line, 'error' => 'Could not parse row.'];
            continue;
        }
        $rows[] = ['line' => $line, 'data' => $row];
    }
    fclose($handle);

    $file = fopen(DATA_FILE, 'c+');
    if ($file === false) {
        throw new RuntimeException('Could not open ticket datastore.');
    }

    $imported = 0;
    $errors = [];
    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Could not lock ticket datastore.');
        }
        rewind($file);
        $raw = stream_get_contents($file);
        $tickets = json_decode($raw ?: '[]', true);
        if (!is_array($tickets)) {
            $tickets = [];
        }

        $nextNumber = nextTicketNumber($tickets);
        foreach ($rows as $item) {
            if (isset($item['error'])) {
                $errors[] = 'Line ' . $item['line'] . ': ' . $item['error'];
                continue;
            }
            $row = $item['data'];
            $subject = trim((string)($row['subject'] ?? ''));
            $description = trim((string)($row['description'] ?? ''));
            $requester = trim((string)($row['requester'] ?? ''));
            $email = normalizeEmail((string)($row['email'] ?? ''));
            if ($subject === '' || $description === '' || $requester === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Line ' . $item['line'] . ': subject, description, requester, and a valid email are required.';
                continue;
            }

            $now = gmdate('c');
            $createdAt = normalizeDate((string)($row['createdAt'] ?? ''), $now);
            $updatedAt = normalizeDate((string)($row['updatedAt'] ?? ''), $createdAt);
            try {
                $comments = normalizeComments((string)($row['commentsJson'] ?? ''), $createdAt);
            } catch (RuntimeException $e) {
                $errors[] = 'Line ' . $item['line'] . ': ' . $e->getMessage();
                continue;
            }

            $tickets[] = [
                'id' => bin2hex(random_bytes(8)),
                'number' => $nextNumber++,
                'subject' => $subject,
                'description' => $description,
                'requester' => $requester,
                'email' => $email,
                'status' => validChoice((string)($row['status'] ?? 'Open'), ['Open', 'Pending', 'Resolved', 'Closed'], 'Open'),
                'priority' => validChoice((string)($row['priority'] ?? 'Medium'), ['Low', 'Medium', 'High', 'Urgent'], 'Medium'),
                'category' => trim((string)($row['category'] ?? 'General')) ?: 'General',
                'assignedTo' => trim((string)($row['assignedTo'] ?? '')),
                'source' => trim((string)($row['source'] ?? 'CSV Import')) ?: 'CSV Import',
                'createdAt' => $createdAt,
                'updatedAt' => $updatedAt,
                'comments' => $comments,
            ];
            $imported++;
        }

        if ($imported > 0) {
            $json = json_encode(array_values($tickets), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Could not encode ticket datastore.');
            }
            ftruncate($file, 0);
            rewind($file);
            if (fwrite($file, $json . PHP_EOL) === false) {
                throw new RuntimeException('Could not write ticket datastore.');
            }
            fflush($file);
        }
        flock($file, LOCK_UN);
    } finally {
        fclose($file);
    }

    return ['imported' => $imported, 'errors' => $errors];
}

$user = requireAgent();

if (($_GET['action'] ?? '') === 'export') {
    exportTickets();
}

if (!isset($_SESSION['ticketing_csv_csrf'])) {
    $_SESSION['ticketing_csv_csrf'] = bin2hex(random_bytes(24));
}

$result = null;
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['ticketing_csv_csrf'], $token)) {
        $error = 'Invalid form token. Refresh and try again.';
    } elseif (!isset($_FILES['csvFile']) || ($_FILES['csvFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Choose a CSV file to import.';
    } elseif (($_FILES['csvFile']['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'CSV file must be 5 MB or smaller.';
    } else {
        try {
            $result = importTicketsFromCsv((string)$_FILES['csvFile']['tmp_name']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ticketing CSV Tools</title>
    <link rel="stylesheet" href="styles.css?v=1.1.0">
    <style>
        .csv-shell{max-width:960px;margin:0 auto;padding:28px}.csv-actions{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}.csv-panel{padding:20px;margin-bottom:18px}.csv-panel h2{margin-bottom:10px}.csv-panel ul{color:var(--muted);line-height:1.6}.csv-result{padding:14px;border-radius:10px;margin:14px 0}.csv-result.ok{border:1px solid var(--success);background:rgba(34,197,94,.08)}.csv-result.err{border:1px solid var(--danger);background:rgba(239,68,68,.08)}.csv-errors{max-height:220px;overflow:auto}.csv-upload{display:grid;gap:12px;max-width:620px}.csv-upload input[type=file]{padding:14px}
    </style>
</head>
<body>
<main class="csv-shell">
    <div class="topbar">
        <div><h1>CSV Import / Export</h1><p class="muted">Agent tools · Project rev <?= htmlspecialchars(PROJECT_REVISION) ?></p></div>
        <a class="button secondary" href="index.php">Back to Ticketing</a>
    </div>

    <section class="panel csv-panel">
        <h2>Export tickets</h2>
        <p class="muted">Exports the complete ticket queue, including metadata, timestamps, and reply history in <code>commentsJson</code>.</p>
        <div class="csv-actions">
            <a class="button primary" href="csv.php?action=export">Download Current Tickets CSV</a>
            <a class="button" href="sample-ticket-import.csv" download>Download Sample Import CSV</a>
        </div>
    </section>

    <section class="panel csv-panel">
        <h2>Import tickets</h2>
        <p class="muted">Ticket numbers in the CSV are informational only. New tickets are assigned sequential numbers starting after the current highest ticket number.</p>
        <?php if ($error !== ''): ?>
            <div class="csv-result err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (is_array($result)): ?>
            <div class="csv-result ok"><strong><?= (int)$result['imported'] ?></strong> ticket(s) imported.</div>
            <?php if (!empty($result['errors'])): ?>
                <div class="csv-result err csv-errors"><strong><?= count($result['errors']) ?> row(s) skipped:</strong><ul><?php foreach ($result['errors'] as $message): ?><li><?= htmlspecialchars($message) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
        <?php endif; ?>
        <form class="csv-upload" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_csv_csrf']) ?>">
            <label>CSV file<input type="file" name="csvFile" accept=".csv,text/csv" required></label>
            <button class="button primary" type="submit">Import CSV</button>
        </form>
    </section>

    <section class="panel csv-panel">
        <h2>Supported columns</h2>
        <ul>
            <li><strong>Required:</strong> subject, description, requester, email</li>
            <li><strong>Optional:</strong> status, priority, category, assignedTo, source, createdAt, updatedAt, commentsJson</li>
            <li><strong>Informational:</strong> ticketNumber is exported but ignored during import to prevent duplicate ticket numbers.</li>
            <li><strong>commentsJson:</strong> JSON array containing author, role, visibility, body, and createdAt for public replies and private agent notes.</li>
        </ul>
    </section>
</main>
</body>
</html>
