# Weather Advisory Demo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 5-minute blue weather advisory demo overlay to the kiosk system, and fix real NWS alert overlay colors to match TV broadcast standards.

**Architecture:** Four independent but sequential tasks — backend state file + PHP endpoint first, then two `index.html` changes (real colors, demo overlay), then the admin UI section. All polling follows the existing 5-second pattern. Demo auto-expires server-side via `expiresAt` timestamp.

**Tech Stack:** Vanilla JS, PHP 8+, NWS GeoJSON API, existing `set-alarm.php` pattern.

---

## File Map

| File | Change |
|------|--------|
| `weather-demo.json` | **Create** — demo state storage |
| `weather-demo.json.example` | **Create** — reference copy |
| `set-alarm.php` | **Modify** — add `weather` alarm type + `expiresAt` logic |
| `index.html` | **Modify** — dynamic NWS overlay colors + weather demo overlay + poll |
| `admin.html` | **Modify** — Weather Advisory Demo section + countdown JS |

---

## Task 1: Create `weather-demo.json` and update `set-alarm.php`

**Files:**
- Create: `time-clock/weather-demo.json`
- Create: `time-clock/weather-demo.json.example`
- Modify: `time-clock/set-alarm.php`

- [ ] **Step 1: Create `weather-demo.json`**

```json
{
  "active": false,
  "triggeredAt": null,
  "expiresAt": null,
  "event": "Severe Thunderstorm Warning",
  "headline": "Severe Thunderstorm Warning issued by NWS Cleveland OH",
  "senderName": "NWS Cleveland OH"
}
```

- [ ] **Step 2: Create `weather-demo.json.example`** (identical content — reference copy)

```json
{
  "active": false,
  "triggeredAt": null,
  "expiresAt": null,
  "event": "Severe Thunderstorm Warning",
  "headline": "Severe Thunderstorm Warning issued by NWS Cleveland OH",
  "senderName": "NWS Cleveland OH"
}
```

- [ ] **Step 3: Add `getDefaultData()` helper and `weather` type to `set-alarm.php`**

Replace the existing `$alarmTypes` array (lines 15–19) and add a `getDefaultData()` helper function immediately after it:

```php
$alarmTypes = [
    'fire'    => ['file' => 'fire-alarm.json',     'message' => 'FIRE ALARM — EVACUATE NOW'],
    'shooter' => ['file' => 'active-shooter.json', 'message' => 'ACTIVE THREAT — LOCKDOWN NOW'],
    'demo'    => ['file' => 'demo-alert.json',     'message' => 'DEMO ALERT — This is a Test'],
    'weather' => ['file' => 'weather-demo.json',   'message' => 'Severe Thunderstorm Warning'],
];

function getDefaultData($type, $cfg) {
    if ($type === 'weather') {
        return [
            'active'      => false,
            'triggeredAt' => null,
            'expiresAt'   => null,
            'event'       => 'Severe Thunderstorm Warning',
            'headline'    => 'Severe Thunderstorm Warning issued by NWS Cleveland OH',
            'senderName'  => 'NWS Cleveland OH',
        ];
    }
    return ['active' => false, 'triggeredAt' => null, 'message' => $cfg['message']];
}
```

- [ ] **Step 4: Update the GET handler to auto-expire `weather` type**

Replace the GET block (lines 24–37) with:

```php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'fire';
    if (!isset($alarmTypes[$type])) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown alarm type']);
        exit;
    }
    $file = __DIR__ . '/' . $alarmTypes[$type]['file'];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? [];
        // Lazy expiry: if weather demo has passed its expiresAt, report inactive
        if ($type === 'weather' && !empty($data['active']) && !empty($data['expiresAt']) && time() > $data['expiresAt']) {
            $data['active'] = false;
        }
        echo json_encode($data);
    } else {
        echo json_encode(getDefaultData($type, $alarmTypes[$type]));
    }
    exit;
}
```

- [ ] **Step 5: Update the `action=on` handler to write `weather` data with `expiresAt`**

Replace the `if ($action === 'on')` block (lines 59–71) with:

