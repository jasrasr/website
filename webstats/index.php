<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
$error = '';
try {
    $cfg = config();
    storage();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$secure && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) fail(400, 'Use HTTPS for the dashboard.');
    require_once dirname(__DIR__) . '/user-management/bootstrap.php';
    $auth = jasr_users_auth();
    $auth->requireProject('webstats', 'admin');
    $viewerIp = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
    $viewerAddress = $viewerIp === false ? false : inet_pton($viewerIp);
    $isViewerIp = static fn (?string $ip): bool => $viewerAddress !== false && $ip !== null && inet_pton($ip) === $viewerAddress;
    $activityMode = $_GET['activity'] ?? (($_GET['hide_me'] ?? null) === '1' ? 'hide' : 'all');
    if ($viewerAddress === false || !in_array($activityMode, ['show', 'hide'], true)) $activityMode = 'all';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['action'] ?? '') !== 'logout' || !$auth->validCsrf($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid dashboard request.');
        }
        $auth->logout();
        header('Location: ' . $auth->loginUrl($_SERVER['SCRIPT_NAME']), true, 303);
        exit;
    }
    $tz = new DateTimeZone($cfg['timezone']);
    $today = new DateTimeImmutable('today', $tz);
    $date = static function (mixed $value, DateTimeImmutable $default) use ($tz): DateTimeImmutable {
        if ($value === null) return $default;
        if (!is_string($value)) throw new InvalidArgumentException('Choose valid dates.');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) throw new InvalidArgumentException('Choose valid dates.');
        return $parsed;
    };
    $start = $date($_GET['start'] ?? null, $today->modify('-6 days'));
    $last = $date($_GET['end'] ?? null, $today);
    if ($last < $start || (int)$start->diff($last)->days > 89 || $last > $today) throw new InvalidArgumentException('Choose up to 90 days ending today or earlier.');
    $end = $last->modify('+1 day');
    $sites = array_values(array_unique(array_values($cfg['origins']))); sort($sites);
    $site = is_string($_GET['site'] ?? null) ? $_GET['site'] : '';
    if ($site !== '' && !in_array($site, $sites, true)) throw new InvalidArgumentException('Choose a configured site.');
    $counts = ['pageview' => 0, 'click' => 0];
    $sessions = $pages = $clicks = $referrers = $bySite = $daily = $recent = [];
    $recentLimit = filter_var($_GET['activity_limit'] ?? 30, FILTER_VALIDATE_INT, ['options' => ['min_range' => 30, 'max_range' => PHP_INT_MAX - 30]]) ?: 30;
    $recentCutoff = time() - 48 * 3600;
    $hasOlderActivity = false;
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) $daily[$day->format('Y-m-d')] = ['pageview' => 0, 'click' => 0];
    foreach (events_between($start->getTimestamp(), $end->getTimestamp(), $site) as $event) {
        $counts[$event['kind']]++;
        $sessions[$event['session']] = true;
        $day = (new DateTimeImmutable('@' . $event['occurred']))->setTimezone($tz)->format('Y-m-d');
        $daily[$day][$event['kind']]++;
        $bySite[$event['site']] = ($bySite[$event['site']] ?? 0) + 1;
        if ($event['kind'] === 'pageview') {
            $pages[$event['page']] = ($pages[$event['page']] ?? 0) + 1;
            $referrer = $event['referrer'] ?: 'Direct / unavailable';
            $referrers[$referrer] = ($referrers[$referrer] ?? 0) + 1;
        } else {
            $key = $event['page'] . ' → ' . ($event['target'] ?: $event['label']);
            $clicks[$key] = ($clicks[$key] ?? 0) + 1;
        }
        if ($activityMode !== 'all' && $isViewerIp($event['ip'] ?? null) !== ($activityMode === 'show')) continue;
        if ($recentLimit > 30 || $event['occurred'] >= $recentCutoff) {
            add_recent_activity($recent, $event, $recentLimit + 1);
        } else {
            $hasOlderActivity = true;
        }
    }
    $hasMoreActivity = $hasOlderActivity || count($recent) > $recentLimit;
    if (count($recent) > $recentLimit) array_shift($recent);
    foreach (['pages', 'clicks', 'referrers', 'bySite'] as $name) arsort($$name);
    $peak = max(1, ...array_column($daily, 'pageview'));
    $columns = ['time' => 'Time', 'page' => 'Site / page', 'event' => 'Event', 'ip' => 'IP address'];
    $sort = $_GET['sort'] ?? 'time';
    if (!is_string($sort) || !isset($columns[$sort])) $sort = 'time';
    $direction = ($_GET['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
    $activityQuery = [
        'site' => $site, 'start' => $start->format('Y-m-d'), 'end' => $last->format('Y-m-d'),
        'sort' => $sort, 'direction' => $direction,
    ];
    if ($recentLimit > 30) $activityQuery['activity_limit'] = $recentLimit;
    if ($activityMode !== 'all') $activityQuery['activity'] = $activityMode;
    $sortValue = static function (array $row) use ($sort): int|string {
        $event = $row['event'];
        return match ($sort) {
            'time' => $event['occurred'],
            'page' => $event['page'],
            'event' => $row['count'] > 1 ? 'click' : activity_label($event),
            'ip' => isset($event['ip']) ? bin2hex(inet_pton($event['ip']) ?: '') : '',
        };
    };
    $recent = array_reverse($recent);
    usort($recent, static function (array $a, array $b) use ($sortValue, $sort, $direction): int {
        $left = $sortValue($a);
        $right = $sortValue($b);
        $order = $sort === 'time' ? $left <=> $right : strcasecmp($left, $right);
        return $direction === 'asc' ? $order : -$order;
    });
} catch (InvalidArgumentException $exception) {
    http_response_code(400); $error = $exception->getMessage(); $invalidFilters = true;
} catch (Throwable $exception) {
    error_log('Webstats dashboard: ' . get_class($exception));
    http_response_code(503); $unavailable = true; $error = 'Webstats is unavailable. Check the installation guide and server configuration.';
}
function ranking(string $title, array $rows, string $unit): void { ?>
<section class="panel"><h2><?= e($title) ?></h2>
<?php if (!$rows): ?><p class="muted">No data in this range.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th scope="col">Source</th><th scope="col"><?= e($unit) ?></th></tr></thead><tbody>
<?php foreach (array_slice($rows, 0, 15, true) as $label => $count): ?><tr><td><?= e($label) ?></td><td><?= number_format($count) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section><?php }
function activity_time(int $timestamp, DateTimeZone $timezone): string {
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('M j, H:i:s T');
}

function activity_label(array $event): string {
    return $event['kind'] === 'click' ? 'click: ' . ($event['target'] ?: $event['label']) : $event['kind'];
}
?>
<!doctype html>
<html lang="en" data-analytics-ignore><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Webstats · JASR</title><link rel="stylesheet" href="assets/css/app.css?v=3"></head>
<body><main><header class="heading"><div><p class="eyebrow">JASR / YOUR SITES, YOUR STATS</p><h1>Webstats<span>.</span></h1><p class="muted">A clear view of where people go and what they click.</p></div>
<?php if (isset($auth) && empty($unavailable)): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($auth->csrf()) ?>"><button class="secondary" name="action" value="logout">Sign out</button></form><?php endif; ?></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($unavailable)): ?><p>Complete the setup in <code>webstats/README.md</code> before collecting traffic.</p>
<?php elseif (!empty($invalidFilters)): ?><a href="./">Reset filters</a>
<?php else: ?>
<form method="get" class="filters panel"><label>Website<select name="site"><option value="">All sites</option><?php foreach ($sites as $item): ?><option value="<?= e($item) ?>" <?= $site === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
<input type="hidden" name="sort" value="<?= e($sort) ?>"><input type="hidden" name="direction" value="<?= e($direction) ?>">
<?php if ($activityMode !== 'all'): ?><input type="hidden" name="activity" value="<?= e($activityMode) ?>"><?php endif; ?>
<label>From<input type="date" name="start" value="<?= e($start->format('Y-m-d')) ?>" required></label>
<label>Through<input type="date" name="end" value="<?= e($last->format('Y-m-d')) ?>" max="<?= e($today->format('Y-m-d')) ?>" required></label><button>Update view</button></form>
<p class="muted">Dates use <?= e($cfg['timezone']) ?>. Today's totals are still growing. Refresh to load new activity.</p>
<div class="metrics"><section class="panel"><p class="eyebrow">PAGE VIEWS</p><strong><?= number_format($counts['pageview']) ?></strong><p class="muted">Pages loaded</p></section><section class="panel"><p class="eyebrow">CLICKS</p><strong><?= number_format($counts['click']) ?></strong><p class="muted">Links, buttons &amp; named events</p></section><section class="panel"><p class="eyebrow">TAB SESSIONS / DAY</p><strong><?= number_format(count($sessions)) ?></strong><p class="muted">Approximate; not unique people</p></section></div>
<?php if (!array_sum($counts)): ?><section class="panel empty"><h2>Ready for your first visitor.</h2><p>Add the tracker to a page, visit it, then refresh this dashboard. No sample traffic is included in these totals.</p></section><?php endif; ?>
<section class="panel"><div class="section-heading"><h2>Daily traffic</h2><span class="muted">Page views / clicks</span></div><div class="daily">
<?php foreach ($daily as $day => $values): ?><div class="day"><time><?= e($day) ?></time><meter min="0" max="<?= $peak ?>" value="<?= $values['pageview'] ?>" aria-label="<?= e($day) ?> page views"><?= $values['pageview'] ?></meter><span><?= number_format($values['pageview']) ?> / <?= number_format($values['click']) ?></span></div><?php endforeach; ?></div></section>
<div class="grid"><?php ranking('Top pages', $pages, 'Views'); ranking('Clicked destinations & actions', $clicks, 'Clicks'); ranking('Referrers', $referrers, 'Views'); ranking('Traffic by site', $bySite, 'Events'); ?></div>
<section class="panel" id="recent-activity"><h2>Recent activity</h2>
<?php if ($viewerAddress !== false): ?>
<form method="get" action="#recent-activity" class="activity-filter" aria-label="Filter by current IP">
<?php foreach (array_diff_key($activityQuery, ['activity' => true]) as $name => $value): ?>
<input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php endforeach; ?>
<?php foreach (['all' => 'All activity', 'show' => 'Show my activity', 'hide' => 'Hide my activity'] as $mode => $label): ?>
<button type="submit" name="activity" value="<?= e($mode) ?>" aria-pressed="<?= $activityMode === $mode ? 'true' : 'false' ?>"<?= $activityMode === $mode ? '' : ' class="secondary"' ?>><?= e($label) ?></button>
<?php endforeach; ?>
</form>

<?php endif; ?>
<div class="table-wrap"><table><thead><tr>
<?php foreach ($columns as $key => $label):
    $active = $key === $sort;
    $query = http_build_query(array_replace($activityQuery, [
        'sort' => $key, 'direction' => $active && $direction === 'asc' ? 'desc' : 'asc',
    ]));
?>
<th scope="col"<?php if ($active): ?> aria-sort="<?= $direction === 'asc' ? 'ascending' : 'descending' ?>"<?php endif; ?>><a href="?<?= e($query) ?>#recent-activity"><?= e($label) ?><?php if ($active): ?> <span aria-hidden="true"><?= $direction === 'asc' ? '↑' : '↓' ?></span><?php endif; ?></a></th>
<?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($recent as $row):
    $event = $row['event'];
    $eventIp = $event['ip'] ?? null;
    $pageUrl = clean_url($event['page']);
?>
<tr><td><?php if ($row['first'] !== $event['occurred']): ?><?= e(activity_time($row['first'], $tz)) ?> – <br><?php endif; ?><?= e(activity_time($event['occurred'], $tz)) ?></td><td>
<?php if ($pageUrl !== ''): ?><a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($event['page']) ?></a><?php else: ?><?= e($event['page']) ?><?php endif; ?>
</td><td>
<?php if ($row['count'] > 1): ?>
<details><summary><?= number_format($row['count']) ?> clicks</summary>
<?php if ($row['count'] > count($row['clicks'])): ?><p class="muted">Showing the latest <?= count($row['clicks']) ?> clicks.</p><?php endif; ?>
<ul><?php foreach (array_reverse($row['clicks']) as $click): ?><li><?= e(activity_time($click['occurred'], $tz)) ?> · <?= e(activity_label($click)) ?></li><?php endforeach; ?></ul></details>
<?php else: ?><?= e(activity_label($event)) ?><?php endif; ?>
</td><td><?= e($eventIp ?? '—') ?><?php if ($isViewerIp($eventIp)): ?> - <strong class="activity-me">ME</strong><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="4">No activity in the last 48 hours for these filters.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($hasMoreActivity): ?><p><a href="?<?= e(http_build_query(array_replace($activityQuery, ['activity_limit' => $recentLimit + 30]))) ?>#recent-activity">Show more</a></p><?php endif; ?></section>
<?php endif; ?><footer>Webstats 1.1.0 · Self-hosted · No form values or URL query strings collected</footer></main></body></html>
