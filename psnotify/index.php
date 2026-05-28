<?php
/*
Filename : index.php
Revision : 1.3
Description : Mobile-friendly PSNotify viewer and setup page for self-hosted PowerShell notifications, updated to prioritize newest messages at the top of the page.
Author : Jason Lamb (with help from ChatGPT)
Created Date : 2026-03-20
Modified Date : 2026-05-27
Changelog :
1.0 initial release
1.1 moved messages to the top of the viewer, added manual refresh, added copy PowerShell example, and pushed setup controls below the message list
1.2 standardized header and changelog format and aligned the publish example with the working direct publish.php endpoint
1.3 moved viewer key entry to POST/session auth so keys do not stay in URLs or fetch requests
*/

require_once __DIR__ . '/common.php';

$topic = psnotify_clean_topic((string) ($_GET['topic'] ?? DEFAULT_TOPIC));
$loginError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['key'])) {
    $postedTopic = psnotify_clean_topic((string) ($_POST['topic'] ?? $topic));
    $postedKey = trim((string) ($_POST['key'] ?? ''));

    if (psnotify_authorize_view_session($postedKey)) {
        header('Location: ?topic=' . rawurlencode($postedTopic));
        exit;
    }

    $topic = $postedTopic;
    $loginError = 'Invalid viewer key.';
}

