<?php
declare(strict_types=1);

$dataFile = __DIR__ . '/data/ticket-counts.json';
$payload = ['goal' => 0, 'entries' => []];
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

$entries = $payload['entries'];
usort($entries, static fn(array $a, array $b): int => strcmp((string) ($a['capturedAt'] ?? ''), (string) ($b['capturedAt'] ?? '')));
$goal = max(0, (int) ($payload['goal'] ?? 0));
$first = $entries[0] ?? null;
$latest = $entries ? $entries[array_key_last($entries)] : null;
$previous = count($entries) > 1 ? $entries[count($entries) - 2] : null;
$current = (int) ($latest['unresolved'] ?? 0);
$baseline = (int) ($first['unresolved'] ?? 0);
$change = $previous ? $current - (int) ($previous['unresolved'] ?? 0) : null;
$reduced = max(0, $baseline - $current);
$reductionPercent = $baseline > $goal ? max(0, min(100, (($baseline - $current) / ($baseline - $goal)) * 100)) : 100;
$remaining = max(0, $current - $goal);

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
        .badge { padding:8px 12px; border:1px solid var(--line); border-radius:999px; color:var(--green); background:#0d211c; font-weight:700; white-space:nowrap; }
        .grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
        .card,.section { background:linear-gradient(145deg,var(--panel2),var(--panel)); border:1px solid var(--line); border-radius:18px; box-shadow:0 16px 38px rgba(0,0,0,.2); }
        .card { padding:20px; min-height:138px; }
        .label { display:block; margin-bottom:18px; color:var(--muted); font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .value { display:block; font-size:clamp(2rem,5vw,3.5rem); font-weight:850; line-height:1; }
        .value.goal { color:var(--green); }
        .value.good { color:var(--green); }.value.bad { color:var(--red); }
        .sub { display:block; color:var(--muted); margin-top:10px; font-size:.9rem; }
        .progress { height:10px; margin-top:15px; overflow:hidden; border-radius:999px; background:#07111f; }
        .progress span { display:block; height:100%; background:linear-gradient(90deg,var(--blue),var(--green)); border-radius:inherit; }
        .section { margin-top:14px; padding:22px; }
        .section h2 { margin:0 0 16px; font-size:1.15rem; }
        canvas { display:block; width:100%; height:280px; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:680px; }
        th,td { padding:13px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { color:var(--muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; }
        td.number { font-weight:800; }
        .error { padding:14px; border:1px solid #74363a; border-radius:12px; background:#2d171b; color:#ffbec1; }
        footer { color:var(--muted); text-align:center; margin-top:24px; font-size:.85rem; }
        @media (max-width:820px) { .grid { grid-template-columns:repeat(2,1fr); } header { align-items:flex-start; flex-direction:column; } }
        @media (max-width:480px) { main { width:min(100% - 18px,1120px); padding-top:18px; } .grid { grid-template-columns:1fr 1fr; gap:9px; } .card { min-height:120px; padding:15px; } .section { padding:16px; } }
    </style>
</head>
<body>
<main>
    <header>
        <div><h1>Freshservice Ticket Tracker</h1><p>Working the unresolved queue toward zero.</p></div>
        <div class="badge">Goal: <?= $goal ?> unresolved</div>
    </header>

    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

    <section class="grid" aria-label="Ticket summary">
        <article class="card"><span class="label">Current unresolved</span><span class="value"><?= number_format($current) ?></span><span class="sub">As of <?= e(displayDate($latest['capturedAt'] ?? null)) ?></span></article>
        <article class="card"><span class="label">Change since prior</span><span class="value <?= $change !== null && $change <= 0 ? 'good' : 'bad' ?>"><?= $change === null ? '—' : sprintf('%+d', $change) ?></span><span class="sub"><?= $change === null ? 'Needs a second snapshot' : ($change <= 0 ? 'Moving the right direction' : 'Queue increased') ?></span></article>
        <article class="card"><span class="label">Tickets to go</span><span class="value"><?= number_format($remaining) ?></span><span class="sub">Until the queue reaches <?= $goal ?></span></article>
        <article class="card"><span class="label">Reduction from start</span><span class="value goal"><?= number_format($reductionPercent, 0) ?>%</span><div class="progress"><span style="width:<?= e((string) $reductionPercent) ?>%"></span></div><span class="sub"><?= number_format($reduced) ?> fewer than the <?= number_format($baseline) ?> baseline</span></article>
    </section>

    <section class="section"><h2>Unresolved ticket trend</h2><canvas id="trend" role="img" aria-label="Unresolved ticket count over time"></canvas><p id="chart-note" class="muted"></p></section>

    <section class="section"><h2>Snapshot history</h2><div class="table-wrap"><table><thead><tr><th>Captured</th><th>Unresolved</th><th>Change</th><th>Source</th><th>Note</th></tr></thead><tbody>
    <?php foreach (array_reverse($entries) as $index => $entry):
        $originalIndex = count($entries) - 1 - $index;
        $rowChange = $originalIndex > 0 ? (int) $entry['unresolved'] - (int) $entries[$originalIndex - 1]['unresolved'] : null;
    ?><tr><td><?= e(displayDate($entry['capturedAt'] ?? null)) ?></td><td class="number"><?= number_format((int) ($entry['unresolved'] ?? 0)) ?></td><td><?= $rowChange === null ? '—' : sprintf('%+d', $rowChange) ?></td><td><?= e((string) ($entry['source'] ?? 'unknown')) ?></td><td><?= e((string) ($entry['note'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <footer>Aggregate counts only. No ticket subjects, requesters, or credentials are stored here.</footer>
</main>
<script>
const entries = <?= json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const canvas = document.getElementById('trend');
const note = document.getElementById('chart-note');
function drawChart() {
    const dpr = window.devicePixelRatio || 1, rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.round(rect.width * dpr)); canvas.height = Math.round(280 * dpr);
    const c = canvas.getContext('2d'); c.scale(dpr,dpr);
    const w=rect.width,h=280,p={l:48,r:20,t:18,b:42}; c.clearRect(0,0,w,h);
    if (!entries.length) { note.textContent='No snapshots recorded.'; return; }
    const values=entries.map(x=>Number(x.unresolved)), min=Math.min(...values,0), max=Math.max(...values,1), range=Math.max(1,max-min);
    c.strokeStyle='#29405d'; c.fillStyle='#93a4ba'; c.font='12px system-ui'; c.lineWidth=1;
    for(let i=0;i<5;i++){ const y=p.t+(h-p.t-p.b)*(i/4),v=Math.round(max-range*(i/4)); c.beginPath();c.moveTo(p.l,y);c.lineTo(w-p.r,y);c.stroke();c.fillText(String(v),6,y+4); }
    const points=values.map((v,i)=>({x:entries.length===1?(p.l+w-p.r)/2:p.l+(w-p.l-p.r)*(i/(entries.length-1)),y:p.t+(h-p.t-p.b)*((max-v)/range)}));
    if(points.length>1){c.strokeStyle='#4d95ff';c.lineWidth=3;c.beginPath();points.forEach((q,i)=>i?c.lineTo(q.x,q.y):c.moveTo(q.x,q.y));c.stroke();}
    points.forEach((q,i)=>{c.fillStyle='#3ddc84';c.beginPath();c.arc(q.x,q.y,5,0,Math.PI*2);c.fill();c.fillStyle='#f3f7fb';c.fillText(String(values[i]),q.x-8,q.y-12);});
    c.fillStyle='#93a4ba'; const first=new Date(entries[0].capturedAt),last=new Date(entries.at(-1).capturedAt); c.fillText(first.toLocaleDateString(),p.l,h-12); if(entries.length>1)c.fillText(last.toLocaleDateString(),w-p.r-72,h-12);
    note.textContent=entries.length<2?'One snapshot recorded; the trend line begins with the next update.':'';
}
drawChart(); window.addEventListener('resize',drawChart);
</script>
</body>
</html>