```php
if ($action === 'on') {
    $now = time();
    // Clear all other alarm types first (mutual exclusivity)
    foreach ($alarmTypes as $otherType => $cfg) {
        if ($otherType !== $type) {
            file_put_contents(
                __DIR__ . '/' . $cfg['file'],
                json_encode(getDefaultData($otherType, $cfg), JSON_PRETTY_PRINT) . "\n"
            );
        }
    }
    // Build activation payload
    if ($type === 'weather') {
        $data = [
            'active'      => true,
            'triggeredAt' => $now,
            'expiresAt'   => $now + 300,
            'event'       => 'Severe Thunderstorm Warning',
            'headline'    => 'Severe Thunderstorm Warning issued by NWS Cleveland OH',
            'senderName'  => 'NWS Cleveland OH',
        ];
    } else {
        $data = ['active' => true, 'triggeredAt' => $now, 'message' => $message];
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => true, 'expiresAt' => $data['expiresAt'] ?? null]);
    exit;
}
```

- [ ] **Step 6: Update the `action=off` handler to use `getDefaultData()`**

Replace the `if ($action === 'off')` block (lines 73–78) with:

```php
if ($action === 'off') {
    $data = getDefaultData($type, $alarmTypes[$type]);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => false]);
    exit;
}
```

- [ ] **Step 7: Update the revision header in `set-alarm.php`**

Change:
```php
 * Revision   : 1.2.0
 * Modified   : 2026-05-22
```
To:
```php
 * Revision   : 1.3.0
 * Modified   : 2026-05-22
```
Add to changelog:
```php
 * 1.3.0  add weather demo alarm type with 5-minute expiresAt auto-expiry
```

- [ ] **Step 8: Verify `set-alarm.php` manually**

Open browser to: `https://jasr.me/time-clock/set-alarm.php?type=weather`

Expected response:
```json
{"active":false,"triggeredAt":null,"expiresAt":null,"event":"Severe Thunderstorm Warning","headline":"Severe Thunderstorm Warning issued by NWS Cleveland OH","senderName":"NWS Cleveland OH"}
```

- [ ] **Step 9: Commit**

```bash
git add time-clock/weather-demo.json time-clock/weather-demo.json.example time-clock/set-alarm.php
git commit -m "Add weather demo alarm type with 5-min auto-expiry to set-alarm.php"
```

---

## Task 2: Fix real NWS overlay colors in `index.html`

**Files:**
- Modify: `time-clock/index.html`

- [ ] **Step 1: Add CSS custom properties to `#nws-overlay`**

Find the `#nws-overlay` CSS block (around line 224). Replace the `@keyframes nws-flash` block:

Old:
```css
@keyframes nws-flash {
    0%, 100% { background: #cc0000; }
    50%       { background: #ff1111; }
}
```

New (the custom properties allow JS to drive the color; defaults stay red for tornado):
```css
#nws-overlay {
    --nws-base:   #cc0000;
    --nws-bright: #ff1111;
}

@keyframes nws-flash {
    0%, 100% { background: var(--nws-base); }
    50%       { background: var(--nws-bright); }
}
```

- [ ] **Step 2: Add `getNWSColors()` function to the JS section**

In the script block, immediately before the existing `nwsSeverityToType()` function (around line 692), add:

```javascript
// Maps NWS event type to TV broadcast color pair {base, bright}
const NWS_EVENT_COLORS = {
    tornado:      { base: '#cc0000', bright: '#ff2222' },
    thunderstorm: { base: '#FF8C00', bright: '#ffaa44' },
    flood:        { base: '#006400', bright: '#009900' },
    watch:        { base: '#b8860b', bright: '#e6a817' },
    default:      { base: '#444444', bright: '#666666' },
};

function getNWSColors(event) {
    const e = (event || '').toLowerCase();
    if (e.includes('tornado warning') || e.includes('tornado emergency') || e.includes('extreme wind warning')) return NWS_EVENT_COLORS.tornado;
    if (e.includes('severe thunderstorm warning')) return NWS_EVENT_COLORS.thunderstorm;
    if (e.includes('flash flood warning') || e.includes('flash flood emergency')) return NWS_EVENT_COLORS.flood;
    if (e.includes('tornado watch') || e.includes('severe thunderstorm watch')) return NWS_EVENT_COLORS.watch;
    return NWS_EVENT_COLORS.default;
}
```

- [ ] **Step 3: Update `updateOverlay()` to apply dynamic colors**

