<?php
/*
Filename: smart-404.php
Revision: 1.2.1
Description: Logs missing jasr.me URLs, checks malicious patterns and manual mappings, suggests close /github/ matches, and displays styled 404 pages.
Author: Jason Lamb (with help from Codex CLI)
Created Date: 2026-05-18
Modified Date: 2026-06-18
Changelog:
1.0.0 initial release with logging, manual mappings, and fuzzy /github matching
1.0.1 log YOURLS ?missing= keywords as the requested path
1.1.0 replace plain-text 404 output with a styled HTML error page
1.1.1 update GitHub browse button to https://jasr.me/gh
1.1.2 update GitHub browse button label
1.1.3 add a retry link for the requested missing URL
1.2.0 add malicious pattern routing to a separate logged 404 page
1.2.1 prevent hidden /github/. paths from fuzzy redirects
*/

declare(strict_types=1);

const SMART_404_DATA_DIR = __DIR__ . '/smart-404-data';
const SMART_404_LOG_FILE = SMART_404_DATA_DIR . '/404-requests.jsonl';
const SMART_404_MAP_FILE = SMART_404_DATA_DIR . '/smart-404-map.json';
const SMART_404_MALICIOUS_FILE = SMART_404_DATA_DIR . '/smart-404-malicious.json';
const SMART_404_GITHUB_DIR = __DIR__ . '/github';
const SMART_404_FUZZY_MAX_DISTANCE = 2;

function smart_404_normalize_path(string $path): string
{
    $path = trim($path, "/ \t\n\r\0\x0B");
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    return $path;
}

function smart_404_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function smart_404_safe_redirect(string $target): bool
{
    if ($target === '' || substr($target, 0, 2) === '//') {
        return false;
    }

    $targetPath = parse_url($target, PHP_URL_PATH) ?: '';
    if (preg_match('#^/github/(?:.*/)?\.#', $targetPath)) {
        return false;
    }

    if (preg_match('#^https?://#i', $target)) {
        return false;
    }

    header('Location: ' . $target, true, 302);
    return true;
}

function smart_404_path_href(string $requested): string
{
    if ($requested === '') {
        return '/';
    }

    $parts = array_map('rawurlencode', explode('/', $requested));
    return '/' . implode('/', $parts);
}

function smart_404_log_request(string $requestedPath, string $mappedTo = '', string $matchedBy = ''): void
{
    if (!is_dir(SMART_404_DATA_DIR)) {
        mkdir(SMART_404_DATA_DIR, 0755, true);
    }

    $entry = [
        'time_utc' => gmdate('c'),
        'path' => $requestedPath,
        'query' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '',
        'mapped_to' => $mappedTo,
        'matched_by' => $matchedBy,
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    file_put_contents(
        SMART_404_LOG_FILE,
        json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function smart_404_load_map(): array
{
    if (!is_file(SMART_404_MAP_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(SMART_404_MAP_FILE), true);
    return is_array($decoded) ? $decoded : [];
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
        $value = smart_404_normalize_path((string) ($pattern['value'] ?? ''));
        if (!in_array($type, ['exact', 'prefix', 'contains'], true) || $value === '') {
            continue;
        }

        $patterns[] = [
            'type' => $type,
            'value' => $value,
            'note' => (string) ($pattern['note'] ?? ''),
            'created_utc' => (string) ($pattern['created_utc'] ?? ''),
        ];
    }

    return $patterns;
}

function smart_404_matches_malicious_pattern(string $requestedPath, array $pattern): bool
{
    $requested = strtolower(smart_404_normalize_path($requestedPath));
    $value = strtolower(smart_404_normalize_path((string) ($pattern['value'] ?? '')));
    if ($requested === '' || $value === '') {
        return false;
    }

    $type = (string) ($pattern['type'] ?? '');
    if ($type === 'exact') {
        return $requested === $value;
    }

    if ($type === 'prefix') {
        return $requested === $value || str_starts_with($requested, rtrim($value, '/') . '/');
    }

    if ($type === 'contains') {
        return str_contains($requested, $value);
    }

    return false;
}

function smart_404_find_malicious_match(string $requestedPath): ?array
{
    foreach (smart_404_load_malicious_patterns() as $pattern) {
        if (smart_404_matches_malicious_pattern($requestedPath, $pattern)) {
            return $pattern;
        }
    }

    return null;
}

function smart_404_find_github_match(string $requestedPath): ?string
{
    if ($requestedPath === '' || !is_dir(SMART_404_GITHUB_DIR)) {
        return null;
    }

    $firstSegment = explode('/', $requestedPath)[0] ?? '';
    if ($firstSegment === '') {
        return null;
    }

    $items = scandir(SMART_404_GITHUB_DIR);
    if ($items === false) {
        return null;
    }

    $best = null;
    $bestDistance = PHP_INT_MAX;
    $needle = strtolower($firstSegment);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
            continue;
        }

        $fullPath = SMART_404_GITHUB_DIR . '/' . $item;
        if (!is_dir($fullPath) && !is_file($fullPath)) {
            continue;
        }

        $distance = levenshtein($needle, strtolower($item));
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $item;
        }
    }

    if ($best === null || $bestDistance > SMART_404_FUZZY_MAX_DISTANCE) {
        return null;
    }

    $suffix = '';
    $parts = explode('/', $requestedPath, 2);
    if (isset($parts[1]) && $parts[1] !== '') {
        $suffix = '/' . $parts[1];
    }

    return '/github/' . rawurlencode($best) . $suffix;
}

