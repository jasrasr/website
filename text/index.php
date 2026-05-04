<?php
/*
File Name   : index.php
Project     : /text
Author      : Jason Lamb (with help from ChatGPT)
Created     : 2026-05-04
Revision    : 1.0

Purpose:
- Server-backed scratch pad for editing text and retrieving it from another device.
- Stores the current text in data/text-copy.json on the web server.
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

const TEXT_COPY_DATA_DIR = __DIR__ . '/data';
const TEXT_COPY_DATA_FILE = TEXT_COPY_DATA_DIR . '/text-copy.json';
const TEXT_COPY_MAX_BYTES = 500000;

function ensureTextCopyDataFile(): void
{
    if (!is_dir(TEXT_COPY_DATA_DIR)) {
        mkdir(TEXT_COPY_DATA_DIR, 0755, true);
    }

    if (!file_exists(TEXT_COPY_DATA_FILE)) {
        saveTextCopyData('');
    }
}

function loadTextCopyData(): array
{
    ensureTextCopyDataFile();
    $json = file_get_contents(TEXT_COPY_DATA_FILE);
    $data = json_decode($json ?: '{}', true);

    if (!is_array($data)) {
        return ['text' => '', 'updated_at' => null, 'bytes' => 0];
    }

    return [
        'text' => (string)($data['text'] ?? ''),
        'updated_at' => $data['updated_at'] ?? null,
        'bytes' => (int)($data['bytes'] ?? strlen((string)($data['text'] ?? ''))),
    ];
}

function saveTextCopyData(string $text): bool
{
    if (!is_dir(TEXT_COPY_DATA_DIR)) {
        mkdir(TEXT_COPY_DATA_DIR, 0755, true);
    }

    $payload = [
        'text' => $text,
        'updated_at' => date('c'),
        'bytes' => strlen($text),
    ];

    $fp = fopen(TEXT_COPY_DATA_FILE, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) {
            fclose($fp);
        }
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

function sendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'load') {
    sendJson(['ok' => true, 'data' => loadTextCopyData()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $text = (string)($_POST['text'] ?? '');

    if (strlen($text) > TEXT_COPY_MAX_BYTES) {
        sendJson(['ok' => false, 'error' => 'Text is too large.'], 413);
    }

    if (!saveTextCopyData($text)) {
        sendJson(['ok' => false, 'error' => 'Could not save text.'], 500);
    }

    sendJson(['ok' => true, 'data' => loadTextCopyData()]);
}

$initialData = loadTextCopyData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Edit and Copy Text</title>
    <style>
        :root {
            --bg: #f3eadf;
            --ink: #201813;
            --muted: #6f6258;
            --panel: #fffaf3;
            --line: #d8c6b5;
            --primary: #0f5c5c;
            --primary-dark: #0a4646;
            --danger: #a73722;
            --shadow: 0 22px 70px rgba(74, 49, 28, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, rgba(15, 92, 92, 0.18), transparent 30rem),
                linear-gradient(135deg, #f8f0e7 0%, var(--bg) 45%, #e5d3c1 100%);
        }

        main {
            width: min(1100px, calc(100% - 32px));
            margin: 0 auto;
            padding: 36px 0;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: end;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.2rem, 7vw, 5.8rem);
            line-height: 0.9;
            letter-spacing: -0.06em;
        }

        .subtitle {
            max-width: 430px;
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.45;
        }

        .panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 26px;
            background: rgba(255, 250, 243, 0.92);
            box-shadow: var(--shadow);
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 14px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.46);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        button {
            border: 0;
            border-radius: 999px;
            padding: 11px 18px;
            color: #fff;
            background: var(--primary);
            font: 700 0.95rem/1.1 Arial, sans-serif;
            cursor: pointer;
            transition: transform 0.12s ease, background 0.12s ease;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        button.secondary {
            color: var(--ink);
            background: #ead8c6;
        }

        button.secondary:hover {
            background: #ddc6b0;
        }

        button.danger {
            background: var(--danger);
        }

        .status {
            color: var(--muted);
            font: 700 0.86rem/1.3 Arial, sans-serif;
            text-align: right;
        }

        textarea {
            display: block;
            width: 100%;
            min-height: 58vh;
            resize: vertical;
            border: 0;
            outline: 0;
            padding: 24px;
            color: var(--ink);
            background: transparent;
            font: 1.12rem/1.6 Consolas, "Courier New", monospace;
        }

        textarea::placeholder {
            color: #9b8978;
        }

        .toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            transform: translateY(18px);
            opacity: 0;
            pointer-events: none;
            border-radius: 999px;
            padding: 12px 18px;
            color: #fff;
            background: var(--primary);
            font: 700 0.95rem/1 Arial, sans-serif;
            box-shadow: 0 12px 30px rgba(10, 70, 70, 0.24);
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media (max-width: 720px) {
            main {
                width: min(100% - 20px, 1100px);
                padding: 18px 0;
            }

            .hero {
                display: block;
            }

            .subtitle {
                margin-top: 12px;
            }

            .toolbar {
                align-items: stretch;
            }

            .actions,
            .toolbar,
            button {
                width: 100%;
            }

            .status {
                text-align: left;
            }

            textarea {
                min-height: 62vh;
                padding: 18px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <main>
        <section class="hero" aria-labelledby="page-title">
            <h1 id="page-title">Shared<br>Text</h1>
            <p class="subtitle">Edit text on one computer, save it to the server, then open this same page from another computer and load or copy it.</p>
        </section>

        <section class="panel" aria-label="Shared text editor">
            <div class="toolbar">
                <div class="actions">
                    <button id="saveBtn" type="button">Save Shared Text</button>
                    <button id="loadBtn" class="secondary" type="button">Load Latest</button>
                    <button id="copyBtn" class="secondary" type="button">Copy Text</button>
                    <button id="selectBtn" class="secondary" type="button">Select All</button>
                    <button id="clearBtn" class="danger" type="button">Clear</button>
                </div>
                <div id="status" class="status">Loading...</div>
            </div>

            <textarea id="textInput" spellcheck="true" placeholder="Type or paste text here..."><?php echo htmlspecialchars($initialData['text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </section>
    </main>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <script>
        const initialData = <?php echo json_encode($initialData, JSON_UNESCAPED_SLASHES); ?>;
        const textInput = document.getElementById('textInput');
        const status = document.getElementById('status');
        const toast = document.getElementById('toast');
        const saveBtn = document.getElementById('saveBtn');
        const loadBtn = document.getElementById('loadBtn');
        const copyBtn = document.getElementById('copyBtn');
        const selectBtn = document.getElementById('selectBtn');
        const clearBtn = document.getElementById('clearBtn');

        let toastTimer = null;
        let lastSavedAt = initialData.updated_at || null;

        function showToast(message) {
            clearTimeout(toastTimer);
            toast.textContent = message;
            toast.classList.add('show');
            toastTimer = setTimeout(() => toast.classList.remove('show'), 1800);
        }

        function formatSavedAt(value) {
            if (!value) {
                return 'not saved yet';
            }

            return new Date(value).toLocaleString();
        }

        function updateStatus() {
            const text = textInput.value;
            const words = text.trim() ? text.trim().split(/\s+/).length : 0;
            status.textContent = `${text.length} characters | ${words} words | saved ${formatSavedAt(lastSavedAt)}`;
        }

        async function saveText() {
            const body = new URLSearchParams();
            body.set('action', 'save');
            body.set('text', textInput.value);

            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'Save failed.');
            }

            lastSavedAt = result.data.updated_at;
            updateStatus();
            showToast('Saved to server');
        }

        async function loadText() {
            const response = await fetch(`${window.location.pathname}?action=load`, { cache: 'no-store' });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'Load failed.');
            }

            textInput.value = result.data.text || '';
            lastSavedAt = result.data.updated_at || null;
            updateStatus();
            showToast('Loaded latest text');
        }

        async function copyText() {
            textInput.focus();
            textInput.select();

            try {
                await navigator.clipboard.writeText(textInput.value);
                showToast('Copied to clipboard');
            } catch (error) {
                document.execCommand('copy');
                showToast('Copied using browser fallback');
            }
        }

        updateStatus();

        textInput.addEventListener('input', updateStatus);
        saveBtn.addEventListener('click', () => saveText().catch(error => showToast(error.message)));
        loadBtn.addEventListener('click', () => loadText().catch(error => showToast(error.message)));
        copyBtn.addEventListener('click', copyText);
        selectBtn.addEventListener('click', () => {
            textInput.focus();
            textInput.select();
            showToast('Text selected');
        });
        clearBtn.addEventListener('click', () => {
            if (!textInput.value || confirm('Clear the editor? This will not clear the server until you save.')) {
                textInput.value = '';
                updateStatus();
                textInput.focus();
                showToast('Editor cleared');
            }
        });
    </script>
</body>
</html>