Find `updateOverlay()` (around line 704). Replace it with:

```javascript
function updateOverlay() {
    const overlay = document.getElementById('nws-overlay');
    const top = nwsAlerts.find(a => a._overlay);
    if (top) {
        const colors = getNWSColors(top._event);
        overlay.style.setProperty('--nws-base',   colors.base);
        overlay.style.setProperty('--nws-bright',  colors.bright);
        document.getElementById('nws-overlay-event').textContent    = top._event;
        document.getElementById('nws-overlay-headline').textContent = top._headline || top._event;
        overlay.classList.add('active');
    } else {
        overlay.classList.remove('active');
    }
}
```

- [ ] **Step 4: Update `index.html` revision header**

Change:
```html
    Revision   : 1.5.0
```
To:
```html
    Revision   : 1.6.0
```
Add to changelog:
```html
    1.6.0  fix NWS overlay to use correct TV broadcast colors per event type; add weather advisory demo overlay
```

- [ ] **Step 5: Test in browser**

Open `index.html`. With no active NWS alerts the overlay should stay hidden. To test color logic without a real alert, temporarily add this to the browser console:

```javascript
nwsAlerts = [{message:'test',type:'severe',_event:'Severe Thunderstorm Warning',_headline:'Test',_overlay:true}];
updateOverlay();
```

Expected: overlay shows with **orange** background (`#FF8C00`), not red.

Then test tornado:
```javascript
nwsAlerts = [{message:'test',type:'severe',_event:'Tornado Warning',_headline:'Test',_overlay:true}];
updateOverlay();
```

Expected: overlay shows with **red** background (`#cc0000`).

Run `nwsAlerts = []; updateOverlay();` to clear.

- [ ] **Step 6: Commit**

```bash
git add time-clock/index.html
git commit -m "Fix NWS overlay colors to match TV broadcast standards (orange=SVR, red=TOR, green=FFW)"
```

---

## Task 3: Add weather demo overlay to `index.html`

**Files:**
- Modify: `time-clock/index.html`

- [ ] **Step 1: Add `#weather-demo-overlay` div to the HTML**

Find the Demo Alert Overlay comment (around line 491). Insert the new overlay **immediately after** the closing `</div>` of `#demo-overlay` and before the `<style>@keyframes demo-flash...</style>` tag:

```html
<!-- Weather Advisory Demo Overlay -->
<div id="weather-demo-overlay" style="display:none;position:fixed;inset:0;z-index:10003;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px;animation:demo-flash 1.4s ease-in-out infinite;">
    <div style="font-size:clamp(14px,1.8vw,26px);font-weight:700;color:#5bc8f5;letter-spacing:3px;text-transform:uppercase;margin-bottom:12px;">&#128203; WEATHER ADVISORY &#8212; DEMO</div>
    <div id="weather-demo-event" style="font-size:clamp(52px,10vw,150px);font-weight:900;color:#ffffff;text-transform:uppercase;letter-spacing:5px;line-height:1.1;">Severe Thunderstorm Warning</div>
    <div id="weather-demo-headline" style="font-size:clamp(18px,2.8vw,44px);font-weight:600;color:#aad4f5;margin-top:20px;letter-spacing:2px;max-width:90%;">Severe Thunderstorm Warning issued by NWS Cleveland OH</div>
    <div style="font-size:clamp(12px,1.5vw,22px);color:#7fb8d8;margin-top:16px;letter-spacing:3px;text-transform:uppercase;">&#9651; NATIONAL WEATHER SERVICE &#8212; THIS IS A DRILL</div>
    <div class="overlay-clock" id="weather-demo-overlay-clock"></div>
</div>
```

- [ ] **Step 2: Add `weather-demo-overlay-clock` to the clock update loop**

Find the clock array in `updateClock()` (around line 548):

Old:
```javascript
['fire-overlay-clock','shooter-overlay-clock','demo-overlay-clock'].forEach(id => {
```

New:
```javascript
['fire-overlay-clock','shooter-overlay-clock','demo-overlay-clock','weather-demo-overlay-clock'].forEach(id => {
```

- [ ] **Step 3: Add `fetchWeatherDemo()` poll function**

In the script block, after the `fetchDemoAlarm()` / `setInterval(fetchDemoAlarm, ...)` block (around line 789), add:

```javascript
// ── Weather Advisory Demo Poll ───────────────────────────────
async function fetchWeatherDemo() {
    try {
        const res  = await fetch(`weather-demo.json?_=${Date.now()}`);
        const data = await res.json();
        const active = data.active && data.expiresAt && (Date.now() / 1000) < data.expiresAt;
        const overlay = document.getElementById('weather-demo-overlay');
        if (active) {
            document.getElementById('weather-demo-event').textContent    = data.event    || 'Severe Thunderstorm Warning';
            document.getElementById('weather-demo-headline').textContent = data.headline || 'Severe Thunderstorm Warning issued by NWS Cleveland OH';
            overlay.style.display = 'flex';
        } else {
            overlay.style.display = 'none';
        }
    } catch (err) {
        // silently ignore — don't clear overlay on fetch failure
    }
}

fetchWeatherDemo();
setInterval(fetchWeatherDemo, 5 * 1000);
```

- [ ] **Step 4: Test in browser**

Open `index.html`. Manually set `weather-demo.json` to active with a future `expiresAt` (current Unix time + 300) to verify the blue overlay appears with correct content. The clock should tick on the overlay. Verify it hides when `active: false`.

- [ ] **Step 5: Commit**

```bash
git add time-clock/index.html
git commit -m "Add weather advisory demo overlay to kiosk (blue, 5-min auto-expiry, real NWS field content)"
```

---

## Task 4: Add Weather Advisory Demo section to `admin.html`

**Files:**
- Modify: `time-clock/admin.html`

- [ ] **Step 1: Add the Weather Advisory Demo HTML section**

Find the Active Shooter section closing `</div>` (around line 324). Insert the new section immediately after it, before the `</div>` closing the `.container`:

```html
        <!-- Weather Advisory Demo -->
        <div class="add-section" style="margin-top:12px;border-color:#1a5276;background:#eaf4fb;">
            <h2 style="color:#1a5276;">&#127785; Weather Advisory Demo</h2>
            <p style="font-size:13px;color:#555;margin-bottom:14px;">Triggers a blue weather overlay with NWS-format content for Girard, OH (Trumbull County). Auto-clears after 5 minutes. Blue color — will not cause public alarm.</p>
            <div style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:8px 14px;margin-bottom:12px;font-size:12px;color:#555;">
                <strong>Data source:</strong> NWS Cleveland OH &nbsp;&middot;&nbsp; Trumbull County, OH (Girard area)
            </div>
            <div id="weather-demo-countdown" style="display:none;background:#1a5276;border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#aed6f1;justify-content:space-between;align-items:center;">
                <span>&#9200; Demo expires in: <strong id="weather-demo-timer" style="color:#fff;">5:00</strong></span>
                <span id="weather-demo-expires-at" style="font-size:11px;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <button style="background:#1a5276;color:#fff;padding:10px 18px;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;" onclick="setAlarm('weather','on')">&#127785; Activate (5 min)</button>
                <button class="btn-save" onclick="setAlarm('weather','off')">&#10003; Clear</button>
                <span id="alarm-status-weather" style="font-size:13px;color:#888;"></span>
            </div>
        </div>
```

- [ ] **Step 2: Add `weatherCountdownInterval` variable and `startWeatherCountdown()` function**

In the `<script>` block, immediately after `let fileLastModified = '';` (around line 332), add:

```javascript
let weatherCountdownInterval = null;
```

Then, immediately before the `// ── Version ──` comment, add:

```javascript
// ── Weather Demo Countdown ────────────────────────────────────
function startWeatherCountdown(expiresAt) {
    clearInterval(weatherCountdownInterval);
    const countdownEl  = document.getElementById('weather-demo-countdown');
    const timerEl      = document.getElementById('weather-demo-timer');
    const expiresAtEl  = document.getElementById('weather-demo-expires-at');

    const expiresDate = new Date(expiresAt * 1000);
    expiresAtEl.textContent = 'auto-clears at ' + expiresDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    countdownEl.style.display = 'flex';

    weatherCountdownInterval = setInterval(() => {
        const remaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
        const m = Math.floor(remaining / 60);
        const s = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        if (remaining === 0) {
            clearInterval(weatherCountdownInterval);
            countdownEl.style.display = 'none';
            loadAlarmStatus('weather');
        }
    }, 1000);
}
```