function smart_404_render_page(string $requested): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $displayPath = $requested !== '' ? '/' . $requested : '/';
    $retryHref = smart_404_path_href($requested);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found | jasr.me</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f4f7f9;
            --panel: #ffffff;
            --text: #1b2630;
            --muted: #607180;
            --line: #cfd9e3;
            --accent: #1f6f8b;
            --accent-strong: #124963;
            --soft: #e8f2f6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        main {
            width: min(760px, 100%);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 18px 45px rgba(20, 44, 60, 0.12);
            overflow: hidden;
        }
        .topbar {
            height: 8px;
            background: var(--accent);
        }
        .content {
            padding: clamp(24px, 5vw, 44px);
        }
        .eyebrow {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent-strong);
            margin-bottom: 14px;
        }
        h1 {
            font-size: clamp(34px, 8vw, 68px);
            line-height: 0.95;
            margin: 0 0 18px;
            letter-spacing: 0;
        }
        p {
            font-size: 17px;
            line-height: 1.55;
            margin: 0 0 18px;
            color: var(--muted);
        }
        code {
            display: inline-block;
            max-width: 100%;
            overflow-wrap: anywhere;
            background: var(--soft);
            color: var(--accent-strong);
            padding: 4px 7px;
            border-radius: 6px;
            font-size: 15px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }
        a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid var(--accent);
        }
        a.primary {
            background: var(--accent);
            color: #fff;
        }
        a.secondary {
            background: transparent;
            color: var(--accent-strong);
        }
        .meta {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            font-size: 13px;
            color: var(--muted);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #10161d;
                --panel: #17202a;
                --text: #edf3f7;
                --muted: #a9b7c3;
                --line: #2e3b48;
                --accent: #43a6c6;
                --accent-strong: #8bd4ea;
                --soft: #20303b;
            }
            main { box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28); }
            a.primary { color: #071117; }
        }
    </style>
</head>
<body>
    <main>
        <div class="topbar"></div>
        <section class="content">
            <span class="eyebrow">404 Not Found</span>
            <h1>That page is not here.</h1>
            <p>The requested path <code><?= smart_404_escape($displayPath) ?></code> could not be matched to a shortlink or site file.</p>
            <div class="actions">
                <a class="button primary" href="<?= smart_404_escape($retryHref) ?>">Try this page again</a>
                <a class="button secondary" href="/">Go to jasr.me</a>
                <a class="button secondary" href="https://jasr.me/gh">Browse GitHub Repos @jasrasr</a>
            </div>
            <div class="meta">This missing path has been logged for review.</div>
        </section>
    </main>
</body>
</html>
    <?php
}

function smart_404_render_malicious_page(string $requested): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $displayPath = $requested !== '' ? '/' . $requested : '/';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Logged | jasr.me</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #111820;
            --panel: #18222c;
            --text: #edf3f7;
            --muted: #aebac5;
            --line: #364553;
            --accent: #d95f45;
            --soft: #2a2020;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        main {
            width: min(720px, 100%);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
            overflow: hidden;
        }
        .topbar { height: 8px; background: var(--accent); }
        .content { padding: clamp(24px, 5vw, 44px); }
        .eyebrow {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }
        h1 {
            font-size: clamp(34px, 7vw, 58px);
            line-height: 1;
            margin: 0 0 18px;
            letter-spacing: 0;
        }
        p {
            font-size: 17px;
            line-height: 1.55;
            margin: 0 0 18px;
            color: var(--muted);
        }
        code {
            display: inline-block;
            max-width: 100%;
            overflow-wrap: anywhere;
            background: var(--soft);
            color: #ffb19f;
            padding: 4px 7px;
            border-radius: 6px;
            font-size: 15px;
        }
        .meta {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            font-size: 13px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main>
        <div class="topbar"></div>
        <section class="content">
            <span class="eyebrow">404 Not Found</span>
            <h1>This request has been logged.</h1>
            <p>The requested path <code><?= smart_404_escape($displayPath) ?></code> is not available.</p>
            <div class="meta">Repeated automated probing may be reviewed by the site owner.</div>
        </section>
    </main>
</body>
</html>
    <?php
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$missing = isset($_GET['missing']) && is_string($_GET['missing']) ? $_GET['missing'] : '';
$requested = smart_404_normalize_path($missing !== '' ? $missing : $requestPath);

$maliciousMatch = smart_404_find_malicious_match($requested);
if ($maliciousMatch !== null) {
    $matchedBy = 'malicious-' . ($maliciousMatch['type'] ?? 'pattern') . ':' . ($maliciousMatch['value'] ?? '');
    smart_404_log_request($requested, '', $matchedBy);
    smart_404_render_malicious_page($requested);
    exit;
}

$map = smart_404_load_map();
if (isset($map[$requested]) && is_string($map[$requested])) {
    smart_404_log_request($requested, $map[$requested], 'manual-map');
    if (smart_404_safe_redirect($map[$requested])) {
        exit;
    }
}

$fuzzyTarget = smart_404_find_github_match($requested);
if ($fuzzyTarget !== null) {
    smart_404_log_request($requested, $fuzzyTarget, 'github-fuzzy');
    if (smart_404_safe_redirect($fuzzyTarget)) {
        exit;
    }
}

smart_404_log_request($requested);
smart_404_render_page($requested);
