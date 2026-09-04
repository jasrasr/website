<?php
declare(strict_types=1);

$dataFile = __DIR__ . '/data/ticket-counts.json';
$apiDataFile = __DIR__ . '/storage/api-snapshots.json';
$pullLogFile = __DIR__ . '/storage/pull-log.json';
$payload = ['goal' => 0, 'entries' => []];
$pullLog = [];
$error = null;

if (is_file($dataFile)) {
    $decoded = json_decode((string) file_get_contents($dataFile), true);
    if (is_array($decoded) && isset($decoded['entries']) && is_array($decoded['entries'])) {
        $payload = $decoded;
    } else {
        $error = 'Ticket data could not be read.';
    }
} else {
    $error = 'Ticket data file was not found.';
}

if (is_file($apiDataFile)) {
    $apiDecoded = json_decode((string) file_get_contents($apiDataFile), true);
    if (is_array($apiDecoded) && isset($apiDecoded['entries']) && is_array($apiDecoded['entries'])) {
        $payload['entries'] = array_merge($payload['entries'], $apiDecoded['entries']);
    }
}

if (is_file($pullLogFile)) {
    $pullLogDecoded = json_decode((string) file_get_contents($pullLogFile), true);
    if (is_array($pullLogDecoded) && isset($pullLogDecoded['entries']) && is_array($pullLogDecoded['entries'])) {
        $pullLog = $pullLogDecoded['entries'];
    }
}

