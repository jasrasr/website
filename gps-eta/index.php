<?php
/*
Project: GPS Speed + ETA Tracker
File: gps-eta/index.php
Revision: 1.5.0
Author: Jason Lamb
Created: 2026-06-30
Modified: 2026-06-30
Description: Mobile-friendly PHP page with browser GPS tracking, speed calculation, compass heading, tracked distance, distance remaining, trip stats, trip log snapshots, CSV export, per-device history logging, automatic 365-day retention, user history deletion, and dynamic changelog rendering from CHANGELOG.md.
Public URL: https://jasr.me/github/gps-eta/
Repository: https://github.com/jasrasr/website/tree/main/gps-eta
Changelog: gps-eta/CHANGELOG.md
Notes: Requires HTTPS or localhost for browser geolocation access. GPS coordinates intentionally remain visible. Device history uses a browser-generated local device ID rather than invasive fingerprinting.
*/

const APP_REVISION = '1.5.0';
const APP_UPDATED = '2026-06-30';
const CHANGELOG_FILE = __DIR__ . '/CHANGELOG.md';
const HISTORY_DIR = __DIR__ . '/data/device-history';
const HISTORY_RETENTION_DAYS = 365;
const HISTORY_MAX_ENTRIES = 20000;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function startsWithText(string $text, string $prefix): bool
{
    return strncmp($text, $prefix, strlen($prefix)) === 0;
}

function inlineMarkdown(string $text): string
{
    $text = h($text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    return $text;
}

function renderChangelogMarkdown(string $path): string
{
    if (!is_readable($path)) {
        return '<p class="small">CHANGELOG.md could not be read.</p>';
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return '<p class="small">CHANGELOG.md could not be loaded.</p>';
    }

    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (startsWithText($trimmed, '# ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h3>' . inlineMarkdown(substr($trimmed, 2)) . '</h3>';
            continue;
        }

        if (startsWithText($trimmed, '## ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h4>' . inlineMarkdown(substr($trimmed, 3)) . '</h4>';
            continue;
        }

        if (startsWithText($trimmed, '- ')) {
            if (!$inList) {
                $html .= '<ul class="changelog-list">';
                $inList = true;
            }
            $html .= '<li>' . inlineMarkdown(substr($trimmed, 2)) . '</li>';
            continue;
        }

        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
        $html .= '<p>' . inlineMarkdown($trimmed) . '</p>';
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function readRequestJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sanitizeDeviceId(string $deviceId): string
{
    $deviceId = trim($deviceId);
    $deviceId = preg_replace('/[^A-Za-z0-9_.:-]/', '', $deviceId) ?? '';
    return substr($deviceId, 0, 120);
}

function requireDeviceId(?string $deviceId): string
{
    $safe = sanitizeDeviceId((string)$deviceId);
    if ($safe === '') {
        jsonResponse(['ok' => false, 'error' => 'Missing device ID.'], 400);
    }
    return $safe;
}

function ensureHistoryDirectory(): void
{
    if (is_dir(HISTORY_DIR)) {
        return;
    }

    if (!mkdir(HISTORY_DIR, 0755, true) && !is_dir(HISTORY_DIR)) {
        jsonResponse(['ok' => false, 'error' => 'Unable to create history directory.'], 500);
    }
}

function deviceHistoryPath(string $deviceId): string
{
    ensureHistoryDirectory();
    return HISTORY_DIR . '/' . hash('sha256', $deviceId) . '.json';
}

function loadDeviceHistory(string $deviceId): array
{
    $path = deviceHistoryPath($deviceId);
    if (!is_readable($path)) {
        return [
            'deviceHash' => hash('sha256', $deviceId),
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'entries' => [],
        ];
    }

    $raw = file_get_contents($path);
    $decoded = $raw === false ? [] : json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $decoded['deviceHash'] = $decoded['deviceHash'] ?? hash('sha256', $deviceId);
    $decoded['createdAt'] = $decoded['createdAt'] ?? gmdate('c');
    $decoded['updatedAt'] = $decoded['updatedAt'] ?? gmdate('c');
    $decoded['entries'] = isset($decoded['entries']) && is_array($decoded['entries']) ? $decoded['entries'] : [];
    return $decoded;
}

function pruneHistoryEntries(array $entries): array
{
    $cutoff = time() - (HISTORY_RETENTION_DAYS * 86400);
    $filtered = [];

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $stamp = $entry['iso'] ?? $entry['serverSavedAt'] ?? null;
        $time = is_string($stamp) ? strtotime($stamp) : false;
        if ($time !== false && $time >= $cutoff) {
            $filtered[] = $entry;
        }
    }

    if (count($filtered) > HISTORY_MAX_ENTRIES) {
        $filtered = array_slice($filtered, -HISTORY_MAX_ENTRIES);
    }

    return array_values($filtered);
}

function saveDeviceHistory(string $deviceId, array $history): void
{
    $path = deviceHistoryPath($deviceId);
    $history['entries'] = pruneHistoryEntries($history['entries'] ?? []);
    $history['updatedAt'] = gmdate('c');

    $encoded = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        jsonResponse(['ok' => false, 'error' => 'Unable to write history file.'], 500);
    }
}

function limitedString(array $entry, string $key, int $max = 180): string
{
    $value = $entry[$key] ?? '';
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
    }
    return substr((string)$value, 0, $max);
}

function limitedNumber(array $entry, string $key): float|int|null
{
    $value = $entry[$key] ?? null;
    return is_numeric($value) ? $value + 0 : null;
}

function sanitizeHistoryEntry(array $entry): array
{
    $iso = limitedString($entry, 'iso', 40);
    if (strtotime($iso) === false) {
        $iso = gmdate('c');
    }

    return [
        'id' => bin2hex(random_bytes(8)),
        'serverSavedAt' => gmdate('c'),
        'appRevision' => APP_REVISION,
        'iso' => $iso,
        'time' => limitedString($entry, 'time', 40),
        'elapsed' => limitedString($entry, 'elapsed', 40),
        'elapsedSeconds' => limitedNumber($entry, 'elapsedSeconds'),
        'tracked' => limitedNumber($entry, 'tracked'),
        'remaining' => limitedNumber($entry, 'remaining'),
        'unit' => limitedString($entry, 'unit', 20),
        'speed' => limitedNumber($entry, 'speed'),
        'speedUnit' => limitedString($entry, 'speedUnit', 20),
        'heading' => limitedString($entry, 'heading', 40),
        'accuracy' => limitedString($entry, 'accuracy', 60),
        'latlon' => limitedString($entry, 'latlon', 80),
        'reason' => limitedString($entry, 'reason', 40),
    ];
}

function handleHistoryApi(): void
{
    $api = $_GET['api'] ?? '';
    $body = readRequestJson();
    $deviceId = requireDeviceId($_GET['deviceId'] ?? ($body['deviceId'] ?? null));

    if ($api === 'history') {
        $history = loadDeviceHistory($deviceId);
        $history['entries'] = pruneHistoryEntries($history['entries']);
        saveDeviceHistory($deviceId, $history);
        jsonResponse([
            'ok' => true,
            'retentionDays' => HISTORY_RETENTION_DAYS,
            'count' => count($history['entries']),
            'updatedAt' => $history['updatedAt'],
            'entries' => array_reverse($history['entries']),
        ]);
    }

    if ($api === 'log') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $entry = $body['entry'] ?? null;
        if (!is_array($entry)) {
            jsonResponse(['ok' => false, 'error' => 'Missing history entry.'], 400);
        }

        $history = loadDeviceHistory($deviceId);
        $history['entries'] = pruneHistoryEntries($history['entries']);
        $history['entries'][] = sanitizeHistoryEntry($entry);
        saveDeviceHistory($deviceId, $history);

        jsonResponse(['ok' => true, 'count' => count($history['entries']), 'retentionDays' => HISTORY_RETENTION_DAYS]);
    }

    if ($api === 'delete-history') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $path = deviceHistoryPath($deviceId);
        $deleted = false;
        if (is_file($path)) {
            $deleted = unlink($path);
        }

        jsonResponse(['ok' => true, 'deleted' => $deleted]);
    }

    jsonResponse(['ok' => false, 'error' => 'Unknown API action.'], 404);
}

