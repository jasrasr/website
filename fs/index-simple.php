<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/version.php';

$dataFile = __DIR__ . '/data/ticket-counts.json';
$apiDataFile = __DIR__ . '/storage/api-snapshots.json';

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

function readEntries(string $dataFile, string $apiDataFile): array
{
    $entries = [];
    if (is_file($dataFile)) {
        $decoded = json_decode((string) file_get_contents($dataFile), true);
        if (is_array($decoded) && isset($decoded['entries']) && is_array($decoded['entries'])) {
            $entries = $decoded['entries'];
        }
    }
    if (is_file($apiDataFile)) {
        $apiDecoded = json_decode((string) file_get_contents($apiDataFile), true);
        if (is_array($apiDecoded) && isset($apiDecoded['entries']) && is_array($apiDecoded['entries'])) {
            $entries = array_merge($entries, $apiDecoded['entries']);
        }
    }
    return $entries;
}

$entries = readEntries($dataFile, $apiDataFile);
usort($entries, static fn(array $a, array $b): int =>
    (easternDate($a['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN)
    <=> (easternDate($b['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN));

// Roll every entry up into one row per Eastern calendar day.
$days = [];
foreach ($entries as $entry) {
    $date = easternDate($entry['capturedAt'] ?? null);
    if ($date === null) continue;
    $key = $date->format('Y-m-d');
    if (!isset($days[$key])) {
        $days[$key] = ['date' => $key, 'unresolved' => null, 'unresolvedTime' => null, 'newTickets' => null, 'completed' => null];
    }
    $timestamp = $date->getTimestamp();
    if ($days[$key]['unresolvedTime'] === null || $timestamp >= $days[$key]['unresolvedTime']) {
        $days[$key]['unresolved'] = (int) ($entry['unresolved'] ?? 0);
        $days[$key]['unresolvedTime'] = $timestamp;
    }
    $baseline = ($entry['initialRun'] ?? false) === true || preg_match('/baseline initialized/i', (string) ($entry['note'] ?? ''));
    $activity = (!$baseline && isset($entry['activity']) && is_array($entry['activity'])) ? $entry['activity'] : null;
    if ($activity !== null) {
        if (isset($activity['newTickets']) && is_numeric($activity['newTickets'])) {
            $days[$key]['newTickets'] = ($days[$key]['newTickets'] ?? 0) + (int) $activity['newTickets'];
        }
        if (isset($activity['resolved']) && is_numeric($activity['resolved']) && isset($activity['closed']) && is_numeric($activity['closed'])) {
            $days[$key]['completed'] = ($days[$key]['completed'] ?? 0) + (int) $activity['resolved'] + (int) $activity['closed'];
        }
    }
}
ksort($days);
$days = array_values($days);
$dayCount = count($days);

$maxValue = 1;
foreach ($days as $day) {
    $maxValue = max($maxValue, (int) ($day['unresolved'] ?? 0), (int) ($day['newTickets'] ?? 0), (int) ($day['completed'] ?? 0));
}

$latest = $dayCount ? $days[$dayCount - 1] : null;

// SVG layout.
$width = 900; $height = 340;
$padL = 46; $padR = 16; $padT = 30; $padB = 40;
$plotW = $width - $padL - $padR;
$plotH = $height - $padT - $padB;

function xFor(int $index, int $count, float $plotW, float $padL): float
{
    if ($count <= 1) return $padL + $plotW / 2;
    return $padL + $plotW * $index / ($count - 1);
}

function yFor(?int $value, int $maxValue, float $plotH, float $padT): ?float
{
    if ($value === null) return null;
    return $padT + $plotH * (1 - $value / $maxValue);
}

function buildSegments(array $days, string $field, int $maxValue, float $plotW, float $plotH, float $padL, float $padT): array
{
    $count = count($days);
    $segments = [];
    $current = [];
    foreach ($days as $index => $day) {
        $value = $day[$field] ?? null;
        $y = yFor($value === null ? null : (int) $value, $maxValue, $plotH, $padT);
        if ($y === null) {
            if ($current) { $segments[] = $current; $current = []; }
            continue;
        }
        $current[] = ['x' => xFor($index, $count, $plotW, $padL), 'y' => $y, 'value' => (int) $value];
    }
    if ($current) $segments[] = $current;
    return $segments;
}

$series = [
    'unresolved' => ['label' => 'Unresolved', 'color' => '#4d95ff', 'field' => 'unresolved'],
    'newTickets' => ['label' => 'New', 'color' => '#ffad4d', 'field' => 'newTickets'],
    'completed' => ['label' => 'Closed/Resolved', 'color' => '#3ddc84', 'field' => 'completed'],
];
foreach ($series as $key => &$config) {
    $config['segments'] = buildSegments($days, $config['field'], $maxValue, (float) $plotW, (float) $plotH, (float) $padL, (float) $padT);
}
unset($config);

// Thin out date labels so they don't overlap on longer histories.
$labelStep = $dayCount > 14 ? (int) ceil($dayCount / 10) : 1;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Ticket Graph — Simple</title>
    <style>
        :root { --bg:#07111f; --panel:#101d2f; --text:#f3f7fb; --muted:#93a4ba; --line:#29405d; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:var(--text); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { width:min(960px,calc(100% - 24px)); margin:auto; padding:28px 0 40px; }
        h1 { margin:0 0 4px; font-size:clamp(1.4rem,4vw,2rem); letter-spacing:-.03em; }
        p.muted { color:var(--muted); margin:.2rem 0 0; }
        .cards { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:20px 0; }
        .card { padding:16px; border:1px solid var(--line); border-radius:12px; background:var(--panel); }
        .card .label { display:block; color:var(--muted); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
        .card .value { display:block; margin-top:8px; font-size:1.9rem; font-weight:800; }
        .card .value.blue { color:#4d95ff; } .card .value.orange { color:#ffad4d; } .card .value.green { color:#3ddc84; }
        .chart-wrap { border:1px solid var(--line); border-radius:14px; background:var(--panel); padding:16px; overflow-x:auto; }
        svg { display:block; min-width:100%; }
        .legend { display:flex; gap:18px; flex-wrap:wrap; margin-top:14px; font-size:.85rem; }
        .legend span { display:inline-flex; align-items:center; gap:6px; }
        .swatch { width:12px; height:12px; border-radius:3px; display:inline-block; }
        .empty { color:var(--muted); padding:20px 0; }
        a.back { display:inline-block; margin-top:22px; color:var(--muted); font-size:.85rem; }
        footer { color:var(--muted); text-align:center; margin-top:24px; font-size:.85rem; }
        @media (max-width:640px) { .cards { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main>
    <h1>Ticket Graph — Simple</h1>
    <p class="muted">Unresolved, new, and closed/resolved tickets by day (Eastern time).</p>

    <?php if (!$dayCount): ?>
        <p class="empty">No ticket data recorded yet.</p>
    <?php else: ?>
        <section class="cards" aria-label="Latest daily totals">
            <article class="card"><span class="label">Unresolved</span><span class="value blue"><?= number_format((int) ($latest['unresolved'] ?? 0)) ?></span></article>
            <article class="card"><span class="label">New</span><span class="value orange"><?= $latest['newTickets'] === null ? '—' : number_format((int) $latest['newTickets']) ?></span></article>
            <article class="card"><span class="label">Closed/Resolved</span><span class="value green"><?= $latest['completed'] === null ? '—' : number_format((int) $latest['completed']) ?></span></article>
        </section>

        <div class="chart-wrap">
            <svg viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="Daily unresolved, new, and closed/resolved ticket counts">
                <?php for ($i = 0; $i <= 4; $i++):
                    $y = $padT + $plotH * $i / 4;
                    $label = (int) round($maxValue * (1 - $i / 4));
                ?>
                    <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>" stroke="#29405d" stroke-width="1" />
                    <text x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" font-size="11" fill="#93a4ba"><?= $label ?></text>
                <?php endfor; ?>

                <?php foreach ($days as $index => $day):
                    if ($index % $labelStep !== 0 && $index !== $dayCount - 1) continue;
                    $x = xFor($index, $dayCount, (float) $plotW, (float) $padL);
                    $date = DateTimeImmutable::createFromFormat('Y-m-d', $day['date']);
                    $label = $date ? $date->format('M j') : $day['date'];
                ?>
                    <text x="<?= $x ?>" y="<?= $height - $padB + 18 ?>" text-anchor="middle" font-size="11" fill="#93a4ba"><?= e($label) ?></text>
                <?php endforeach; ?>

                <?php foreach ($series as $config): ?>
                    <?php foreach ($config['segments'] as $segment): ?>
                        <polyline
                            fill="none"
                            stroke="<?= e($config['color']) ?>"
                            stroke-width="3"
                            stroke-linejoin="round"
                            stroke-linecap="round"
                            points="<?= e(implode(' ', array_map(static fn(array $point): string => $point['x'] . ',' . $point['y'], $segment))) ?>"
                        />
                        <?php foreach ($segment as $point): ?>
                            <circle cx="<?= $point['x'] ?>" cy="<?= $point['y'] ?>" r="4" fill="<?= e($config['color']) ?>" />
                            <text x="<?= $point['x'] ?>" y="<?= $point['y'] - 10 ?>" text-anchor="middle" font-size="11" font-weight="700" fill="<?= e($config['color']) ?>"><?= $point['value'] ?></text>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </svg>
        </div>

        <div class="legend">
            <?php foreach ($series as $config): ?>
                <span><span class="swatch" style="background:<?= e($config['color']) ?>"></span><?= e($config['label']) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="back" href="index.php">&larr; Back to full dashboard</a>
    <footer>Aggregate counts only. No ticket subjects, requesters, or credentials are stored here.<br>Rev <?= e(APP_REVISION) ?> · Updated <?= e(APP_UPDATED) ?></footer>
</main>
</body>
</html>
