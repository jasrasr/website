<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Jasr\Framework\PasswordSession;
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
$error = '';
try {
    $cfg = config();
    storage();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$secure && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) fail(400, 'Use HTTPS for the dashboard.');
    $auth = new PasswordSession('jasr_webstats', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/', $secure);
    if ($auth->loggedIn() && !hash_equals(credential_version($cfg), (string)($_SESSION['webstats_credential_version'] ?? ''))) $auth->logout();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); $error = 'Your session changed. Please try again.'; }
        elseif (($_POST['action'] ?? '') === 'logout') { $auth->logout(); header('Location: ./', true, 303); exit; }
        elseif (($_POST['action'] ?? '') === 'change-password') {
            if (!$auth->loggedIn()) { http_response_code(401); $error = 'Sign in before changing your password.'; }
            else {
                try {
                    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
                    $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
                    change_initial_password($cfg, $newPassword, $confirmation);
                    $auth->logout();
                    header('Location: ./?password_changed=1', true, 303); exit;
                } catch (InvalidArgumentException $exception) { http_response_code(400); $error = $exception->getMessage(); }
            }
        }
        else {
            $username = is_string($_POST['username'] ?? null) ? substr($_POST['username'], 0, 200) : '';
            $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 4096) : '';
            if ($auth->login($username, $password, $cfg['username'], $cfg['password_hash'],
                static fn(): bool => allowance('login:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 900) && allowance('login:global', 100, 900))) {
                $_SESSION['webstats_credential_version'] = credential_version($cfg);
                header('Location: ./', true, 303); exit;
            }
            http_response_code(401); $error = 'Sign-in failed or temporarily rate limited.';
        }
    }
    if ($auth->loggedIn() && !$cfg['must_change_password']) {
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
            $recent[] = $event;
            if (count($recent) > 30) array_shift($recent);
        }
        foreach (['pages', 'clicks', 'referrers', 'bySite'] as $name) arsort($$name);
        $peak = max(1, ...array_column($daily, 'pageview'));
    }
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
?>
<!doctype html>
<html lang="en" data-analytics-ignore><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Webstats · JASR</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><main><header class="heading"><div><p class="eyebrow">JASR / YOUR SITES, YOUR STATS</p><h1>Webstats<span>.</span></h1><p class="muted">A clear view of where people go and what they click.</p></div>
<?php if (isset($auth) && $auth->loggedIn()): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($auth->csrf()) ?>"><button class="secondary" name="action" value="logout">Sign out</button></form><?php endif; ?></header>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($unavailable)): ?><p>Complete the setup in <code>webstats/README.md</code> before collecting traffic.</p>
<?php elseif (!$auth->loggedIn()): ?>
<section class="panel login"><h2>Sign in to your dashboard</h2><p class="muted"><?= isset($_GET['password_changed']) ? 'Password changed. Sign in with your new password.' : 'Your traffic reports stay private.' ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= e($auth->csrf()) ?>">
<label>Username<input name="username" autocomplete="username" maxlength="200" required></label>
<label>Password<input type="password" name="password" autocomplete="current-password" maxlength="4096" required></label>
<button>Sign in</button></form></section>
<?php elseif ($cfg['must_change_password']): ?>
<section class="panel login"><h2>Change your temporary password</h2><p>Set your own password before viewing reports. Use at least 12 characters.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= e($auth->csrf()) ?>"><input type="hidden" name="action" value="change-password">
<label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button>Save password</button></form></section>
<?php elseif (!empty($invalidFilters)): ?><a href="./">Reset filters</a>
<?php else: ?>
<form method="get" class="filters panel"><label>Website<select name="site"><option value="">All sites</option><?php foreach ($sites as $item): ?><option value="<?= e($item) ?>" <?= $site === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
<label>From<input type="date" name="start" value="<?= e($start->format('Y-m-d')) ?>" required></label>
<label>Through<input type="date" name="end" value="<?= e($last->format('Y-m-d')) ?>" max="<?= e($today->format('Y-m-d')) ?>" required></label><button>Update view</button></form>
<p class="muted">Dates use <?= e($cfg['timezone']) ?>. Today's totals are still growing. Refresh to load new activity.</p>
<div class="metrics"><section class="panel"><p class="eyebrow">PAGE VIEWS</p><strong><?= number_format($counts['pageview']) ?></strong><p class="muted">Pages loaded</p></section><section class="panel"><p class="eyebrow">CLICKS</p><strong><?= number_format($counts['click']) ?></strong><p class="muted">Links, buttons &amp; named events</p></section><section class="panel"><p class="eyebrow">TAB SESSIONS / DAY</p><strong><?= number_format(count($sessions)) ?></strong><p class="muted">Approximate; not unique people</p></section></div>
<?php if (!array_sum($counts)): ?><section class="panel empty"><h2>Ready for your first visitor.</h2><p>Add the tracker to a page, visit it, then refresh this dashboard. No sample traffic is included in these totals.</p></section><?php endif; ?>
<section class="panel"><div class="section-heading"><h2>Daily traffic</h2><span class="muted">Page views / clicks</span></div><div class="daily">
<?php foreach ($daily as $day => $values): ?><div class="day"><time><?= e($day) ?></time><meter min="0" max="<?= $peak ?>" value="<?= $values['pageview'] ?>" aria-label="<?= e($day) ?> page views"><?= $values['pageview'] ?></meter><span><?= number_format($values['pageview']) ?> / <?= number_format($values['click']) ?></span></div><?php endforeach; ?></div></section>
<div class="grid"><?php ranking('Top pages', $pages, 'Views'); ranking('Clicked destinations & actions', $clicks, 'Clicks'); ranking('Referrers', $referrers, 'Views'); ranking('Traffic by site', $bySite, 'Events'); ?></div>
<section class="panel"><h2>Recent activity</h2><div class="table-wrap"><table><thead><tr><th>Time</th><th>Site / page</th><th>Event</th></tr></thead><tbody>
<?php foreach (array_reverse($recent) as $event): ?><tr><td><?= e((new DateTimeImmutable('@' . $event['occurred']))->setTimezone($tz)->format('M j, H:i:s T')) ?></td><td><?= e($event['page']) ?></td><td><?= e($event['kind'] . ($event['kind'] === 'click' ? ': ' . ($event['target'] ?: $event['label']) : '')) ?></td></tr><?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="3">No activity yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?><footer>Webstats 1.1.0 · Self-hosted · No form values or URL query strings collected</footer></main></body></html>