if (isset($_GET['api'])) {
    handleHistoryApi();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GPS Speed + ETA Tracker</title>
  <style>
    :root{--bg:#101114;--card:#1b1d22;--card2:#242730;--text:#f4f4f5;--muted:#a1a1aa;--border:#3f3f46;--accent:#60a5fa;--bad:#f87171;--good:#34d399;--warn:#fbbf24}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#1e293b 0,var(--bg) 45%);color:var(--text);min-height:100vh;padding:16px}main{max-width:860px;margin:0 auto}h1{font-size:clamp(1.8rem,5vw,3rem);margin:12px 0 4px;letter-spacing:-.04em}h2{margin:0 0 10px}.subtitle{color:var(--muted);margin:0 0 18px;line-height:1.45}.card{background:rgba(27,29,34,.92);border:1px solid var(--border);border-radius:18px;padding:16px;margin-bottom:14px;box-shadow:0 18px 40px rgba(0,0,0,.25);backdrop-filter:blur(10px)}label{display:block;color:var(--muted);font-size:.95rem;margin-bottom:6px}input,select,button{width:100%;border-radius:12px;border:1px solid var(--border);padding:12px 14px;font-size:1rem}input,select{background:var(--card2);color:var(--text)}button{border:0;color:#06121f;background:var(--accent);font-weight:800;cursor:pointer;margin-top:10px}button.secondary{background:#d4d4d8}button.danger{background:var(--bad);color:#210404}button:disabled{opacity:.55;cursor:not-allowed}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}.metric{background:var(--card2);border:1px solid var(--border);border-radius:16px;padding:14px;min-height:96px}.metric.full{grid-column:1/-1}.metric-title{color:var(--muted);font-size:.85rem;margin-bottom:6px}.metric-value{font-size:clamp(1.55rem,7vw,3rem);font-weight:900;line-height:1;letter-spacing:-.05em}.metric-unit{color:var(--muted);font-size:.95rem;margin-top:5px}.status{display:flex;gap:8px;align-items:center;color:var(--muted);line-height:1.4;min-height:28px;margin-top:12px}.dot{width:10px;height:10px;border-radius:999px;background:var(--warn);flex:0 0 auto}.dot.good{background:var(--good)}.dot.bad{background:var(--bad)}.small{color:var(--muted);font-size:.85rem;line-height:1.45}.row,.button-row,.bottom-actions{display:grid;grid-template-columns:1fr 130px;gap:10px;align-items:end}.button-row{grid-template-columns:repeat(2,1fr)}.bottom-actions{grid-template-columns:repeat(2,1fr);margin-top:10px}.button-row button,.bottom-actions button{margin-top:10px}.log{max-height:270px;overflow:auto;border:1px solid var(--border);border-radius:14px;background:var(--card2)}table{width:100%;border-collapse:collapse;font-size:.88rem}th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--border);white-space:nowrap}th{color:var(--muted);font-weight:700;position:sticky;top:0;background:var(--card2)}.rev{color:var(--muted);font-size:.8rem;margin-top:8px}.changelog-markdown h3{margin:0 0 10px}.changelog-markdown h4{margin:14px 0 6px}.changelog-list{margin:8px 0 0 18px;color:var(--muted);line-height:1.45}.changelog-list strong{color:var(--text)}code{background:var(--card2);border:1px solid var(--border);border-radius:6px;padding:1px 5px}.compass-wrap{display:flex;align-items:center;gap:14px}.compass{position:relative;width:78px;height:78px;border:2px solid var(--border);border-radius:50%;background:#111827;flex:0 0 auto}.compass::before{content:'N';position:absolute;top:4px;left:50%;transform:translateX(-50%);font-size:.72rem;color:var(--muted);font-weight:800}.compass::after{content:'S';position:absolute;bottom:4px;left:50%;transform:translateX(-50%);font-size:.72rem;color:var(--muted);font-weight:800}.compass-east,.compass-west{position:absolute;top:50%;transform:translateY(-50%);font-size:.72rem;color:var(--muted);font-weight:800}.compass-east{right:6px}.compass-west{left:6px}.needle{position:absolute;left:50%;top:50%;width:4px;height:31px;background:var(--accent);border-radius:999px;transform-origin:50% 100%;transform:translate(-50%,-100%) rotate(0deg);box-shadow:0 0 10px rgba(96,165,250,.65)}.needle::after{content:'';position:absolute;left:50%;top:-7px;transform:translateX(-50%);border-left:7px solid transparent;border-right:7px solid transparent;border-bottom:10px solid var(--accent)}.history-id{overflow-wrap:anywhere;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--muted);font-size:.85rem}@media(max-width:650px){body{padding:12px}.grid,.row,.button-row,.bottom-actions{grid-template-columns:1fr}th,td{font-size:.8rem;padding:8px}}
  </style>
</head>
<body>
  <main>
    <h1>GPS Speed + ETA Tracker</h1>
    <p class="subtitle">Enter your starting distance, start trip tracking, and this page estimates arrival time from current speed while automatically counting down distance traveled by GPS.</p>

    <section class="card">
      <div class="row">
        <div>
          <label for="distance">Starting distance remaining</label>
          <input id="distance" type="number" inputmode="decimal" min="0" step="0.01" placeholder="Example: 41" />
        </div>
        <div>
          <label for="unit">Unit</label>
          <select id="unit"><option value="miles">Miles</option><option value="km">Kilometers</option></select>
        </div>
      </div>
      <button id="startBtn">Start Trip Tracking</button>
      <div class="button-row">
        <button id="pauseBtn" class="danger" disabled>Pause</button>
        <button id="resetBtn" class="secondary">Reset</button>
      </div>
      <div class="status"><span id="statusDot" class="dot"></span><span id="statusText">Not tracking yet.</span></div>
      <p class="small">Trip Mode subtracts GPS miles traveled from your starting distance. GPS coordinates remain visible. No route API is used, so this is GPS breadcrumb distance, not traffic-aware road distance.</p>
      <div class="rev">Rev <?= h(APP_REVISION) ?> &bull; Updated <?= h(APP_UPDATED) ?> &bull; Per-Device History + 365-Day Retention</div>
    </section>

    <section class="grid" aria-label="Live trip data">
      <div class="metric"><div class="metric-title">Distance Remaining</div><div id="remainingValue" class="metric-value">--</div><div id="remainingUnit" class="metric-unit">miles</div></div>
      <div class="metric"><div class="metric-title">Current Speed</div><div id="speedValue" class="metric-value">--</div><div id="speedUnit" class="metric-unit">mph</div></div>
      <div class="metric full">
        <div class="metric-title">Compass Heading</div>
        <div class="compass-wrap">
          <div class="compass" aria-hidden="true"><span class="compass-west">W</span><span class="compass-east">E</span><span id="compassNeedle" class="needle"></span></div>
          <div><div id="headingValue" class="metric-value">--</div><div id="headingSource" class="metric-unit">native or calculated GPS heading</div></div>
        </div>
      </div>
      <div class="metric"><div class="metric-title">Estimated Time Remaining</div><div id="etaDuration" class="metric-value">--</div><div class="metric-unit">current speed + remaining distance</div></div>
      <div class="metric"><div class="metric-title">Estimated Arrival Time</div><div id="arrivalTime" class="metric-value">--</div><div class="metric-unit">local device time</div></div>
      <div class="metric"><div class="metric-title">Distance Traveled</div><div id="traveledValue" class="metric-value">0.0</div><div id="traveledUnit" class="metric-unit">miles</div></div>
      <div class="metric"><div class="metric-title">Trip Progress</div><div id="progressValue" class="metric-value">--</div><div class="metric-unit">percent of entered distance</div></div>
      <div class="metric"><div class="metric-title">Elapsed Tracking Time</div><div id="elapsedValue" class="metric-value">--</div><div class="metric-unit">hh:mm:ss</div></div>
      <div class="metric"><div class="metric-title">Moving Time</div><div id="movingValue" class="metric-value">--</div><div class="metric-unit">speed above idle threshold</div></div>
      <div class="metric"><div class="metric-title">Stopped Time</div><div id="stoppedValue" class="metric-value">--</div><div class="metric-unit">elapsed minus moving</div></div>
      <div class="metric"><div class="metric-title">Average Speed</div><div id="avgSpeedValue" class="metric-value">--</div><div id="avgSpeedUnit" class="metric-unit">mph</div></div>
      <div class="metric"><div class="metric-title">Max Speed</div><div id="maxSpeedValue" class="metric-value">--</div><div id="maxSpeedUnit" class="metric-unit">mph</div></div>
      <div class="metric"><div class="metric-title">Current Pace</div><div id="paceValue" class="metric-value">--</div><div id="paceUnit" class="metric-unit">min/mi</div></div>
      <div class="metric"><div class="metric-title">GPS Accuracy</div><div id="accuracyValue" class="metric-value">--</div><div id="accuracyUnit" class="metric-unit">feet</div></div>
      <div class="metric full"><div class="metric-title">Last Location</div><div id="locationValue" style="font-size:1.05rem;overflow-wrap:anywhere">--</div><div id="timestampValue" class="metric-unit">--</div></div>
      <div class="metric full"><div class="metric-title">Raw GPS Data</div><div id="rawGpsValue" style="font-size:.98rem;overflow-wrap:anywhere">Altitude: -- | Heading: -- | Native speed: --</div></div>
    </section>

    <section class="card">
      <h2>Trip Log</h2>
      <div class="log"><table><thead><tr><th>Time</th><th>Elapsed</th><th>Tracked</th><th>Remaining</th><th>Speed</th><th>Heading</th><th>Accuracy</th></tr></thead><tbody id="logBody"><tr><td colspan="7" class="small">No log entries yet.</td></tr></tbody></table></div>
      <div class="bottom-actions">
        <button id="snapshotBtn" class="secondary" disabled>Log Snapshot</button>
        <button id="exportBtn" class="secondary" disabled>Export CSV</button>
      </div>
      <p class="small">A snapshot is logged locally and to server history at start, every 60 seconds while tracking, and any time you tap Log Snapshot.</p>
    </section>

    <section class="card">
      <h2>Device History</h2>
      <p class="small">History is saved per browser-generated device ID, kept for the last <?= HISTORY_RETENTION_DAYS ?> days, and pruned automatically on read/write. This is not aggressive fingerprinting; it is a local ID stored in this browser.</p>
      <div id="deviceIdValue" class="history-id">Device ID: loading...</div>
      <div class="bottom-actions">
        <button id="loadHistoryBtn" class="secondary">Refresh History</button>
        <button id="deleteHistoryBtn" class="danger">Delete My History</button>
      </div>
      <p id="historySummary" class="small">History not loaded yet.</p>
      <div class="log"><table><thead><tr><th>Saved</th><th>Elapsed</th><th>Tracked</th><th>Remaining</th><th>Speed</th><th>Heading</th><th>Reason</th></tr></thead><tbody id="historyBody"><tr><td colspan="7" class="small">No server history loaded.</td></tr></tbody></table></div>
    </section>

    <section class="card"><p class="small"><strong>Other useful calculations to consider next:</strong> estimated route error/correction factor, average moving speed, trip start time, pause count, last GPS update age, and distance per GPS ping. The only big missing piece is true road-route distance/traffic, which needs a maps/directions API.</p></section>

    <section class="card">
      <div class="changelog-markdown">
        <?= renderChangelogMarkdown(CHANGELOG_FILE) ?>
      </div>
      <p class="small">Rendered directly from <code>gps-eta/CHANGELOG.md</code>.</p>
    </section>
  </main>

<script>
/*
Script: GPS Speed + ETA Tracker Client Logic
Revision: 1.5.0
Author: Jason Lamb
Created: 2026-06-30
Modified: 2026-06-30
Description: Uses browser geolocation to calculate smoothed speed, compass heading, GPS breadcrumb distance traveled, distance remaining, ETA, trip stats, snapshot logs, CSV export, localStorage trip recovery, and PHP-backed per-device history.
Dependencies: None. Plain PHP, HTML, CSS, and JavaScript.
Browser Requirements: Secure context via HTTPS or localhost; geolocation permission required.
*/
const els={distance:document.getElementById('distance'),unit:document.getElementById('unit'),startBtn:document.getElementById('startBtn'),pauseBtn:document.getElementById('pauseBtn'),resetBtn:document.getElementById('resetBtn'),snapshotBtn:document.getElementById('snapshotBtn'),exportBtn:document.getElementById('exportBtn'),loadHistoryBtn:document.getElementById('loadHistoryBtn'),deleteHistoryBtn:document.getElementById('deleteHistoryBtn'),historyBody:document.getElementById('historyBody'),historySummary:document.getElementById('historySummary'),deviceIdValue:document.getElementById('deviceIdValue'),statusDot:document.getElementById('statusDot'),statusText:document.getElementById('statusText'),remainingValue:document.getElementById('remainingValue'),remainingUnit:document.getElementById('remainingUnit'),traveledValue:document.getElementById('traveledValue'),traveledUnit:document.getElementById('traveledUnit'),elapsedValue:document.getElementById('elapsedValue'),movingValue:document.getElementById('movingValue'),stoppedValue:document.getElementById('stoppedValue'),progressValue:document.getElementById('progressValue'),paceValue:document.getElementById('paceValue'),paceUnit:document.getElementById('paceUnit'),speedValue:document.getElementById('speedValue'),speedUnit:document.getElementById('speedUnit'),avgSpeedValue:document.getElementById('avgSpeedValue'),avgSpeedUnit:document.getElementById('avgSpeedUnit'),maxSpeedValue:document.getElementById('maxSpeedValue'),maxSpeedUnit:document.getElementById('maxSpeedUnit'),accuracyValue:document.getElementById('accuracyValue'),accuracyUnit:document.getElementById('accuracyUnit'),etaDuration:document.getElementById('etaDuration'),arrivalTime:document.getElementById('arrivalTime'),headingValue:document.getElementById('headingValue'),headingSource:document.getElementById('headingSource'),compassNeedle:document.getElementById('compassNeedle'),locationValue:document.getElementById('locationValue'),timestampValue:document.getElementById('timestampValue'),rawGpsValue:document.getElementById('rawGpsValue'),logBody:document.getElementById('logBody')};
let watchId=null,lastPosition=null,lastTripPosition=null,speedSamplesMps=[],tripActive=false,tracking=false,tripStartMeters=0,distanceTraveledMeters=0,elapsedSeconds=0,movingSeconds=0,maxSpeedMps=0,lastLogMs=0,logEntries=[],lastHeadingDeg=null,lastHeadingText='--',lastHeadingSource='heading unavailable';
const MAX_SAMPLES=5,MIN_SPEED_MPS=.4,MAX_SPEED_MPS=90,MAX_ACCURACY_METERS=120,MPS_TO_MPH=2.2369362921,MPS_TO_KPH=3.6,METERS_PER_MILE=1609.344,LOG_INTERVAL_MS=60000,STORE_KEY='gpsEtaTripStateV2',DEVICE_KEY='gpsEtaDeviceIdV1',APP_REVISION='<?= h(APP_REVISION) ?>';
function setStatus(msg,type='warn'){els.statusText.textContent=msg;els.statusDot.className='dot'+(type==='good'?' good':type==='bad'?' bad':'')}
function label(){return els.unit.value==='miles'?'miles':'km'}
function speedLabel(){return els.unit.value==='miles'?'mph':'km/h'}
function paceLabel(){return els.unit.value==='miles'?'min/mi':'min/km'}
function toDisplayDistance(m){return els.unit.value==='miles'?m/METERS_PER_MILE:m/1000}
function toDisplaySpeed(mps){return els.unit.value==='miles'?mps*MPS_TO_MPH:mps*MPS_TO_KPH}
function inputMeters(){const d=parseFloat(els.distance.value);if(!Number.isFinite(d)||d<=0)return 0;return els.unit.value==='miles'?d*METERS_PER_MILE:d*1000}
function rad(d){return d*Math.PI/180}
function deg(r){return r*180/Math.PI}
function hav(lat1,lon1,lat2,lon2){const R=6371000,dLat=rad(lat2-lat1),dLon=rad(lon2-lon1),a=Math.sin(dLat/2)**2+Math.cos(rad(lat1))*Math.cos(rad(lat2))*Math.sin(dLon/2)**2;return 2*R*Math.atan2(Math.sqrt(a),Math.sqrt(1-a))}
function bearing(lat1,lon1,lat2,lon2){const y=Math.sin(rad(lon2-lon1))*Math.cos(rad(lat2)),x=Math.cos(rad(lat1))*Math.sin(rad(lat2))-Math.sin(rad(lat1))*Math.cos(rad(lat2))*Math.cos(rad(lon2-lon1));return (deg(Math.atan2(y,x))+360)%360}
function cardinal(degrees){if(!Number.isFinite(degrees))return'--';const dirs=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];return dirs[Math.round((degrees%360)/22.5)%16]}
function updateHeading(degrees,source){if(!Number.isFinite(degrees)){els.headingValue.textContent='--';els.headingSource.textContent='heading unavailable';return}lastHeadingDeg=(degrees+360)%360;lastHeadingText=`${Math.round(lastHeadingDeg)}° ${cardinal(lastHeadingDeg)}`;lastHeadingSource=source;els.headingValue.textContent=lastHeadingText;els.headingSource.textContent=source;els.compassNeedle.style.transform=`translate(-50%,-100%) rotate(${lastHeadingDeg}deg)`}
function addSpeed(mps){if(!Number.isFinite(mps)||mps<0||mps>MAX_SPEED_MPS)return;speedSamplesMps.push(mps);if(speedSamplesMps.length>MAX_SAMPLES)speedSamplesMps.shift();if(mps>maxSpeedMps)maxSpeedMps=mps}
function avgCurrentSpeed(){return speedSamplesMps.length?speedSamplesMps.reduce((s,n)=>s+n,0)/speedSamplesMps.length:0}
function remainingMeters(){return tripActive?Math.max(tripStartMeters-distanceTraveledMeters,0):inputMeters()}
function fmtDur(sec){if(!Number.isFinite(sec)||sec<0)return'--';sec=Math.round(sec);const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;return h>0?`${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`:`${m}:${String(s).padStart(2,'0')}`}
function fmtShortDur(sec){if(!Number.isFinite(sec)||sec<0)return'--';const mins=Math.round(sec/60),h=Math.floor(mins/60),m=mins%60;if(h<=0)return`${m} min`;return m?`${h} hr ${m} min`:`${h} hr`}
function fmtPace(mps){if(!Number.isFinite(mps)||mps<MIN_SPEED_MPS)return'--';const unitMeters=els.unit.value==='miles'?METERS_PER_MILE:1000;return fmtDur(unitMeters/mps)}
function getDeviceId(){let id=localStorage.getItem(DEVICE_KEY);if(!id){id=(crypto&&crypto.randomUUID)?crypto.randomUUID():`gps-${Date.now()}-${Math.random().toString(16).slice(2)}`;localStorage.setItem(DEVICE_KEY,id)}els.deviceIdValue.textContent=`Device ID: ${id}`;return id}
function updateDistanceDisplays(){const rem=remainingMeters(),distLabel=label();els.remainingUnit.textContent=distLabel;els.traveledUnit.textContent=distLabel;els.remainingValue.textContent=rem>0?toDisplayDistance(rem).toFixed(1):(tripActive?'0.0':'--');els.traveledValue.textContent=toDisplayDistance(distanceTraveledMeters).toFixed(1)}
function updateStats(){const current=avgCurrentSpeed(),avg=elapsedSeconds>0?distanceTraveledMeters/elapsedSeconds:0,stopped=Math.max(elapsedSeconds-movingSeconds,0),progress=tripStartMeters>0?Math.min((distanceTraveledMeters/tripStartMeters)*100,100):NaN;els.elapsedValue.textContent=elapsedSeconds?fmtDur(elapsedSeconds):'--';els.movingValue.textContent=movingSeconds?fmtDur(movingSeconds):'--';els.stoppedValue.textContent=elapsedSeconds?fmtDur(stopped):'--';els.progressValue.textContent=Number.isFinite(progress)?progress.toFixed(1)+'%':'--';els.speedUnit.textContent=speedLabel();els.avgSpeedUnit.textContent=speedLabel();els.maxSpeedUnit.textContent=speedLabel();els.paceUnit.textContent=paceLabel();els.speedValue.textContent=current>=.05?toDisplaySpeed(current).toFixed(1):'--';els.avgSpeedValue.textContent=avg>=.05?toDisplaySpeed(avg).toFixed(1):'--';els.maxSpeedValue.textContent=maxSpeedMps>=.05?toDisplaySpeed(maxSpeedMps).toFixed(1):'--';els.paceValue.textContent=fmtPace(current);updateDistanceDisplays();updateEta(current)}
function updateEta(speedMps){const rem=remainingMeters();if(!Number.isFinite(rem)||rem<=0){els.etaDuration.textContent=tripActive?'Arrived':'--';els.arrivalTime.textContent=tripActive?'Now':'--';return}if(!Number.isFinite(speedMps)||speedMps<MIN_SPEED_MPS){els.etaDuration.textContent='--';els.arrivalTime.textContent='--';return}const sec=rem/speedMps,arrival=new Date(Date.now()+sec*1000);els.etaDuration.textContent=fmtShortDur(sec);els.arrivalTime.textContent=arrival.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})}
function updateAccuracy(m){if(!Number.isFinite(m)){els.accuracyValue.textContent='--';return}if(els.unit.value==='miles'){els.accuracyValue.textContent=Math.round(m*3.28084);els.accuracyUnit.textContent='feet'}else{els.accuracyValue.textContent=Math.round(m);els.accuracyUnit.textContent='meters'}}
async function saveServerLog(entry){try{entry.appRevision=APP_REVISION;entry.unit=label();entry.speedUnit=speedLabel();const response=await fetch('?api=log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({deviceId:getDeviceId(),entry})});const data=await response.json();if(data&&data.ok){els.historySummary.textContent=`Server history saved. ${data.count} entries retained for this device.`}}catch{els.historySummary.textContent='Server history save failed. Local trip log still works.'}}
function addLog(reason='auto'){const now=new Date(),cur=avgCurrentSpeed(),rem=remainingMeters();if(!tripActive&&reason!=='manual')return;const entry={time:now.toLocaleTimeString([],{hour:'numeric',minute:'2-digit',second:'2-digit'}),iso:now.toISOString(),elapsed:fmtDur(elapsedSeconds),elapsedSeconds,tracked:toDisplayDistance(distanceTraveledMeters),remaining:toDisplayDistance(rem),unit:label(),speed:toDisplaySpeed(cur),speedUnit:speedLabel(),heading:lastHeadingText,accuracy:els.accuracyValue.textContent+' '+els.accuracyUnit.textContent,latlon:els.locationValue.textContent,reason};logEntries.unshift(entry);if(logEntries.length>250)logEntries.pop();renderLog();saveState();saveServerLog({...entry});lastLogMs=Date.now()}
function renderLog(){if(!logEntries.length){els.logBody.innerHTML='<tr><td colspan="7" class="small">No log entries yet.</td></tr>';return}els.logBody.innerHTML=logEntries.slice(0,25).map(e=>`<tr><td>${e.time}</td><td>${e.elapsed}</td><td>${e.tracked.toFixed(2)} ${label()}</td><td>${e.remaining.toFixed(2)} ${label()}</td><td>${e.speed.toFixed(1)} ${speedLabel()}</td><td>${e.heading||'--'}</td><td>${e.accuracy}</td></tr>`).join('')}
function renderHistory(entries){if(!entries||!entries.length){els.historyBody.innerHTML='<tr><td colspan="7" class="small">No server history found for this device.</td></tr>';return}els.historyBody.innerHTML=entries.slice(0,50).map(e=>`<tr><td>${e.time||e.serverSavedAt||'--'}</td><td>${e.elapsed||'--'}</td><td>${Number.isFinite(Number(e.tracked))?Number(e.tracked).toFixed(2):'--'} ${e.unit||label()}</td><td>${Number.isFinite(Number(e.remaining))?Number(e.remaining).toFixed(2):'--'} ${e.unit||label()}</td><td>${Number.isFinite(Number(e.speed))?Number(e.speed).toFixed(1):'--'} ${e.speedUnit||speedLabel()}</td><td>${e.heading||'--'}</td><td>${e.reason||'--'}</td></tr>`).join('')}
async function loadServerHistory(){try{const response=await fetch(`?api=history&deviceId=${encodeURIComponent(getDeviceId())}`);const data=await response.json();if(!data.ok)throw new Error(data.error||'history failed');renderHistory(data.entries||[]);els.historySummary.textContent=`Loaded ${data.count} retained history entries. Older than ${data.retentionDays} days are automatically deleted.`}catch(err){els.historySummary.textContent=`History load failed: ${err.message}`}}
async function deleteServerHistory(){if(!confirm('Delete all saved server history for this browser/device? Local active trip data is not removed.'))return;try{const response=await fetch('?api=delete-history',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({deviceId:getDeviceId()})});const data=await response.json();if(!data.ok)throw new Error(data.error||'delete failed');els.historySummary.textContent='Server history for this device was deleted.';renderHistory([])}catch(err){els.historySummary.textContent=`History delete failed: ${err.message}`}}
function saveState(){const state={unit:els.unit.value,distanceInput:els.distance.value,tripActive,tripStartMeters,distanceTraveledMeters,elapsedSeconds,movingSeconds,maxSpeedMps,logEntries,lastHeadingDeg,lastHeadingText,lastHeadingSource};localStorage.setItem(STORE_KEY,JSON.stringify(state))}
function loadState(){try{const raw=localStorage.getItem(STORE_KEY);if(!raw)return;const s=JSON.parse(raw);els.unit.value=s.unit||'miles';els.distance.value=s.distanceInput||'';tripActive=!!s.tripActive;tripStartMeters=s.tripStartMeters||0;distanceTraveledMeters=s.distanceTraveledMeters||0;elapsedSeconds=s.elapsedSeconds||0;movingSeconds=s.movingSeconds||0;maxSpeedMps=s.maxSpeedMps||0;logEntries=Array.isArray(s.logEntries)?s.logEntries:[];lastHeadingDeg=s.lastHeadingDeg??null;lastHeadingText=s.lastHeadingText||'--';lastHeadingSource=s.lastHeadingSource||'heading unavailable';if(Number.isFinite(lastHeadingDeg))updateHeading(lastHeadingDeg,lastHeadingSource);if(tripActive){els.distance.disabled=true;els.unit.disabled=true;els.startBtn.textContent='Resume Tracking';els.snapshotBtn.disabled=false;els.exportBtn.disabled=false;setStatus('Trip restored. Tap Resume Tracking to continue GPS updates.','warn')}renderLog();updateStats()}catch{localStorage.removeItem(STORE_KEY)}}
function accumulate(position,calcSpeed){if(!tripActive)return;const{latitude,longitude,accuracy}=position.coords,t=position.timestamp;if(!lastTripPosition){lastTripPosition={latitude,longitude,timestamp:t};return}const seconds=(t-lastTripPosition.timestamp)/1000;if(seconds<=0||seconds>300){lastTripPosition={latitude,longitude,timestamp:t};return}const meters=hav(lastTripPosition.latitude,lastTripPosition.longitude,latitude,longitude),implied=meters/seconds,accuracyOk=!Number.isFinite(accuracy)||accuracy<=MAX_ACCURACY_METERS,movedEnough=meters>=2,speedOk=Number.isFinite(implied)&&implied>=0&&implied<=MAX_SPEED_MPS;elapsedSeconds+=seconds;if(accuracyOk&&movedEnough&&speedOk){distanceTraveledMeters+=meters;if(implied>=MIN_SPEED_MPS)movingSeconds+=seconds;lastTripPosition={latitude,longitude,timestamp:t}}else if(Number.isFinite(calcSpeed)&&calcSpeed>=MIN_SPEED_MPS){movingSeconds+=seconds}}
function handlePosition(position){const{latitude,longitude,accuracy,speed,altitude,altitudeAccuracy,heading}=position.coords,t=position.timestamp;let calc=null,computedHeading=null;if(Number.isFinite(speed)&&speed>=0)calc=speed;if(lastPosition){const seconds=(t-lastPosition.timestamp)/1000,meters=hav(lastPosition.latitude,lastPosition.longitude,latitude,longitude);if(seconds>0&&!Number.isFinite(calc))calc=meters/seconds;if(meters>=2)computedHeading=bearing(lastPosition.latitude,lastPosition.longitude,latitude,longitude)}if(Number.isFinite(heading))updateHeading(heading,'native GPS heading');else if(Number.isFinite(computedHeading))updateHeading(computedHeading,'calculated from GPS movement');if(Number.isFinite(calc))addSpeed(calc);accumulate(position,calc);updateAccuracy(accuracy);els.locationValue.textContent=`${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;els.timestampValue.textContent=`Updated ${new Date(t).toLocaleTimeString([],{hour:'numeric',minute:'2-digit',second:'2-digit'})}`;els.rawGpsValue.textContent=`Altitude: ${Number.isFinite(altitude)?altitude.toFixed(1)+' m':'--'} | Alt accuracy: ${Number.isFinite(altitudeAccuracy)?altitudeAccuracy.toFixed(1)+' m':'--'} | Native heading: ${Number.isFinite(heading)?heading.toFixed(0)+'°':'--'} | Native speed: ${Number.isFinite(speed)?toDisplaySpeed(speed).toFixed(1)+' '+speedLabel():'--'}`;lastPosition={latitude,longitude,timestamp:t};updateStats();saveState();if(Date.now()-lastLogMs>=LOG_INTERVAL_MS)addLog('auto');setStatus(accuracy>MAX_ACCURACY_METERS?'Tracking, but GPS accuracy is weak.':'Tracking GPS location.','good')}
function handleError(error){let msg='Location error.';if(error.code===error.PERMISSION_DENIED)msg='Location permission was denied.';else if(error.code===error.POSITION_UNAVAILABLE)msg='Location is unavailable. Try going outside or checking location services.';else if(error.code===error.TIMEOUT)msg='GPS request timed out. Trying again may help.';setStatus(msg,'bad')}
function start(){if(!('geolocation'in navigator)){setStatus('This browser does not support GPS location.','bad');return}if(!tripActive){tripStartMeters=inputMeters();if(tripStartMeters<=0){setStatus('Enter a starting distance first.','bad');return}distanceTraveledMeters=0;elapsedSeconds=0;movingSeconds=0;maxSpeedMps=0;logEntries=[];speedSamplesMps=[];tripActive=true;lastLogMs=0;addLog('start')}lastPosition=null;lastTripPosition=null;watchId=navigator.geolocation.watchPosition(handlePosition,handleError,{enableHighAccuracy:true,maximumAge:1000,timeout:15000});tracking=true;els.distance.disabled=true;els.unit.disabled=true;els.startBtn.disabled=true;els.pauseBtn.disabled=false;els.snapshotBtn.disabled=false;els.exportBtn.disabled=false;setStatus('Requesting GPS permission...','warn');saveState()}
function pause(){if(watchId!==null){navigator.geolocation.clearWatch(watchId);watchId=null}tracking=false;lastTripPosition=null;els.startBtn.disabled=false;els.startBtn.textContent='Resume Tracking';els.pauseBtn.disabled=true;setStatus('Tracking paused. Trip stats are preserved.','warn');addLog('pause');saveState()}
function reset(){if(watchId!==null)navigator.geolocation.clearWatch(watchId);watchId=null;lastPosition=null;lastTripPosition=null;speedSamplesMps=[];tripActive=false;tracking=false;tripStartMeters=0;distanceTraveledMeters=0;elapsedSeconds=0;movingSeconds=0;maxSpeedMps=0;lastLogMs=0;logEntries=[];lastHeadingDeg=null;lastHeadingText='--';lastHeadingSource='heading unavailable';els.distance.disabled=false;els.unit.disabled=false;els.startBtn.disabled=false;els.startBtn.textContent='Start Trip Tracking';els.pauseBtn.disabled=true;els.snapshotBtn.disabled=true;els.exportBtn.disabled=true;els.headingValue.textContent='--';els.headingSource.textContent='native or calculated GPS heading';els.compassNeedle.style.transform='translate(-50%,-100%) rotate(0deg)';els.locationValue.textContent='--';els.timestampValue.textContent='--';els.rawGpsValue.textContent='Altitude: -- | Heading: -- | Native speed: --';localStorage.removeItem(STORE_KEY);renderLog();updateStats();setStatus('Trip reset.','warn')}
function exportCsv(){if(!logEntries.length)return;const header=['iso','time','elapsed_seconds','elapsed','tracked_'+label(),'remaining_'+label(),'speed_'+speedLabel(),'heading','accuracy','latlon','reason'];const rows=logEntries.slice().reverse().map(e=>[e.iso,e.time,e.elapsedSeconds,e.elapsed,e.tracked.toFixed(4),e.remaining.toFixed(4),e.speed.toFixed(2),e.heading||'',e.accuracy,e.latlon,e.reason]);const csv=[header,...rows].map(r=>r.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(',')).join('\n');const blob=new Blob([csv],{type:'text/csv'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='gps-trip-log.csv';a.click();URL.revokeObjectURL(url)}
els.startBtn.addEventListener('click',start);els.pauseBtn.addEventListener('click',pause);els.resetBtn.addEventListener('click',reset);els.snapshotBtn.addEventListener('click',()=>addLog('manual'));els.exportBtn.addEventListener('click',exportCsv);els.loadHistoryBtn.addEventListener('click',loadServerHistory);els.deleteHistoryBtn.addEventListener('click',deleteServerHistory);els.distance.addEventListener('input',()=>{if(!tripActive)updateStats()});els.unit.addEventListener('change',()=>{renderLog();updateStats();saveState()});getDeviceId();loadState();updateStats();loadServerHistory();
</script>
</body>
</html>
