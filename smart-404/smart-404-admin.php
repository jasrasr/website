<?php
/*
Filename: smart-404-admin.php
Revision: 1.3.0
Description: Password-gated web viewer and editor for smart-404 logs, manual redirects, and malicious patterns.
Author: Jason Lamb (with help from Codex CLI)
Created Date: 2026-05-18
Modified Date: 2026-05-27
Changelog:
1.0.0 initial release with login, filtering, summary stats, and newest-first log table
1.1.0 add manual map create, update, and delete controls
1.2.0 add map-from-log controls with optional matching log cleanup notices
1.3.0 add malicious pattern controls with optional matching log cleanup
*/

declare(strict_types=1);

const SMART_404_DATA_DIR = __DIR__ . '/smart-404-data';
const SMART_404_LOG_FILE = SMART_404_DATA_DIR . '/404-requests.jsonl';
const SMART_404_MAP_FILE = SMART_404_DATA_DIR . '/smart-404-map.json';
const SMART_404_MALICIOUS_FILE = SMART_404_DATA_DIR . '/smart-404-malicious.json';
const SMART_404_PASSWORD_FILE = SMART_404_DATA_DIR . '/smart-404-admin-password.php';
const SMART_404_SESSION_KEY = 'smart_404_admin_authenticated';

session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function smart_404_normalize_map_path(string $path): string
{
    $path = trim($path, "/ \t\n\r\0\x0B");
    return preg_replace('#/+#', '/', $path) ?? $path;
}

function smart_404_is_safe_target(string $target): bool
{
    $target = trim($target);
    if ($target === '' || str_starts_with($target, '//')) {
        return false;
    }

    if (str_starts_with($target, '/')) {
        return true;
    }

    return false;
}

function smart_404_admin_password_hash(): string
{
    $envHash = getenv('SMART_404_ADMIN_PASSWORD_HASH');
    if (is_string($envHash) && $envHash !== '') {
        return $envHash;
    }

    if (is_file(SMART_404_PASSWORD_FILE)) {
        $value = require SMART_404_PASSWORD_FILE;
        return is_string($value) ? $value : '';
    }

    return '';
}

function smart_404_read_log(): array
{
    if (!is_file(SMART_404_LOG_FILE)) {
        return [];
    }

    $lines = file(SMART_404_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $entries = [];
    foreach ($lines as $lineNumber => $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            $entries[] = [
                'time_utc' => '',
                'path' => '[invalid-json-line-' . ($lineNumber + 1) . ']',
                'query' => '',
                'mapped_to' => '',
                'matched_by' => '',
                'referer' => '',
                'user_agent' => $line,
                'ip' => '',
            ];
            continue;
        }

        $entries[] = [
            'time_utc' => (string) ($decoded['time_utc'] ?? ''),
            'path' => (string) ($decoded['path'] ?? ''),
            'query' => (string) ($decoded['query'] ?? ''),
            'mapped_to' => (string) ($decoded['mapped_to'] ?? ''),
            'matched_by' => (string) ($decoded['matched_by'] ?? ''),
            'referer' => (string) ($decoded['referer'] ?? ''),
            'user_agent' => (string) ($decoded['user_agent'] ?? ''),
            'ip' => (string) ($decoded['ip'] ?? ''),
        ];
    }

    return array_reverse($entries);
}