- [ ] **Step 3: Update `loadFireAlarmStatus()` to include `weather`**

Find `loadFireAlarmStatus()` (around line 471):

Old:
```javascript
async function loadFireAlarmStatus() {
    await loadAlarmStatus('demo');
    await loadAlarmStatus('fire');
    await loadAlarmStatus('shooter');
}
```

New:
```javascript
async function loadFireAlarmStatus() {
    await loadAlarmStatus('demo');
    await loadAlarmStatus('fire');
    await loadAlarmStatus('shooter');
    await loadAlarmStatus('weather');
}
```

- [ ] **Step 4: Update `loadAlarmStatus()` to restore countdown if weather is active**

Find `loadAlarmStatus()` (around line 477). Replace it with:

```javascript
async function loadAlarmStatus(type) {
    const elId = `alarm-status-${type}`;
    try {
        const res  = await fetch(`set-alarm.php?type=${type}&_=` + Date.now());
        const data = await res.json();
        updateAlarmStatus(type, data.active);
        if (type === 'weather' && data.active && data.expiresAt) {
            startWeatherCountdown(data.expiresAt);
        }
    } catch (err) {
        document.getElementById(elId).textContent = 'Status unavailable';
    }
}
```

- [ ] **Step 5: Update `setAlarm()` to handle weather countdown and mutual exclusivity**

Find `setAlarm()` (around line 488). Replace it with:

```javascript
async function setAlarm(type, action) {
    const el = document.getElementById(`alarm-status-${type}`);
    el.textContent = action === 'on' ? 'Activating...' : 'Clearing...';
    try {
        const res  = await fetch('set-alarm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, action })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || res.status);
        updateAlarmStatus(type, data.active);
        // Start countdown when weather demo is activated
        if (type === 'weather' && action === 'on' && data.expiresAt) {
            startWeatherCountdown(data.expiresAt);
        }
        // Clear countdown when weather demo is manually cleared
        if (type === 'weather' && action === 'off') {
            clearInterval(weatherCountdownInterval);
            document.getElementById('weather-demo-countdown').style.display = 'none';
        }
        // Refresh all others since server clears them on activate
        if (action === 'on') {
            for (const t of ['demo', 'fire', 'shooter', 'weather']) {
                if (t !== type) await loadAlarmStatus(t);
            }
        }
    } catch (err) {
        el.textContent = 'Error: ' + err.message;
    }
}
```

- [ ] **Step 6: Update `admin.html` revision header**

Change:
```html
    Revision   : 2.4.0
    Modified   : 2026-05-22
```
To:
```html
    Revision   : 2.5.0
    Modified   : 2026-05-22
```
Add to changelog:
```html
    2.5.0  add weather advisory demo section with 5-minute countdown
```

- [ ] **Step 7: Test end-to-end**

1. Open `admin.html` in browser, unlock with PIN (`changeme`)
2. Scroll to "Weather Advisory Demo" section — status should show ● Inactive
3. Click "Activate (5 min)" — countdown bar should appear showing `5:00` counting down, expiry time shown
4. Open `index.html` in another tab — blue weather overlay should appear within 5 seconds showing "SEVERE THUNDERSTORM WARNING" and NWS Cleveland OH headline
5. Click "Clear" on admin — overlay disappears on kiosk within 5 seconds, countdown bar hides
6. Re-activate and wait 5 minutes — overlay auto-disappears, admin shows Inactive

- [ ] **Step 8: Commit**

```bash
git add time-clock/admin.html
git commit -m "Add Weather Advisory Demo section to admin with 5-min countdown and auto-expiry"
```

---

## Task 5: Final sync and version bump

**Files:**
- Modify: `time-clock/version.json`

- [ ] **Step 1: Update `version.json`**

```json
{
  "deployedAt": 1779485747,
  "files": {
    "index.html": "1.6.0",
    "admin.html": "2.5.0",
    "save-alerts.php": "1.2.0",
    "set-alarm.php": "1.3.0",
    "updates.html": "1.0.0"
  }
}
```

- [ ] **Step 2: Final commit and push**

```bash
git add time-clock/version.json
git commit -m "Bump version.json for weather advisory demo release"
git push origin main
```