$entries = $payload['entries'];
usort($entries, static fn(array $a, array $b): int =>
    (easternDate($a['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN)
    <=> (easternDate($b['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN));
$goal = max(0, (int) ($payload['goal'] ?? 0));
$latest = $entries ? $entries[array_key_last($entries)] : null;
$previous = count($entries) > 1 ? $entries[count($entries) - 2] : null;
$current = (int) ($latest['unresolved'] ?? 0);
$change = $previous ? $current - (int) ($previous['unresolved'] ?? 0) : null;
$analytics = $latest && isset($latest['analytics']) && is_array($latest['analytics']) ? $latest['analytics'] : [];
$latestDay = easternDate($latest['capturedAt'] ?? null)?->format('Y-m-d');
$todayActivity = [
    'enteredUnresolved' => 0,
    'exitedUnresolved' => 0,
    'newTickets' => 0,
    'assignedIn' => 0,
    'reopened' => 0,
    'resolved' => 0,
    'closed' => 0,
    'reassignedAway' => 0,
];
foreach ($entries as $entry) {
    if ($latestDay === null || empty($entry['capturedAt']) || empty($entry['activity']) || !is_array($entry['activity'])) continue;
    if (easternDate($entry['capturedAt'])?->format('Y-m-d') !== $latestDay) continue;
    foreach ($todayActivity as $key => $value) {
        $todayActivity[$key] += (int) ($entry['activity'][$key] ?? 0);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function easternDate(?string $value): ?DateTimeImmutable
{
    if ($value === null || trim($value) === '') return null;
    try {
        $timezone = new DateTimeZone('America/New_York');
        return (new DateTimeImmutable($value, $timezone))->setTimezone($timezone);
    } catch (Throwable) {
        return null;
    }
}

function displayDate(?string $value): string
{
    return easternDate($value)?->format('M j, Y g:i A') ?? '—';
}

function analyticsRows(array $analytics, string $key): array
{
    if (!isset($analytics[$key]) || !is_array($analytics[$key])) return [];
    return array_filter($analytics[$key], static fn(mixed $count): bool => is_numeric($count) && (int) $count > 0);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Freshservice Ticket Tracker</title>
    <style>
        :root { --bg:#07111f; --panel:#101d2f; --panel2:#16253a; --text:#f3f7fb; --muted:#93a4ba; --blue:#4d95ff; --green:#3ddc84; --red:#ff6b72; --line:#29405d; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:radial-gradient(circle at top right,#132846 0,#07111f 42%); color:var(--text); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { width:min(1120px,calc(100% - 28px)); margin:auto; padding:32px 0 48px; }
        header { display:flex; justify-content:space-between; gap:20px; align-items:flex-end; margin-bottom:24px; }
        h1 { margin:0; font-size:clamp(1.7rem,4vw,2.7rem); letter-spacing:-.04em; }
        header p,.muted { color:var(--muted); }
        header p { margin:.4rem 0 0; }
        .header-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:10px; align-items:center; }
        .badge,.pull-button { padding:8px 12px; border:1px solid var(--line); border-radius:999px; font-weight:700; white-space:nowrap; }
        .badge { color:var(--green); background:#0d211c; }
        .pull-button { appearance:none; color:#fff; background:#1768d8; cursor:pointer; font:inherit; }
        .pull-button:hover { background:#2478ed; }
        .pull-button:disabled { cursor:wait; opacity:.65; }
        .pull-status { width:100%; min-height:1.2em; color:var(--muted); text-align:right; font-size:.85rem; }
        .pull-status.error-text { color:#ffbec1; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .card,.section { background:linear-gradient(145deg,var(--panel2),var(--panel)); border:1px solid var(--line); border-radius:18px; box-shadow:0 16px 38px rgba(0,0,0,.2); }
        .card { padding:20px; min-height:138px; }
        .label { display:block; margin-bottom:18px; color:var(--muted); font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .value { display:block; font-size:clamp(2rem,5vw,3.5rem); font-weight:850; line-height:1; }
        .value.good { color:var(--green); }.value.bad { color:var(--red); }
        .sub { display:block; color:var(--muted); margin-top:10px; font-size:.9rem; }
        .section { margin-top:14px; padding:22px; }
        .section h2 { margin:0 0 16px; font-size:1.15rem; }
        .activity-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
        .activity-item { padding:15px; border-radius:13px; background:#0b1727; border:1px solid var(--line); }
        .activity-item strong { display:block; margin-top:5px; font-size:1.8rem; }
        .analytics-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .analytics-chart { padding:18px; border-radius:14px; background:#0b1727; border:1px solid var(--line); }
        .analytics-chart h3 { margin:0 0 16px; font-size:1rem; }
        .bar-row { display:grid; grid-template-columns:minmax(95px,1.25fr) minmax(120px,3fr) auto; gap:10px; align-items:center; margin:10px 0; }
        .bar-label { overflow:hidden; color:#dce7f5; text-overflow:ellipsis; white-space:nowrap; font-size:.86rem; }
        .bar-track { height:10px; overflow:hidden; border-radius:999px; background:#07111f; }
        .bar-fill { display:block; height:100%; min-width:3px; border-radius:inherit; background:linear-gradient(90deg,var(--blue),var(--green)); }
        .bar-value { min-width:2ch; text-align:right; font-weight:800; font-variant-numeric:tabular-nums; }
        canvas { display:block; width:100%; height:280px; }
        .trend-viewport { overflow-x:auto; }
        .chart-controls { display:flex; flex-wrap:wrap; gap:10px 18px; margin-bottom:14px; }
        .chart-controls label { display:flex; gap:6px; align-items:center; font-size:.9rem; }
        .chart-controls input { width:18px; height:18px; }
        .chart-day-picker { margin-top:14px; }
        .chart-day-picker select { max-width:100%; padding:7px; background:#0b1727; color:var(--text); border:1px solid var(--line); border-radius:6px; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:680px; }
        th,td { padding:13px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { color:var(--muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; }
        td.number { font-weight:800; }
        .error { padding:14px; border:1px solid #74363a; border-radius:12px; background:#2d171b; color:#ffbec1; }
        .celebration { position:fixed; inset:0; z-index:1000; display:grid; place-items:center; overflow:hidden; padding:24px; background:rgba(3,9,18,.84); backdrop-filter:blur(8px); }
        .celebration[hidden] { display:none; }
        .celebration-canvas { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; }
        .celebration-banner { position:relative; width:min(760px,100%); padding:clamp(30px,7vw,70px); border:2px solid #ffe082; border-radius:28px; background:linear-gradient(145deg,#183b62,#102139); box-shadow:0 24px 90px rgba(0,0,0,.55),0 0 60px rgba(61,220,132,.2); text-align:center; }
        .celebration-emoji { display:block; font-size:clamp(3rem,10vw,6rem); line-height:1; }
        .celebration h2 { margin:18px 0 8px; font-size:clamp(2.2rem,8vw,5rem); line-height:.95; letter-spacing:-.05em; color:#fff3a8; text-transform:uppercase; }
        .celebration p { margin:16px auto 0; max-width:560px; color:#dcecff; font-size:clamp(1.05rem,3vw,1.4rem); }
        .celebration-close { margin-top:26px; padding:11px 20px; border:1px solid #8dbfff; border-radius:999px; color:#fff; background:#1768d8; cursor:pointer; font:inherit; font-weight:700; }
        footer { color:var(--muted); text-align:center; margin-top:24px; font-size:.85rem; }
        @media (max-width:820px) { .grid,.activity-grid { grid-template-columns:repeat(2,1fr); } .analytics-grid { grid-template-columns:1fr; } header { align-items:flex-start; flex-direction:column; } .header-actions { justify-content:flex-start; } .pull-status { text-align:left; } }
        @media (max-width:480px) { main { width:min(100% - 18px,1120px); padding-top:18px; } .grid { grid-template-columns:1fr 1fr; gap:9px; } .card { min-height:120px; padding:15px; } .section { padding:16px; } .bar-row { grid-template-columns:minmax(0,1fr) auto; gap:6px 10px; } .bar-label { overflow:visible; text-overflow:clip; white-space:normal; } .bar-value { grid-column:2; grid-row:1; } .bar-track { grid-column:1 / -1; grid-row:2; } }
    </style>
</head>
<body>
<?php if ($latest !== null && $current === 0): ?>
<div class="celebration" id="goal-celebration" role="dialog" aria-modal="true" aria-labelledby="celebration-title">
    <canvas class="celebration-canvas" id="fireworks" aria-hidden="true"></canvas>
    <div class="celebration-banner">
        <span class="celebration-emoji" aria-hidden="true">👏 🎆 👏</span>
        <h2 id="celebration-title">Zero achieved!</h2>
        <p>The unresolved queue is clear. Outstanding work—take a bow!</p>
        <button type="button" class="celebration-close" id="celebration-close">Continue to dashboard</button>
    </div>
</div>
<?php endif; ?>
<main>
    <header>
        <div><h1>Freshservice Ticket Tracker</h1><p>Working the unresolved queue toward zero.</p><p>All times are Eastern (EDT/EST), adjusted automatically for daylight saving time.</p></div>
        <div class="header-actions">
            <div class="badge">Goal: <?= $goal ?> unresolved</div>
            <button type="button" class="pull-button" id="pull-tickets">Pull tickets now</button>
            <span class="pull-status" id="pull-status" role="status" aria-live="polite"></span>
        </div>
    </header>

    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

    <section class="grid" aria-label="Ticket summary">
        <article class="card"><span class="label">Current unresolved</span><span class="value"><?= number_format($current) ?></span><span class="sub">As of <?= e(displayDate($latest['capturedAt'] ?? null)) ?></span></article>
        <article class="card"><span class="label">Change since prior</span><span class="value <?= $change !== null && $change <= 0 ? 'good' : 'bad' ?>"><?= $change === null ? '—' : sprintf('%+d', $change) ?></span><span class="sub"><?= $change === null ? 'Needs a second snapshot' : ($change <= 0 ? 'Moving the right direction' : 'Queue increased') ?></span></article>
    </section>

    <section class="section">
        <h2>Activity on <?= $latestDay ? e(easternDate($latestDay)->format('M j, Y')) : 'latest day' ?></h2>
        <div class="activity-grid">
            <div class="activity-item"><span class="label">Entered unresolved</span><strong><?= number_format($todayActivity['enteredUnresolved']) ?></strong><span class="sub">New, assigned, or reopened</span></div>
            <div class="activity-item"><span class="label">Exited unresolved</span><strong><?= number_format($todayActivity['exitedUnresolved']) ?></strong><span class="sub">Resolved, closed, or reassigned</span></div>
            <div class="activity-item"><span class="label">New tickets</span><strong><?= number_format($todayActivity['newTickets']) ?></strong><span class="sub"><?= number_format($todayActivity['assignedIn']) ?> assigned in · <?= number_format($todayActivity['reopened']) ?> reopened</span></div>
            <div class="activity-item"><span class="label">Completed</span><strong><?= number_format($todayActivity['resolved'] + $todayActivity['closed']) ?></strong><span class="sub"><?= number_format($todayActivity['resolved']) ?> resolved · <?= number_format($todayActivity['closed']) ?> closed</span></div>
        </div>
        <?php if (($latest['source'] ?? '') !== 'freshservice-api'): ?><p class="muted">Detailed activity begins after the API collector is configured and has completed at least two runs.</p><?php endif; ?>
    </section>

    <section class="section"><h2>Daily ticket totals and activity</h2>
        <div class="chart-controls" role="group" aria-label="Visible chart series">
            <label style="color:#4d95ff"><input type="checkbox" data-chart-series="unresolved" checked>Unresolved</label>
            <label style="color:#ffad4d"><input type="checkbox" data-chart-series="newTickets" checked>New</label>
            <label style="color:#3ddc84"><input type="checkbox" data-chart-series="completed" checked>Resolved/Closed</label>
        </div>
        <div class="trend-viewport" tabindex="0" role="region" aria-label="Scrollable combined daily chart"><canvas id="trend" role="img" aria-label="Daily unresolved, new and resolved or closed ticket lines with activity bars"></canvas></div>
        <div class="chart-day-picker"><label for="trend-day">Daily details: </label><select id="trend-day"></select></div>
        <p id="chart-note" class="muted" aria-live="polite"></p>
        <p class="muted">The combined view uses lines for all three series and bars for New and Resolved/Closed. Activity is grouped by the day of the pull—not guaranteed event-day totals. A later resolved-to-closed change is not counted again. A reopened ticket completed again can count again. Missed transitions cannot be reconstructed. Missing activity is unknown, not zero; known zeroes are labeled 0.</p>
    </section>

    <section class="section"><h2>Current queue analytics</h2>
        <?php if (!$analytics): ?>
            <p class="muted">Analytics will appear after the next API pull.</p>
        <?php else: ?>
            <div class="analytics-grid">
            <?php foreach ([
                'status' => 'Tickets by status',
                'category' => 'Tickets by category',
                'priority' => 'Tickets by priority',
                'age' => 'Tickets by age',
                'requesterDistribution' => 'Requesters by unresolved ticket count',
            ] as $analyticsKey => $analyticsTitle):
                $rows = analyticsRows($analytics, $analyticsKey);
                $chartMax = $rows ? max(array_map('intval', $rows)) : 1;
            ?>
                <article class="analytics-chart">
                    <h3><?= e($analyticsTitle) ?></h3>
                    <?php if (!$rows): ?><p class="muted">No data</p><?php endif; ?>
                    <?php foreach ($rows as $label => $count): ?>
                        <div class="bar-row">
                            <span class="bar-label" title="<?= e((string) $label) ?>"><?= e((string) $label) ?></span>
                            <span class="bar-track" aria-hidden="true"><span class="bar-fill" style="width:<?= e(number_format(((int) $count / $chartMax) * 100, 2, '.', '')) ?>%"></span></span>
                            <span class="bar-value" aria-label="<?= e((string) $label) ?>: <?= number_format((int) $count) ?>"><?= number_format((int) $count) ?></span>
                        </div>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
            </div>
            <p class="muted">Requester IDs never appear on this public page. Categories with fewer than three tickets are grouped into Other.</p>
        <?php endif; ?>
    </section>

    <section class="section"><h2>Snapshot history</h2><div class="table-wrap"><table><thead><tr><th>Captured</th><th>Unresolved</th><th>Change</th><th>Entered</th><th>Exited</th><th>Source</th><th>Note</th></tr></thead><tbody>
    <?php foreach (array_reverse($entries) as $index => $entry):
        $originalIndex = count($entries) - 1 - $index;
        $rowChange = $originalIndex > 0 ? (int) $entry['unresolved'] - (int) $entries[$originalIndex - 1]['unresolved'] : null;
        $rowActivity = isset($entry['activity']) && is_array($entry['activity']) ? $entry['activity'] : [];
    ?><tr><td><?= e(displayDate($entry['capturedAt'] ?? null)) ?></td><td class="number"><?= number_format((int) ($entry['unresolved'] ?? 0)) ?></td><td><?= $rowChange === null ? '—' : sprintf('%+d', $rowChange) ?></td><td><?= array_key_exists('enteredUnresolved', $rowActivity) ? number_format((int) $rowActivity['enteredUnresolved']) : '—' ?></td><td><?= array_key_exists('exitedUnresolved', $rowActivity) ? number_format((int) $rowActivity['exitedUnresolved']) : '—' ?></td><td><?= e((string) ($entry['source'] ?? 'unknown')) ?></td><td><?= e((string) ($entry['note'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="section"><h2>API pull log</h2>
        <?php if (!$pullLog): ?>
            <p class="muted">No collector attempts have been logged yet. The next pull will appear here.</p>
        <?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Attempted</th><th>Result</th><th>Starting</th><th>Ending</th><th>Net</th><th>Entered</th><th>Exited</th><th>Details</th></tr></thead><tbody>
            <?php foreach (array_reverse($pullLog) as $pull):
                $pullActivity = isset($pull['activity']) && is_array($pull['activity']) ? $pull['activity'] : [];
                $pullOk = (bool) ($pull['ok'] ?? false);
            ?><tr>
                <td><?= e(displayDate($pull['attemptedAt'] ?? null)) ?></td>
                <td class="number <?= $pullOk ? 'good' : 'bad' ?>"><?= $pullOk ? 'Success' : 'Failed' ?></td>
                <td><?= $pullOk ? number_format((int) ($pull['startingUnresolved'] ?? 0)) : '—' ?></td>
                <td><?= $pullOk ? number_format((int) ($pull['endingUnresolved'] ?? 0)) : '—' ?></td>
                <td><?= $pullOk ? sprintf('%+d', (int) ($pull['netChange'] ?? 0)) : '—' ?></td>
                <td><?= $pullOk ? number_format((int) ($pullActivity['enteredUnresolved'] ?? 0)) : '—' ?></td>
                <td><?= $pullOk ? number_format((int) ($pullActivity['exitedUnresolved'] ?? 0)) : '—' ?></td>
                <td><?= $pullOk ? ((bool) ($pull['initialRun'] ?? false) ? 'Baseline initialized' : 'Completed') : e((string) ($pull['error'] ?? 'Unknown error')) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <footer>Aggregate counts only. No ticket subjects, requesters, or credentials are stored here.</footer>
</main>
<script>
const entries = <?= json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const pullButton = document.getElementById('pull-tickets');
const pullStatus = document.getElementById('pull-status');
const canvas = document.getElementById('trend');
const note = document.getElementById('chart-note');
const celebration = document.getElementById('goal-celebration');
const celebrationClose = document.getElementById('celebration-close');
const fireworksCanvas = document.getElementById('fireworks');

if (celebration && celebrationClose) {
    celebrationClose.focus();
    celebrationClose.addEventListener('click', () => { celebration.hidden = true; });
    celebration.addEventListener('click', event => { if (event.target === celebration) celebration.hidden = true; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') celebration.hidden = true; });
}

if (fireworksCanvas && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const context = fireworksCanvas.getContext('2d');
    const colors = ['#ffe082','#3ddc84','#4d95ff','#ff6b72','#ffffff'];
    let particles = [], lastBurst = 0, animationStart = performance.now();
    const resizeFireworks = () => {
        const ratio = window.devicePixelRatio || 1;
        fireworksCanvas.width = Math.round(innerWidth * ratio);
        fireworksCanvas.height = Math.round(innerHeight * ratio);
        fireworksCanvas.style.width = `${innerWidth}px`;
        fireworksCanvas.style.height = `${innerHeight}px`;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
    };
    const burst = () => {
        const x = innerWidth * (.15 + Math.random() * .7), y = innerHeight * (.1 + Math.random() * .45);
        const color = colors[Math.floor(Math.random() * colors.length)];
        for (let i = 0; i < 54; i++) {
            const angle = Math.PI * 2 * i / 54, speed = 1.5 + Math.random() * 4;
            particles.push({x,y,vx:Math.cos(angle)*speed,vy:Math.sin(angle)*speed,life:1,color});
        }
    };
    const animateFireworks = now => {
        context.clearRect(0, 0, innerWidth, innerHeight);
        if (now - lastBurst > 550 && now - animationStart < 8500) { burst(); lastBurst = now; }
        particles = particles.filter(particle => particle.life > .02);
        for (const particle of particles) {
            particle.x += particle.vx; particle.y += particle.vy; particle.vy += .035; particle.vx *= .992; particle.life *= .975;
            context.globalAlpha = particle.life; context.fillStyle = particle.color;
            context.beginPath(); context.arc(particle.x, particle.y, 2.2, 0, Math.PI * 2); context.fill();
        }
        context.globalAlpha = 1;
        if (now - animationStart < 10000 && celebration && !celebration.hidden) requestAnimationFrame(animateFireworks);
    };
    resizeFireworks(); window.addEventListener('resize', resizeFireworks); requestAnimationFrame(animateFireworks);
}

async function pullTickets(token) {
    pullButton.disabled = true;
    pullStatus.classList.remove('error-text');
    pullStatus.textContent = 'Contacting Freshservice…';

    try {
        const response = await fetch('api/collect.php', {
            method: 'POST',
            headers: { 'X-Collector-Token': token.trim(), 'Accept': 'application/json' },
            cache: 'no-store'
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || `Collector returned HTTP ${response.status}.`);
        pullStatus.textContent = `Pulled ${Number(result.endingUnresolved).toLocaleString()} unresolved tickets. Refreshing…`;
        window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        pullStatus.classList.add('error-text');
        pullStatus.textContent = error instanceof Error ? error.message : 'The ticket pull failed.';
        pullButton.disabled = false;
    }
}

pullButton.addEventListener('click', async () => {
    const token = window.prompt('Enter the private collector token from config.local.php:');
    if (token === null || token.trim() === '') return;
    await pullTickets(token.trim());
});

async function autoPullOnPageLoad() {
    pullStatus.textContent = 'Checking for updated tickets…';
    try {
        const response = await fetch('api/auto.php', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || `Auto-pull returned HTTP ${response.status}.`);
        if (result.pulled === false) {
            pullStatus.textContent = '';
            return;
        }
        pullStatus.textContent = `Automatically pulled ${Number(result.endingUnresolved).toLocaleString()} unresolved tickets. Refreshing…`;
        window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        pullStatus.classList.add('error-text');
        pullStatus.textContent = error instanceof Error ? error.message : 'The automatic ticket pull failed.';
    }
}
autoPullOnPageLoad();

const chartTimezone = 'America/New_York';
const calendarDayMs = 24 * 60 * 60 * 1000;
const chartDayFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: chartTimezone, year: 'numeric', month: '2-digit', day: '2-digit'
});

function chartDay(time) {
    const parts = Object.fromEntries(chartDayFormatter.formatToParts(time).map(part => [part.type, part.value]));
    return Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day));
}

// Display aggregation only: keep every raw snapshot for history and activity.
function dailyChartSamples(rawEntries) {
    const days = new Map();
    for (const entry of rawEntries) {
        const value = Number(entry.unresolved), time = Date.parse(entry.capturedAt);
        if (entry.unresolved === null || entry.unresolved === '' || !Number.isFinite(value) ||
            value < 0 || !Number.isFinite(time)) continue;
        const day = chartDay(time);
        let daily = days.get(day);
        if (!daily) {
            daily = {value, time, day, newTickets:null, completed:null, resolved:null, closed:null};
            days.set(day, daily);
        }
        if (time >= daily.time) { daily.value = value; daily.time = time; }
        // Baseline zeroes mean no comparison, not a measured day with no activity.
        const baseline = entry.initialRun === true || /baseline initialized/i.test(entry.note || '');
        const activity = !baseline && entry.activity;
        const valid = count => (typeof count === 'number' || (typeof count === 'string' && count.trim() !== '')) &&
            Number.isInteger(Number(count)) && Number(count) >= 0;
        if (activity && valid(activity.newTickets)) {
            daily.newTickets = (daily.newTickets ?? 0) + Number(activity.newTickets);
        }
        if (activity && valid(activity.resolved) && valid(activity.closed)) {
            daily.resolved = (daily.resolved ?? 0) + Number(activity.resolved);
            daily.closed = (daily.closed ?? 0) + Number(activity.closed);
            daily.completed = daily.resolved + daily.closed;
        }
    }
    return [...days.values()].sort((a, b) => a.day - b.day);
}

const chartSeries = {unresolved:true, newTickets:true, completed:true};
const dayPicker = document.getElementById('trend-day');
document.querySelectorAll('[data-chart-series]').forEach(control => {
    control.addEventListener('change', () => {
        chartSeries[control.dataset.chartSeries] = control.checked;
        drawChart();
    });
});
if (dayPicker) dayPicker.addEventListener('change', () => {
    const hit = (canvas._trendHits || []).find(hit => String(hit.sample.day) === dayPicker.value);
    if (hit) showDailyDetails(hit);
});

function drawChart() {
    const samples = dailyChartSamples(entries);
    const viewport = canvas.parentElement;
    const p = {l:48, r:24, t:30, b:52}, h = 280;
    const lastDay = samples.at(-1)?.day;
    const dayCount = samples.length;
    const dayIndexes = new Map(samples.map((sample, index) => [sample.day, index]));
    // Only recorded days occupy axis slots; missing calendar dates are omitted.
    const w = Math.max(viewport.clientWidth, p.l + p.r + Math.max(1, dayCount - 1) * 52);
    const dpr = window.devicePixelRatio || 1;
    canvas.style.width = `${w}px`;
    canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
    const c = canvas.getContext('2d'); c.scale(dpr, dpr); c.clearRect(0, 0, w, h);
    canvas._trendHits = [];
    canvas._dataLabels = [];
    if (!samples.length) {
        note.textContent = 'No snapshots recorded.';
        if (dayPicker) { dayPicker.replaceChildren(); dayPicker.disabled = true; }
        return;
    }

    const max = Math.max(1, ...samples.flatMap(sample => [
        chartSeries.unresolved ? sample.value : 0,
        chartSeries.newTickets ? sample.newTickets ?? 0 : 0,
        chartSeries.completed ? sample.completed ?? 0 : 0
    ]));
    const plotWidth = w - p.l - p.r, plotHeight = h - p.t - p.b;
    const xFor = day => dayCount === 1 ? p.l + plotWidth / 2 :
        p.l + plotWidth * dayIndexes.get(day) / (dayCount - 1);
    const yFor = value => p.t + plotHeight * (max - value) / max;
    c.strokeStyle = '#29405d'; c.fillStyle = '#93a4ba'; c.font = '12px system-ui'; c.lineWidth = 1;
    for (let i = 0; i < 5; i++) {
        const y = p.t + plotHeight * i / 4;
        c.beginPath(); c.moveTo(p.l, y); c.lineTo(w - p.r, y); c.stroke();
        c.fillText(String(Math.round(max * (1 - i / 4))), 6, y + 4);
    }

    c.textAlign = 'center'; c.font = '11px system-ui';
    for (const {day} of samples) {
        const x = xFor(day), date = new Date(day);
        c.beginPath(); c.moveTo(x, h - p.b); c.lineTo(x, h - p.b + 6); c.stroke();
        c.fillText(date.toLocaleDateString('en-US', {timeZone:'UTC', month:'short'}), x, h - 23);
        c.fillText(String(date.getUTCDate()), x, h - 8);
    }

    // Label collision checks keep close/identical series values readable.
    const pendingLabels = [];
    const addLabel = (value, x, y, color) => pendingLabels.push({value, x, y, color});
    // Activity bars sit behind their matching lines; the unresolved count remains line-only.
    for (const sample of samples) {
        for (const [key, offset, color] of [['newTickets',-8,'#ffad4d'],['completed',8,'#3ddc84']]) {
            if (!chartSeries[key] || sample[key] === null) continue;
            const value = sample[key], x = xFor(sample.day) + offset, y = yFor(value);
            c.save(); c.globalAlpha = .34; c.fillStyle = color;
            c.fillRect(x - 6, y, 12, Math.max(1, h - p.b - y)); c.restore();
        }
    }
    // Unknown values break a line; no fabricated zero markers.
    c.lineWidth = 3; c.lineJoin = 'round'; c.font = '12px system-ui';
    for (const [key, field, color, radius] of [
        ['unresolved','value','#4d95ff',5],
        ['newTickets','newTickets','#ffad4d',4],
        ['completed','completed','#3ddc84',3]
    ]) {
        if (!chartSeries[key]) continue;
        c.strokeStyle = color;
        for (let i = 1; i < samples.length; i++) {
            const previous = samples[i - 1], current = samples[i];
            if (previous[field] === null || current[field] === null) continue;
            c.setLineDash(current.day - previous.day > calendarDayMs ? [5, 5] : []);
            c.beginPath(); c.moveTo(xFor(previous.day), yFor(previous[field]));
            c.lineTo(xFor(current.day), yFor(current[field])); c.stroke();
        }
        c.setLineDash([]);
        for (const sample of samples) {
            if (sample[field] === null) continue;
            const x = xFor(sample.day), y = yFor(sample[field]);
            c.fillStyle = color; c.beginPath(); c.arc(x, y, radius, 0, Math.PI * 2); c.fill();
            addLabel(sample[field], x, y, color);
        }
    }
    c.font = '12px system-ui';
    for (const label of pendingLabels) {
        const text = String(label.value), width = c.measureText(text).width;
        const x = Math.max(p.l + width / 2, Math.min(w - p.r - width / 2, label.x));
        const candidates = [label.y - 12, label.y - 28, label.y - 44, label.y + 18, label.y + 34, label.y + 50];
        for (let y = 16; y <= h - p.b - 4; y += 16) candidates.push(y);
        const boundsAt = y => ({left:x-width/2-3,right:x+width/2+3,top:y-12,bottom:y+3});
        const clear = box => !canvas._dataLabels.some(other =>
            box.left < other.right && box.right > other.left && box.top < other.bottom && box.bottom > other.top);
        const y = candidates.find(y => y >= 16 && y <= h-p.b-4 && clear(boundsAt(y))) ?? 16;
        canvas._dataLabels.push({...boundsAt(y),value:label.value,color:label.color});
        c.fillStyle = label.color; c.fillText(text, x, y);
    }
    canvas._trendHits = samples.map((sample, index) => ({
        x:xFor(sample.day), y:yFor(sample.value), sample, previous:samples[index - 1]
    }));
    c.textAlign = 'start';
    const todayIsPartial = lastDay === chartDay(Date.now());
    note.textContent = 'Blue: latest daily unresolved total. Orange: observed new tickets. Green: observed resolved/closed tickets. Activity is summed across daily pulls. ' +
        (todayIsPartial ? 'Today is still in progress. ' : '') +
        'Only recorded days are shown, evenly spaced; dates without readings are omitted. ' +
        'Dashed lines indicate skipped dates. Tap a date column or choose Daily details. Swipe horizontally to see every recorded date.';
    if (!Object.values(chartSeries).some(Boolean)) note.textContent = 'Select a series above to display it. Daily details remain available.';
    canvas.setAttribute('aria-label', `Combined daily ticket chart, ${samples.length} recorded days. Unresolved, New and Resolved or Closed use labeled lines; New and Resolved or Closed also use translucent bars. Unknown activity is omitted. Use Daily details for values.`);
    if (dayPicker) {
        const selected = dayPicker.value;
        dayPicker.replaceChildren(...samples.map(sample => {
            const option = document.createElement('option');
            option.value = String(sample.day);
            option.textContent = new Date(sample.day).toLocaleDateString('en-US', {timeZone:'UTC', month:'short', day:'numeric', year:'numeric'});
            return option;
        }));
        dayPicker.disabled = false;
        dayPicker.value = samples.some(sample => String(sample.day) === selected) ? selected : String(lastDay);
    }
    if (!canvas._initiallyScrolled) {
        viewport.scrollLeft = viewport.scrollWidth;
        canvas._initiallyScrolled = true;
    }
}

canvas.addEventListener('click', event => {
    const rect = canvas.getBoundingClientRect(), x = event.clientX - rect.left, y = event.clientY - rect.top;
    if (y < 18 || y > 280) return;
    let best = null, distance = Infinity;
    for (const hit of canvas._trendHits || []) {
        const candidate = Math.abs(x - hit.x);
        if (candidate < distance) { best = hit; distance = candidate; }
    }
    if (!best || distance > 28) return;
    if (dayPicker) dayPicker.value = String(best.sample.day);
    showDailyDetails(best);
});

function showDailyDetails({sample, previous}) {
    const observed = new Date(sample.time).toLocaleString('en-US', {
        timeZone: chartTimezone, month:'short', day:'numeric', year:'numeric',
        hour:'numeric', minute:'2-digit'
    });
    const delta = previous ? sample.value - previous.value : null;
    const gap = previous ? Math.round((sample.day - previous.day) / calendarDayMs) : 0;
    const changeLabel = delta === null ? 'First recorded day; no prior comparison.' :
        `Net change: ${delta > 0 ? '+' : ''}${delta} vs ${gap === 1 ? 'previous day' : `previous recorded day (${gap} days earlier)`}.`;
    const newLabel = sample.newTickets ?? 'unknown';
    const completedLabel = sample.completed === null ? 'unknown' :
        `${sample.completed} (${sample.resolved} resolved, ${sample.closed} closed)`;
    note.textContent = `${sample.value} unresolved — last reading ${observed}. New: ${newLabel}. Resolved/Closed: ${completedLabel}. ${changeLabel}` +
        ' Activity is observed, not a complete event history.' +
        (sample.day === chartDay(Date.now()) ? ' Today is still in progress.' : '');
}

drawChart(); window.addEventListener('resize',drawChart);
</script>
</body>
</html>
