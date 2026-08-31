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
usort($entries, static fn(array $a, array $b): int => strcmp((string) ($a['capturedAt'] ?? ''), (string) ($b['capturedAt'] ?? '')));
$goal = max(0, (int) ($payload['goal'] ?? 0));
$latest = $entries ? $entries[array_key_last($entries)] : null;
$previous = count($entries) > 1 ? $entries[count($entries) - 2] : null;
$current = (int) ($latest['unresolved'] ?? 0);
$change = $previous ? $current - (int) ($previous['unresolved'] ?? 0) : null;
$analytics = $latest && isset($latest['analytics']) && is_array($latest['analytics']) ? $latest['analytics'] : [];
$latestDay = $latest && !empty($latest['capturedAt']) ? (new DateTimeImmutable((string) $latest['capturedAt']))->format('Y-m-d') : null;
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
    if ((new DateTimeImmutable((string) $entry['capturedAt']))->format('Y-m-d') !== $latestDay) continue;
    foreach ($todayActivity as $key => $value) {
        $todayActivity[$key] += (int) ($entry['activity'][$key] ?? 0);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function displayDate(?string $value): string
{
    if (!$value) return '—';
    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A T');
    } catch (Throwable) {
        return $value;
    }
}

function displayDateWithoutTimezone(?string $value): string
{
    if (!$value) return '—';
    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
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
        <div><h1>Freshservice Ticket Tracker</h1><p>Working the unresolved queue toward zero.</p></div>
        <div class="header-actions">
            <div class="badge">Goal: <?= $goal ?> unresolved</div>
            <button type="button" class="pull-button" id="pull-tickets">Pull tickets now</button>
            <span class="pull-status" id="pull-status" role="status" aria-live="polite"></span>
        </div>
    </header>

    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

    <section class="grid" aria-label="Ticket summary">
        <article class="card"><span class="label">Current unresolved</span><span class="value"><?= number_format($current) ?></span><span class="sub">As of <?= e(displayDateWithoutTimezone($latest['capturedAt'] ?? null)) ?></span></article>
        <article class="card"><span class="label">Change since prior</span><span class="value <?= $change !== null && $change <= 0 ? 'good' : 'bad' ?>"><?= $change === null ? '—' : sprintf('%+d', $change) ?></span><span class="sub"><?= $change === null ? 'Needs a second snapshot' : ($change <= 0 ? 'Moving the right direction' : 'Queue increased') ?></span></article>
    </section>

    <section class="section">
        <h2>Activity on <?= $latestDay ? e((new DateTimeImmutable($latestDay))->format('M j, Y')) : 'latest day' ?></h2>
        <div class="activity-grid">
            <div class="activity-item"><span class="label">Entered unresolved</span><strong><?= number_format($todayActivity['enteredUnresolved']) ?></strong><span class="sub">New, assigned, or reopened</span></div>
            <div class="activity-item"><span class="label">Exited unresolved</span><strong><?= number_format($todayActivity['exitedUnresolved']) ?></strong><span class="sub">Resolved, closed, or reassigned</span></div>
            <div class="activity-item"><span class="label">New tickets</span><strong><?= number_format($todayActivity['newTickets']) ?></strong><span class="sub"><?= number_format($todayActivity['assignedIn']) ?> assigned in · <?= number_format($todayActivity['reopened']) ?> reopened</span></div>
            <div class="activity-item"><span class="label">Completed</span><strong><?= number_format($todayActivity['resolved'] + $todayActivity['closed']) ?></strong><span class="sub"><?= number_format($todayActivity['resolved']) ?> resolved · <?= number_format($todayActivity['closed']) ?> closed</span></div>
        </div>
        <?php if (($latest['source'] ?? '') !== 'freshservice-api'): ?><p class="muted">Detailed activity begins after the API collector is configured and has completed at least two runs.</p><?php endif; ?>
    </section>

    <section class="section"><h2>Unresolved ticket trend</h2><canvas id="trend" role="img" aria-label="Unresolved ticket count over time"></canvas><p id="chart-note" class="muted"></p></section>

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
                'requesterDistribution' => 'Anonymous requester distribution',
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

function drawChart() {
    const dpr = window.devicePixelRatio || 1, rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.round(rect.width * dpr)); canvas.height = Math.round(280 * dpr);
    const c = canvas.getContext('2d'); c.scale(dpr,dpr);
    const w=rect.width,h=280,p={l:48,r:16,t:30,b:52}; c.clearRect(0,0,w,h);

    const samples=entries
        .map(entry=>({entry,value:Number(entry.unresolved),time:new Date(entry.capturedAt).getTime()}))
        .filter(sample=>Number.isFinite(sample.value)&&Number.isFinite(sample.time))
        .sort((a,b)=>a.time-b.time);
    if (!samples.length) { note.textContent='No snapshots recorded.'; canvas._trendHits=[]; return; }

    const values=samples.map(sample=>sample.value), min=Math.min(...values,0), max=Math.max(...values,1), range=Math.max(1,max-min);
    const firstTime=samples[0].time,lastTime=samples.at(-1).time,timeRange=Math.max(1,lastTime-firstTime);
    const plotWidth=w-p.l-p.r,plotHeight=h-p.t-p.b;
    const xFor=time=>samples.length===1?(p.l+w-p.r)/2:p.l+plotWidth*((time-firstTime)/timeRange);
    const yFor=value=>p.t+plotHeight*((max-value)/range);

    c.strokeStyle='#29405d'; c.fillStyle='#93a4ba'; c.font='12px system-ui'; c.lineWidth=1;
    for(let i=0;i<5;i++){
        const y=p.t+plotHeight*(i/4),v=Math.round(max-range*(i/4));
        c.beginPath();c.moveTo(p.l,y);c.lineTo(w-p.r,y);c.stroke();c.fillText(String(v),6,y+4);
    }

    const tickCount=Math.min(samples.length===1?1:(w<480?5:w<760?7:9),9);
    const dateOptions=timeRange<=36*60*60*1000
        ? {month:'short',day:'numeric',hour:'numeric'}
        : {month:'short',day:'numeric'};
    c.font=(w<480?'10px':'11px')+' system-ui';
    for(let i=0;i<tickCount;i++){
        const ratio=tickCount===1?0:i/(tickCount-1),tickTime=firstTime+timeRange*ratio,x=xFor(tickTime);
        const label=new Date(tickTime).toLocaleString(undefined,dateOptions),labelWidth=c.measureText(label).width;
        c.strokeStyle='#29405d';c.beginPath();c.moveTo(x,h-p.b);c.lineTo(x,h-p.b+6);c.stroke();
        const labelX=Math.max(p.l,Math.min(w-p.r-labelWidth,x-labelWidth/2));
        c.fillStyle='#93a4ba';c.fillText(label,labelX,h-15);
    }

    if(samples.length>1){
        c.strokeStyle='#4d95ff';c.lineWidth=3;c.lineJoin='round';c.beginPath();
        c.moveTo(xFor(samples[0].time),yFor(samples[0].value));
        for(let i=1;i<samples.length;i++){
            const x=xFor(samples[i].time),previousY=yFor(samples[i-1].value),currentY=yFor(samples[i].value);
            c.lineTo(x,previousY);
            if(currentY!==previousY)c.lineTo(x,currentY);
        }
        c.stroke();
    }

    const runs=[];
    for(const sample of samples){
        const previous=runs.at(-1);
        if(previous&&previous.value===sample.value){
            previous.endTime=sample.time;previous.count++;
        }else{
            runs.push({value:sample.value,startTime:sample.time,endTime:sample.time,count:1});
        }
    }

    c.font='12px system-ui';c.textAlign='center';c.textBaseline='alphabetic';
    const placed=[];
    canvas._trendHits=runs.map(run=>{
        const startX=xFor(run.startTime),endX=xFor(run.endTime),labelX=(startX+endX)/2,y=yFor(run.value);
        const label=run.count>1?`${run.value} × ${run.count}`:String(run.value);
        const labelWidth=c.measureText(label).width;
        let labelY=y-12;
        if(placed.some(item=>Math.abs(item.y-labelY)<15&&labelX-labelWidth/2<item.right+7&&labelX+labelWidth/2>item.left-7)) labelY=y+21;
        if(labelY>h-p.b-3)labelY=y-12;
        placed.push({left:labelX-labelWidth/2,right:labelX+labelWidth/2,y:labelY});

        c.fillStyle='#3ddc84';c.beginPath();c.arc(startX,y,5,0,Math.PI*2);c.fill();
        c.fillStyle='#f3f7fb';c.fillText(label,labelX,labelY);
        return {startX,endX,y,run};
    });
    c.textAlign='start';

    note.textContent=samples.length<2
        ? 'One snapshot recorded; the trend begins with the next update.'
        : 'Time spacing reflects elapsed time. Consecutive duplicate pulls are grouped as ×N; tap a plateau for details.';
}

canvas.addEventListener('click',event=>{
    const rect=canvas.getBoundingClientRect(),x=event.clientX-rect.left,y=event.clientY-rect.top;
    const hits=canvas._trendHits||[];
    let best=null,bestDistance=Infinity;
    for(const hit of hits){
        const left=Math.min(hit.startX,hit.endX)-12,right=Math.max(hit.startX,hit.endX)+12;
        const dx=x<left?left-x:x>right?x-right:0,dy=Math.abs(y-hit.y),distance=Math.hypot(dx,dy);
        if(distance<bestDistance){best=hit;bestDistance=distance;}
    }
    if(!best||bestDistance>28)return;
    const {run}=best,start=new Date(run.startTime),end=new Date(run.endTime);
    const rangeLabel=run.count>1
        ? `${start.toLocaleString()} through ${end.toLocaleString()}`
        : start.toLocaleString();
    note.textContent=`${run.value} unresolved across ${run.count} ${run.count===1?'check':'checks'} — ${rangeLabel}.`;
});

drawChart(); window.addEventListener('resize',drawChart);
</script>
</body>
</html>