$hasValidKey = psnotify_view_session_is_valid();
$publishBase = rtrim(psnotify_base_url(), '/');
$publishUrl = $publishBase . '/publish.php?topic=' . rawurlencode($topic);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111827">
    <link rel="manifest" href="manifest.webmanifest">
    <title>PSNotify</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #0f172a; color: #e5e7eb; }
        .wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
        .card { background: #111827; border: 1px solid #374151; border-radius: 14px; padding: 16px; margin-bottom: 16px; }
        h1, h2, h3, p { margin-top: 0; }
        input, button, select, textarea { width: 100%; box-sizing: border-box; border-radius: 10px; border: 1px solid #4b5563; background: #0b1220; color: #f9fafb; padding: 10px 12px; }
        button { background: #2563eb; border: 0; cursor: pointer; }
        button.secondary { background: #334155; }
        .grid { display: grid; gap: 12px; }
        .grid.two { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .muted { color: #94a3b8; font-size: 0.95rem; }
        .mono { font-family: Consolas, Menlo, monospace; white-space: pre-wrap; word-break: break-word; }
        .item { border-left: 6px solid #64748b; background: #0b1220; border-radius: 10px; padding: 12px; margin-top: 12px; }
        .item:first-child { margin-top: 0; }
        .item.high { border-left-color: #f59e0b; }
        .item.max { border-left-color: #ef4444; }
        .item.low, .item.min { border-left-color: #22c55e; }
        .pill { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #1f2937; color: #cbd5e1; font-size: 12px; margin-right: 6px; margin-bottom: 6px; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; }
        .row > button { width: auto; min-width: 140px; }
        .right { text-align: right; }
        .hidden { display: none; }
        .tight { margin-bottom: 8px; }
        a { color: #93c5fd; }
    </style>
</head>
<body>
<div class="wrap">
    <?php if (!$hasValidKey): ?>
        <div class="card">
            <h1>PSNotify</h1>
            <p class="muted">Viewer key required.</p>
            <?php if ($loginError !== ''): ?>
                <p style="color: #fca5a5;"><?= htmlspecialchars($loginError) ?></p>
            <?php endif; ?>
            <form method="post" class="grid two">
                <div>
                    <label for="topic">Topic</label>
                    <input id="topic" name="topic" value="<?= htmlspecialchars($topic) ?>">
                </div>
                <div>
                    <label for="key">View key</label>
                    <input id="key" name="key" type="password" autocomplete="off">
                </div>
                <div style="grid-column: 1 / -1;">
                    <button type="submit">Open viewer</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="row" style="justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="tight">Messages</h1>
                    <div class="muted" id="statusText">Waiting for messages...</div>
                </div>
                <div class="right muted" id="serverTime"></div>
            </div>
            <div class="muted" style="margin-bottom: 12px;">Newest messages appear first for topic <span class="mono" id="activeTopicLabel"><?= htmlspecialchars($topic) ?></span>.</div>
            <div id="items"></div>
        </div>

        <div class="card">
            <h2>Viewer tools</h2>
            <div class="grid two">
                <div>
                    <label for="topicInput">Topic</label>
                    <input id="topicInput" value="<?= htmlspecialchars($topic) ?>">
                </div>
                <div>
                    <label for="refreshSeconds">Refresh every</label>
                    <select id="refreshSeconds">
                        <option value="3">3 seconds</option>
                        <option value="5" selected>5 seconds</option>
                        <option value="10">10 seconds</option>
                        <option value="30">30 seconds</option>
                    </select>
                </div>
            </div>
            <div class="row" style="margin-top: 12px;">
                <button id="refreshNowBtn">Refresh now</button>
                <button id="openTopic">Load topic</button>
                <button id="notifyBtn" class="secondary">Enable browser alerts</button>
                <button id="copyUrlBtn" class="secondary">Copy publish URL</button>
                <button id="copyPsBtn" class="secondary">Copy PowerShell example</button>
            </div>
            <p class="muted" style="margin-top: 12px;">Current publish URL</p>
            <div class="mono" id="publishUrlText"><?= htmlspecialchars($publishUrl) ?></div>

            <h2 style="margin-top: 16px;">PowerShell example</h2>
            <div class="mono" id="psExample"></div>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasValidKey): ?>
<script>
let currentTopic = <?= json_encode($topic) ?>;
let lastSeenId = '';
let timerHandle = null;
let firstLoad = true;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function buildPublishUrl(topic) {
    const base = <?= json_encode($publishBase) ?>;
    return `${base}/publish.php?topic=${encodeURIComponent(topic)}`;
}

function buildPowerShellExample(topic) {
    return [
        "$topic = '" + topic + "'",
        "$message = 'Job finished on ' + $env:COMPUTERNAME",
        "Invoke-RestMethod `",
        "    -Uri '" + buildPublishUrl(topic) + "' `",
        "    -Method Post `",
        "    -Headers @{ 'X-PSNotify-Token' = 'REPLACE_WITH_PUBLISH_TOKEN'; Title = 'Job Complete'; Priority = 'high'; Tags = 'white_check_mark,computer' } `",
        "    -Body $message"
    ].join("\n");
}

function updateUiForTopic() {
    const publishUrl = buildPublishUrl(currentTopic);
    document.getElementById('publishUrlText').textContent = publishUrl;
    document.getElementById('psExample').textContent = buildPowerShellExample(currentTopic);
    document.getElementById('activeTopicLabel').textContent = currentTopic;
}

function showBrowserNotification(item) {
    if (!('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    const title = item.title && item.title.trim() !== '' ? item.title : `PSNotify - ${item.topic}`;
    const body = item.message.length > 160 ? item.message.substring(0, 157) + '...' : item.message;

    try {
        new Notification(title, { body });
    } catch (error) {
        console.log(error);
    }
}

function renderItems(items) {
    const container = document.getElementById('items');
    container.innerHTML = '';

    if (!items.length) {
        container.innerHTML = '<p class="muted">No messages yet for this topic.</p>';
        return;
    }

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = `item ${escapeHtml(item.priority || 'default')}`;
        div.innerHTML = `
            <div class="row" style="justify-content: space-between; align-items: center;">
                <strong>${escapeHtml(item.title || item.topic)}</strong>
                <span class="pill">${escapeHtml(item.priority || 'default')}</span>
            </div>
            <div class="muted">${escapeHtml(item.created_local || '')} · ${escapeHtml(item.topic || '')}</div>
            <p style="margin: 10px 0 0 0; white-space: pre-wrap;">${escapeHtml(item.message || '')}</p>
            <div style="margin-top: 10px;">${(item.tags || []).map(tag => `<span class="pill">${escapeHtml(tag)}</span>`).join('')}</div>
        `;
        container.appendChild(div);
    });
}

async function loadMessages() {
    const params = new URLSearchParams({ topic: currentTopic, limit: '50' });
    const response = await fetch(`fetch.php?${params.toString()}`, { cache: 'no-store' });
    const data = await response.json();

    if (!data.ok) {
        document.getElementById('statusText').textContent = data.error || 'Unable to load messages.';
        return;
    }

    document.getElementById('statusText').textContent = `Loaded ${data.count} message(s) for ${currentTopic}`;
    document.getElementById('serverTime').textContent = data.server_time || '';

    const items = data.items || [];
    renderItems(items);

    if (items.length > 0) {
        const newestId = items[0].id || '';
        if (!firstLoad && newestId !== '' && newestId !== lastSeenId) {
            showBrowserNotification(items[0]);
        }
        lastSeenId = newestId;
    }

    firstLoad = false;
}

function restartPolling() {
    if (timerHandle) {
        clearInterval(timerHandle);
    }

    loadMessages().catch(error => {
        document.getElementById('statusText').textContent = 'Load failed. ' + error;
    });

    const seconds = parseInt(document.getElementById('refreshSeconds').value, 10);
    timerHandle = setInterval(() => {
        loadMessages().catch(error => {
            document.getElementById('statusText').textContent = 'Load failed. ' + error;
        });
    }, seconds * 1000);
}

document.getElementById('refreshNowBtn').addEventListener('click', () => {
    loadMessages().catch(error => {
        document.getElementById('statusText').textContent = 'Load failed. ' + error;
    });
});

document.getElementById('openTopic').addEventListener('click', () => {
    currentTopic = document.getElementById('topicInput').value.trim() || <?= json_encode(DEFAULT_TOPIC) ?>;
    firstLoad = true;
    lastSeenId = '';
    updateUiForTopic();
    restartPolling();

    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.set('topic', currentTopic);
    history.replaceState({}, '', nextUrl.toString());
});

document.getElementById('refreshSeconds').addEventListener('change', restartPolling);

document.getElementById('notifyBtn').addEventListener('click', async () => {
    if (!('Notification' in window)) {
        alert('This browser does not support notifications.');
        return;
    }

    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
        alert('Browser alerts enabled.');
    } else {
        alert('Browser alerts were not allowed.');
    }
});

document.getElementById('copyUrlBtn').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(document.getElementById('publishUrlText').textContent);
        alert('Publish URL copied.');
    } catch (error) {
        alert('Unable to copy publish URL.');
    }
});

document.getElementById('copyPsBtn').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(document.getElementById('psExample').textContent);
        alert('PowerShell example copied.');
    } catch (error) {
        alert('Unable to copy PowerShell example.');
    }
});

updateUiForTopic();
restartPolling();
</script>
<?php endif; ?>
</body>
</html>