function smart_404_load_map(): array
{
    if (!is_file(SMART_404_MAP_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(SMART_404_MAP_FILE), true);
    if (!is_array($decoded)) {
        return [];
    }

    $map = [];
    foreach ($decoded as $source => $target) {
        if (is_string($source) && is_string($target)) {
            $map[$source] = $target;
        }
    }
    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    return $map;
}

function smart_404_save_map(array $map): bool
{
    if (!is_dir(SMART_404_DATA_DIR)) {
        mkdir(SMART_404_DATA_DIR, 0755, true);
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }

    return file_put_contents(SMART_404_MAP_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function smart_404_pattern_types(): array
{
    return ['exact', 'prefix', 'contains'];
}

function smart_404_load_malicious_patterns(): array
{
    if (!is_file(SMART_404_MALICIOUS_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(SMART_404_MALICIOUS_FILE), true);
    if (!is_array($decoded)) {
        return [];
    }

    $patterns = [];
    foreach ($decoded as $pattern) {
        if (!is_array($pattern)) {
            continue;
        }

        $type = (string) ($pattern['type'] ?? '');
        $value = smart_404_normalize_map_path((string) ($pattern['value'] ?? ''));
        if (!in_array($type, smart_404_pattern_types(), true) || $value === '') {
            continue;
        }

        $patterns[] = [
            'type' => $type,
            'value' => $value,
            'note' => (string) ($pattern['note'] ?? ''),
            'created_utc' => (string) ($pattern['created_utc'] ?? ''),
        ];
    }

    usort($patterns, fn ($a, $b) => [$a['type'], $a['value']] <=> [$b['type'], $b['value']]);
    return $patterns;
}

function smart_404_save_malicious_patterns(array $patterns): bool
{
    if (!is_dir(SMART_404_DATA_DIR)) {
        mkdir(SMART_404_DATA_DIR, 0755, true);
    }

    $json = json_encode(array_values($patterns), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }

    return file_put_contents(SMART_404_MALICIOUS_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function smart_404_malicious_pattern_key(array $pattern): string
{
    return ($pattern['type'] ?? '') . ':' . ($pattern['value'] ?? '');
}

function smart_404_matches_malicious_pattern(string $path, string $type, string $value): bool
{
    $path = strtolower(smart_404_normalize_map_path($path));
    $value = strtolower(smart_404_normalize_map_path($value));
    if ($path === '' || $value === '') {
        return false;
    }

    if ($type === 'exact') {
        return $path === $value;
    }

    if ($type === 'prefix') {
        return $path === $value || str_starts_with($path, rtrim($value, '/') . '/');
    }

    if ($type === 'contains') {
        return str_contains($path, $value);
    }

    return false;
}

function smart_404_count_log_matches(string $source): int
{
    if (!is_file(SMART_404_LOG_FILE)) {
        return 0;
    }

    $lines = file(SMART_404_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return 0;
    }

    $count = 0;
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $path = smart_404_normalize_map_path((string) ($decoded['path'] ?? ''));
        if ($path === $source) {
            $count++;
        }
    }

    return $count;
}

function smart_404_count_malicious_log_matches(string $type, string $value): int
{
    if (!is_file(SMART_404_LOG_FILE)) {
        return 0;
    }

    $lines = file(SMART_404_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return 0;
    }

    $count = 0;
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }

        if (smart_404_matches_malicious_pattern((string) ($decoded['path'] ?? ''), $type, $value)) {
            $count++;
        }
    }

    return $count;
}

function smart_404_remove_log_matches(string $source): int|false
{
    if (!is_file(SMART_404_LOG_FILE)) {
        return 0;
    }

    $lines = file(SMART_404_LOG_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    $kept = [];
    $removed = 0;
    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            $kept[] = $line;
            continue;
        }

        $path = smart_404_normalize_map_path((string) ($decoded['path'] ?? ''));
        if ($path === $source) {
            $removed++;
            continue;
        }

        $kept[] = $line;
    }

    $content = $kept ? implode(PHP_EOL, $kept) . PHP_EOL : '';
    return file_put_contents(SMART_404_LOG_FILE, $content, LOCK_EX) === false ? false : $removed;
}

function smart_404_remove_malicious_log_matches(string $type, string $value): int|false
{
    if (!is_file(SMART_404_LOG_FILE)) {
        return 0;
    }

    $lines = file(SMART_404_LOG_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    $kept = [];
    $removed = 0;
    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            $kept[] = $line;
            continue;
        }

        if (smart_404_matches_malicious_pattern((string) ($decoded['path'] ?? ''), $type, $value)) {
            $removed++;
            continue;
        }

        $kept[] = $line;
    }

    $content = $kept ? implode(PHP_EOL, $kept) . PHP_EOL : '';
    return file_put_contents(SMART_404_LOG_FILE, $content, LOCK_EX) === false ? false : $removed;
}

function smart_404_matches_filter(array $entry, string $filter): bool
{
    if ($filter === '') {
        return true;
    }

    $haystack = strtolower(implode(' ', $entry));
    return str_contains($haystack, strtolower($filter));
}

$passwordHash = smart_404_admin_password_hash();
$configured = $passwordHash !== '';
$error = '';
$notice = '';

if (isset($_POST['logout'])) {
    $_SESSION[SMART_404_SESSION_KEY] = false;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/smart-404-admin.php', '?'));
    exit;
}

if (isset($_POST['password']) && $configured) {
    if (password_verify((string) $_POST['password'], $passwordHash)) {
        $_SESSION[SMART_404_SESSION_KEY] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/smart-404-admin.php', '?'));
        exit;
    }

    $error = 'Invalid password.';
}

$authenticated = $configured && (($_SESSION[SMART_404_SESSION_KEY] ?? false) === true);

if ($authenticated && isset($_POST['map_action'])) {
    $map = smart_404_load_map();
    $source = smart_404_normalize_map_path((string) ($_POST['source'] ?? ''));
    $target = trim((string) ($_POST['target'] ?? ''));

    if ($_POST['map_action'] === 'delete') {
        if ($source === '' || !array_key_exists($source, $map)) {
            $error = 'Map entry was not found.';
        } else {
            unset($map[$source]);
            $notice = smart_404_save_map($map) ? 'Map deleted.' : 'Unable to save map file.';
        }
    } else {
        if ($source === '') {
            $error = 'Source path is required.';
        } elseif (!smart_404_is_safe_target($target)) {
            $error = 'Target must start with /.';
        } else {
            $map[$source] = $target;
            if (smart_404_save_map($map)) {
                $matchingLogs = smart_404_count_log_matches($source);
                if (isset($_POST['remove_matching_logs'])) {
                    $removedLogs = smart_404_remove_log_matches($source);
                    $notice = $removedLogs === false
                        ? 'Map saved, but matching log entries could not be removed.'
                        : 'Map saved. Removed ' . $removedLogs . ' matching log entr' . ($removedLogs === 1 ? 'y.' : 'ies.');
                } elseif ($matchingLogs > 0) {
                    $notice = 'Map saved. ' . $matchingLogs . ' existing log entr' . ($matchingLogs === 1 ? 'y matches' : 'ies match') . ' this source.';
                } else {
                    $notice = 'Map saved.';
                }
            } else {
                $notice = 'Unable to save map file.';
            }
        }
    }
}

if ($authenticated && isset($_POST['malicious_action'])) {
    $patterns = smart_404_load_malicious_patterns();
    $type = (string) ($_POST['malicious_type'] ?? 'exact');
    $value = smart_404_normalize_map_path((string) ($_POST['malicious_value'] ?? ''));
    $note = trim((string) ($_POST['malicious_note'] ?? ''));

    if ($_POST['malicious_action'] === 'delete') {
        $before = count($patterns);
        $patterns = array_values(array_filter(
            $patterns,
            fn ($pattern) => smart_404_malicious_pattern_key($pattern) !== $type . ':' . $value
        ));

        if ($value === '' || count($patterns) === $before) {
            $error = 'Malicious pattern was not found.';
        } else {
            $notice = smart_404_save_malicious_patterns($patterns) ? 'Malicious pattern deleted.' : 'Unable to save malicious pattern file.';
        }
    } else {
        if (!in_array($type, smart_404_pattern_types(), true)) {
            $error = 'Malicious pattern type is invalid.';
        } elseif ($value === '') {
            $error = 'Malicious pattern value is required.';
        } else {
            $pattern = [
                'type' => $type,
                'value' => $value,
                'note' => $note,
                'created_utc' => gmdate('c'),
            ];
            $patterns = array_values(array_filter(
                $patterns,
                fn ($existing) => smart_404_malicious_pattern_key($existing) !== smart_404_malicious_pattern_key($pattern)
            ));
            $patterns[] = $pattern;

            if (smart_404_save_malicious_patterns($patterns)) {
                $matchingLogs = smart_404_count_malicious_log_matches($type, $value);
                if (isset($_POST['remove_matching_logs'])) {
                    $removedLogs = smart_404_remove_malicious_log_matches($type, $value);
                    $notice = $removedLogs === false
                        ? 'Malicious pattern saved, but matching log entries could not be removed.'
                        : 'Malicious pattern saved. Removed ' . $removedLogs . ' matching log entr' . ($removedLogs === 1 ? 'y.' : 'ies.');
                } elseif ($matchingLogs > 0) {
                    $notice = 'Malicious pattern saved. ' . $matchingLogs . ' existing log entr' . ($matchingLogs === 1 ? 'y matches' : 'ies match') . ' this pattern.';
                } else {
                    $notice = 'Malicious pattern saved.';
                }
            } else {
                $notice = 'Unable to save malicious pattern file.';
            }
        }
    }
}

$filter = trim((string) ($_GET['q'] ?? ''));
$limit = max(25, min(1000, (int) ($_GET['limit'] ?? 250)));
$entries = $authenticated ? smart_404_read_log() : [];
$maps = $authenticated ? smart_404_load_map() : [];
$maliciousPatterns = $authenticated ? smart_404_load_malicious_patterns() : [];
$filtered = $authenticated ? array_values(array_filter($entries, fn ($entry) => smart_404_matches_filter($entry, $filter))) : [];
$shown = array_slice($filtered, 0, $limit);
$mappedCount = count(array_filter($entries, fn ($entry) => ($entry['mapped_to'] ?? '') !== ''));
$uniquePaths = count(array_unique(array_map(fn ($entry) => $entry['path'] ?? '', $entries)));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart 404 Admin</title>
    <style>
        :root { color-scheme: light dark; font-family: Arial, sans-serif; }
        body { margin: 0; background: #f5f7fa; color: #18202a; }
        header { background: #1f6f8b; color: #fff; padding: 16px 24px; }
        main { padding: 20px 24px; }
        form { margin: 0; }
        input, button, select { font: inherit; padding: 8px 10px; }
        button { cursor: pointer; }
        .login, .panel { background: #fff; padding: 16px; border: 1px solid #ccd5df; margin-bottom: 16px; }
        .login { max-width: 420px; }
        .error { color: #a40000; margin: 10px 0; }
        .notice { color: #176b36; margin: 10px 0; }
        .stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .stat { background: #fff; border: 1px solid #ccd5df; padding: 12px; min-width: 130px; }
        .stat strong { display: block; font-size: 22px; }
        .toolbar, .map-form { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; align-items: center; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #ccd5df; margin-bottom: 16px; }
        th, td { border-bottom: 1px solid #dfe5eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #edf3f8; position: sticky; top: 0; }
        td { font-size: 13px; }
        code { background: #edf3f8; padding: 2px 4px; }
        .muted { color: #657384; }
        .wide { min-width: 260px; }
        .source-input { min-width: 180px; }
        .target-input { min-width: 320px; }
        @media (prefers-color-scheme: dark) {
            body { background: #10151b; color: #e8edf2; }
            .login, .panel, .stat, table { background: #161d25; border-color: #2d3a47; }
            th { background: #202a34; }
            th, td { border-color: #2d3a47; }
            code { background: #202a34; }
            .muted { color: #aab4bf; }
            .notice { color: #7bd99a; }
            .error { color: #ff8b8b; }
        }
    </style>
</head>
<body>
<header>
    <h1>Smart 404 Admin</h1>
</header>
<main>
<?php if (!$configured): ?>
    <section class="login">
        <h2>Password Required</h2>
        <p>Create <code>smart-404-data/smart-404-admin-password.php</code> using the sample file before this admin page can be used.</p>
    </section>
<?php elseif (!$authenticated): ?>
    <section class="login">
        <h2>Sign In</h2>
        <?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <p><input type="password" name="password" placeholder="Password" autocomplete="current-password" required></p>
            <p><button type="submit">Sign in</button></p>
        </form>
    </section>
<?php else: ?>
    <?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <?php if ($notice !== ''): ?><p class="notice"><?= h($notice) ?></p><?php endif; ?>

    <div class="toolbar">
        <form method="get">
            <input type="search" name="q" value="<?= h($filter) ?>" placeholder="Filter path, IP, referrer, user agent">
            <select name="limit">
                <?php foreach ([100, 250, 500, 1000] as $option): ?>
                    <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
        </form>
        <form method="post">
            <button type="submit" name="logout" value="1">Sign out</button>
        </form>
    </div>

    <section class="stats">
        <div class="stat"><strong><?= count($entries) ?></strong><span>Total</span></div>
        <div class="stat"><strong><?= $uniquePaths ?></strong><span>Unique paths</span></div>
        <div class="stat"><strong><?= $mappedCount ?></strong><span>Mapped hits</span></div>
        <div class="stat"><strong><?= count($maps) ?></strong><span>Manual maps</span></div>
        <div class="stat"><strong><?= count($maliciousPatterns) ?></strong><span>Malicious patterns</span></div>
        <div class="stat"><strong><?= count($filtered) ?></strong><span>Filtered</span></div>
    </section>

    <section class="panel">
        <h2>Manual Maps</h2>
        <form method="post" class="map-form">
            <input class="source-input" name="source" placeholder="source path, e.g. mpg" required>
            <input class="target-input" name="target" placeholder="target, e.g. /github/mpg/" required>
            <label><input type="checkbox" name="remove_matching_logs" value="1"> Remove matching log entries</label>
            <button type="submit" name="map_action" value="save">Add or update</button>
        </form>
        <table>
            <thead><tr><th>Source</th><th>Target</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($maps as $source => $target): ?>
                <tr>
                    <td><code><?= h($source) ?></code></td>
                    <td><code><?= h($target) ?></code></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="source" value="<?= h($source) ?>">
                            <button type="submit" name="map_action" value="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$maps): ?>
                <tr><td colspan="3" class="muted">No manual maps configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Malicious Patterns</h2>
        <form method="post" class="map-form">
            <select name="malicious_type">
                <?php foreach (smart_404_pattern_types() as $option): ?>
                    <option value="<?= h($option) ?>"><?= h(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="source-input" name="malicious_value" placeholder="path or pattern, e.g. wp-admin" required>
            <input class="target-input" name="malicious_note" placeholder="optional note">
            <label><input type="checkbox" name="remove_matching_logs" value="1"> Remove matching log entries</label>
            <button type="submit" name="malicious_action" value="save">Add or update</button>
        </form>
        <table>
            <thead><tr><th>Type</th><th>Value</th><th>Note</th><th>Created UTC</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($maliciousPatterns as $pattern): ?>
                <tr>
                    <td><code><?= h($pattern['type']) ?></code></td>
                    <td><code><?= h($pattern['value']) ?></code></td>
                    <td><?= h($pattern['note']) ?></td>
                    <td><?= h($pattern['created_utc']) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="malicious_type" value="<?= h($pattern['type']) ?>">
                            <input type="hidden" name="malicious_value" value="<?= h($pattern['value']) ?>">
                            <button type="submit" name="malicious_action" value="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$maliciousPatterns): ?>
                <tr><td colspan="5" class="muted">No malicious patterns configured.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <table>
        <thead>
            <tr>
                <th>Time UTC</th>
                <th>Path</th>
                <th>Map</th>
                <th>Malicious</th>
                <th>Query</th>
                <th>Mapped To</th>
                <th>Matched By</th>
                <th>IP</th>
                <th class="wide">Referrer</th>
                <th class="wide">User Agent</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shown as $entry): ?>
            <tr>
                <td><?= h($entry['time_utc']) ?></td>
                <td><code><?= h($entry['path']) ?></code></td>
                <td>
                    <form method="post" class="map-form">
                        <input type="hidden" name="source" value="<?= h($entry['path']) ?>">
                        <input class="target-input" name="target" placeholder="/github/example/" required>
                        <label><input type="checkbox" name="remove_matching_logs" value="1"> Remove matches</label>
                        <button type="submit" name="map_action" value="save">Map this</button>
                    </form>
                </td>
                <td>
                    <form method="post" class="map-form">
                        <select name="malicious_type">
                            <option value="exact">Exact</option>
                            <option value="prefix">Prefix</option>
                            <option value="contains">Contains</option>
                        </select>
                        <input class="source-input" name="malicious_value" value="<?= h($entry['path']) ?>" required>
                        <input name="malicious_note" placeholder="optional note">
                        <label><input type="checkbox" name="remove_matching_logs" value="1"> Remove matches</label>
                        <button type="submit" name="malicious_action" value="save">Mark malicious</button>
                    </form>
                </td>
                <td><?= h($entry['query']) ?></td>
                <td><?= h($entry['mapped_to']) ?></td>
                <td><?= h($entry['matched_by']) ?></td>
                <td><?= h($entry['ip']) ?></td>
                <td><?= h($entry['referer']) ?></td>
                <td><?= h($entry['user_agent']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$shown): ?>
            <tr><td colspan="10" class="muted">No log entries found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>
</main>
</body>
</html>
