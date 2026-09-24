<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/version.php';

$dataFile = __DIR__ . '/data/ticket-counts.json';
$apiDataFile = __DIR__ . '/storage/org-api-snapshots.json';

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

function dayOfWeekAbbr(DateTimeImmutable $date): string
{
    static $map = [1 => 'M', 2 => 'T', 3 => 'W', 4 => 'TH', 5 => 'F', 6 => 'SA', 7 => 'SU'];
    return $map[(int) $date->format('N')];
}

$entries = readEntries($dataFile, $apiDataFile);
usort($entries, static fn(array $a, array $b): int =>
    (easternDate($a['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN)
    <=> (easternDate($b['capturedAt'] ?? null)?->getTimestamp() ?? PHP_INT_MIN));

// Roll every entry up into one row per Eastern calendar day, summing new-ticket counts by
// requester email domain. Baseline entries carry no real activity and are skipped.
$days = [];
foreach ($entries as $entry) {
    $date = easternDate($entry['capturedAt'] ?? null);
    if ($date === null) continue;
    $key = $date->format('Y-m-d');
    if (!isset($days[$key])) {
        $days[$key] = ['date' => $key, 'byOrg' => [], 'hasMeasurement' => false];
    }
    $baseline = ($entry['initialRun'] ?? false) === true || preg_match('/baseline initialized/i', (string) ($entry['note'] ?? ''));
    $activity = (!$baseline && isset($entry['activity']) && is_array($entry['activity'])) ? $entry['activity'] : null;
    if ($activity === null) continue;
    $days[$key]['hasMeasurement'] = true;
    $orgCounts = isset($activity['newTicketsByOrg']) && is_array($activity['newTicketsByOrg']) ? $activity['newTicketsByOrg'] : [];
    foreach ($orgCounts as $domain => $count) {
        if (!is_numeric($count)) continue;
        $domain = trim((string) $domain) !== '' ? (string) $domain : 'Unknown';
        $days[$key]['byOrg'][$domain] = ($days[$key]['byOrg'][$domain] ?? 0) + (int) $count;
    }
}
ksort($days);
$days = array_values($days);
$dayCount = count($days);

// Rank organizations across the whole visible history so the chart and legend stay stable
// and legible; anything outside the top tier folds into "Other".
$globalTotals = [];
foreach ($days as $day) {
    foreach ($day['byOrg'] as $domain => $count) {
        $globalTotals[$domain] = ($globalTotals[$domain] ?? 0) + $count;
    }
}
arsort($globalTotals, SORT_NUMERIC);
$maxSeries = 6;
$topDomains = array_slice(array_keys($globalTotals), 0, $maxSeries);

foreach ($days as &$day) {
    $series = [];
    $otherTotal = 0;
    foreach ($day['byOrg'] as $domain => $count) {
        if (in_array($domain, $topDomains, true)) $series[$domain] = $count;
        else $otherTotal += $count;
    }
    if ($otherTotal > 0) $series['Other'] = $otherTotal;
    $day['series'] = $series;
    $day['total'] = array_sum($series);
}
unset($day);

$domainOrder = $topDomains;
foreach ($days as $day) {
    if (isset($day['series']['Other'])) { $domainOrder[] = 'Other'; break; }
}

$totalNewTickets = (int) array_sum($globalTotals);
$orgCount = count($globalTotals);
$topDomain = $topDomains[0] ?? null;
$topDomainCount = $topDomain !== null ? (int) $globalTotals[$topDomain] : 0;

$maxTotal = 1;
foreach ($days as $day) $maxTotal = max($maxTotal, (int) $day['total']);

$palette = ['#4d95ff', '#ffad4d', '#3ddc84', '#ff6b72', '#b98bff', '#4dd9ec'];
$colors = [];
foreach ($domainOrder as $index => $domain) {
    $colors[$domain] = $domain === 'Other' ? '#5b6b80' : $palette[$index % count($palette)];
}

// SVG layout.
$width = 960; $height = 380;
$padL = 46; $padR = 16; $padT = 26; $padB = 54;
$plotW = $width - $padL - $padR;
$plotH = $height - $padT - $padB;
$barWidth = $dayCount > 0 ? min(46, max(10, $plotW / $dayCount * 0.6)) : 0;

function xFor(int $index, int $count, float $plotW, float $padL): float
{
    if ($count <= 1) return $padL + $plotW / 2;
    return $padL + $plotW * $index / ($count - 1);
}

// Thin out date labels so they don't overlap on longer histories.
$labelStep = $dayCount > 14 ? (int) ceil($dayCount / 10) : 1;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Ticket Graph — By Organization</title>
    <style>
        :root { --bg:#07111f; --panel:#101d2f; --text:#f3f7fb; --muted:#93a4ba; --line:#29405d; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--bg); color:var(--text); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { width:min(1020px,calc(100% - 24px)); margin:auto; padding:28px 0 40px; }
        h1 { margin:0 0 4px; font-size:clamp(1.4rem,4vw,2rem); letter-spacing:-.03em; }
        p.muted { color:var(--muted); margin:.2rem 0 0; }
        .cards { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:20px 0; }
        .card { padding:16px; border:1px solid var(--line); border-radius:12px; background:var(--panel); }
        .card .label { display:block; color:var(--muted); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
        .card .value { display:block; margin-top:8px; font-size:1.9rem; font-weight:800; }
        .chart-wrap { border:1px solid var(--line); border-radius:14px; background:var(--panel); padding:16px; overflow-x:auto; }
        svg { display:block; min-width:100%; }
        .legend { display:flex; gap:18px; flex-wrap:wrap; margin-top:14px; font-size:.85rem; }
        .legend span { display:inline-flex; align-items:center; gap:6px; }
        .swatch { width:12px; height:12px; border-radius:3px; display:inline-block; }
        .empty { color:var(--muted); padding:20px 0; }
        .table-wrap { margin-top:22px; overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
        table { width:100%; border-collapse:collapse; font-size:.85rem; }
        th, td { padding:9px 12px; text-align:right; white-space:nowrap; border-bottom:1px solid var(--line); }
        th:first-child, td:first-child { text-align:left; }
        thead th { color:var(--muted); font-weight:700; background:#0b1727; }
        a.back { display:inline-block; margin-top:22px; color:var(--muted); font-size:.85rem; }
        footer { color:var(--muted); text-align:center; margin-top:24px; font-size:.85rem; }
        @media (max-width:640px) { .cards { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main>
    <h1>Ticket Graph — By Organization</h1>
    <p class="muted">New tickets per day (Eastern time), split by requester email domain.</p>

    <?php if (!$dayCount || $totalNewTickets === 0): ?>
        <p class="empty">No new-ticket organization data recorded yet.</p>
    <?php else: ?>
        <section class="cards" aria-label="Organization summary">
            <article class="card"><span class="label">New tickets</span><span class="value"><?= number_format($totalNewTickets) ?></span></article>
            <article class="card"><span class="label">Organizations</span><span class="value"><?= number_format($orgCount) ?></span></article>
            <article class="card"><span class="label">Top organization</span><span class="value" style="font-size:1.2rem"><?= $topDomain !== null ? e($topDomain) . ' (' . number_format($topDomainCount) . ')' : '—' ?></span></article>
        </section>

        <div class="chart-wrap">
            <svg viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="New tickets per day, stacked by organization">
                <?php for ($i = 0; $i <= 4; $i++):
                    $y = $padT + $plotH * $i / 4;
                    $label = (int) round($maxTotal * (1 - $i / 4));
                ?>
                    <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>" stroke="#29405d" stroke-width="1" />
                    <text x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" font-size="11" fill="#93a4ba"><?= $label ?></text>
                <?php endfor; ?>

                <?php foreach ($days as $index => $day):
                    if ($index % $labelStep !== 0 && $index !== $dayCount - 1) continue;
                    $x = xFor($index, $dayCount, (float) $plotW, (float) $padL);
                    $date = DateTimeImmutable::createFromFormat('Y-m-d', $day['date']);
                    $label = $date ? $date->format('n/j') : $day['date'];
                    $dowLabel = $date ? dayOfWeekAbbr($date) : '';
                ?>
                    <text x="<?= $x ?>" y="<?= $height - $padB + 18 ?>" text-anchor="middle" font-size="11" fill="#93a4ba"><?= e($label) ?></text>
                    <text x="<?= $x ?>" y="<?= $height - $padB + 32 ?>" text-anchor="middle" font-size="10" fill="#6b7d96"><?= e($dowLabel) ?></text>
                <?php endforeach; ?>

                <?php foreach ($days as $index => $day):
                    if ((int) $day['total'] <= 0) continue;
                    $x = xFor($index, $dayCount, (float) $plotW, (float) $padL);
                    $barX = $x - $barWidth / 2;
                    $cumulative = 0;
                    foreach ($domainOrder as $domain):
                        $count = (int) ($day['series'][$domain] ?? 0);
                        if ($count <= 0) continue;
                        $segHeight = $plotH * $count / $maxTotal;
                        $segY = $height - $padB - $plotH * $cumulative / $maxTotal - $segHeight;
                        $cumulative += $count;
                ?>
                        <rect x="<?= $barX ?>" y="<?= $segY ?>" width="<?= $barWidth ?>" height="<?= $segHeight ?>" fill="<?= e($colors[$domain]) ?>">
                            <title><?= e($domain) ?>: <?= $count ?></title>
                        </rect>
                <?php endforeach; ?>
                    <text x="<?= $x ?>" y="<?= $height - $padB - $plotH * $cumulative / $maxTotal - 6 ?>" text-anchor="middle" font-size="11" font-weight="700" fill="#dce7f5"><?= (int) $day['total'] ?></text>
                <?php endforeach; ?>
            </svg>
        </div>

        <div class="legend">
            <?php foreach ($domainOrder as $domain): ?>
                <span><span class="swatch" style="background:<?= e($colors[$domain]) ?>"></span><?= e($domain) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <?php foreach ($domainOrder as $domain): ?><th><?= e($domain) ?></th><?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($days) as $day):
                    if (!$day['hasMeasurement']) continue;
                    $date = DateTimeImmutable::createFromFormat('Y-m-d', $day['date']);
                    $label = $date ? $date->format('M j, Y') : $day['date'];
                ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <?php foreach ($domainOrder as $domain): ?>
                            <td><?= isset($day['series'][$domain]) ? number_format((int) $day['series'][$domain]) : '—' ?></td>
                        <?php endforeach; ?>
                        <td><?= number_format((int) $day['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">Organized by requester email domain only—no requester names or full addresses are ever stored. Domains beyond the top <?= $maxSeries ?> for the visible history are grouped into Other.</p>
    <?php endif; ?>

    <a class="back" href="index.php">&larr; Back to full dashboard</a>
    <footer>Aggregate counts only. No ticket subjects, requesters, or credentials are stored here.<br>Rev <?= e(APP_REVISION) ?> · Updated <?= e(APP_UPDATED) ?></footer>
</main>
</body>
</html>
