<?php
/**
 * File: connections.php
 * Project: TV Binge Board
 * Description: User discovery, mutual connection management, shared list access, and friend activity feed.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-05
 * Revision: 1.5.16
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
$connections = app_connections((string)$user['username']);
$accounts = app_get_accounts()['users'];

function app_connection_account_lookup(array $accounts): array
{
    $lookup = [];
    foreach ($accounts as $account) {
        if (!is_array($account)) { continue; }
        $username = app_sanitize_username((string)($account['username'] ?? ''));
        if ($username !== '') { $lookup[$username] = $account; }
    }
    return $lookup;
}

function app_connection_display_name(array $accountsByUsername, string $username): string
{
    $profile = app_profile($username);
    $account = $accountsByUsername[$username] ?? [];
    $display = trim((string)($profile['display_name'] ?? $account['display_name'] ?? $username));
    return $display !== '' ? $display : $username;
}

function app_connection_media_title(string $username, string $uid): string
{
    static $libraries = [];
    $username = app_sanitize_username($username);
    if ($username === '' || $uid === '') { return 'a title'; }
    if (!isset($libraries[$username])) { $libraries[$username] = app_library($username); }
    $index = app_find_media_index($libraries[$username], $uid);
    if ($index === null) { return 'a title'; }
    $title = trim((string)($libraries[$username]['items'][$index]['title'] ?? ''));
    return $title !== '' ? $title : 'a title';
}

function app_connection_event_text(array $event): string
{
    $action = (string)($event['action'] ?? '');
    $target = app_sanitize_username((string)($event['target'] ?? ''));
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $uid = (string)($details['uid'] ?? '');
    $title = app_connection_media_title($target, $uid);
    $season = (int)($details['season'] ?? 0);
    $episode = (int)($details['episode'] ?? 0);
    $episodeCode = $season > 0 && $episode > 0 ? 'S' . $season . 'E' . $episode : '';

    return match ($action) {
        'media-added-or-updated' => 'added or updated ' . $title,
        'media-status-updated' => 'updated ' . $title . ' to ' . (app_statuses()[(string)($details['status'] ?? '')] ?? 'a new status'),
        'episode-watched-through' => 'watched through ' . ($episodeCode !== '' ? $episodeCode . ' of ' : '') . $title,
        'episode-watched' => 'marked ' . ($episodeCode !== '' ? $episodeCode . ' on ' : '') . $title . ' watched',
        'episode-unwatched' => 'unmarked ' . ($episodeCode !== '' ? $episodeCode . ' on ' : '') . $title,
        'season-watched' => 'marked Season ' . max(1, $season) . ' of ' . $title . ' watched',
        'season-watched-through' => 'marked seasons through Season ' . max(1, $season) . ' of ' . $title . ' watched',
        'season-unwatched' => 'unmarked Season ' . max(1, $season) . ' of ' . $title,
        'media-deleted' => 'removed ' . $title,
        default => '',
    };
}

function app_connection_activity_feed(array $viewer, array $accounts, array $connections, int $limit = 20): array
{
    $viewerName = app_sanitize_username((string)($viewer['username'] ?? ''));
    $accountsByUsername = app_connection_account_lookup($accounts);
    $visibleTargets = [];
    foreach ($accounts as $account) {
        if (!is_array($account)) { continue; }
        $username = app_sanitize_username((string)($account['username'] ?? ''));
        if ($username === '' || $username === $viewerName || !empty($account['disabled']) || ($account['role'] ?? '') === 'admin') { continue; }
        if (in_array($username, $connections['connections'] ?? [], true) || app_can_view_library($viewer, $username)) {
            $visibleTargets[$username] = true;
        }
    }

    $feed = [];
    foreach (app_activity_events(250) as $event) {
        if (!is_array($event)) { continue; }
        $target = app_sanitize_username((string)($event['target'] ?? ''));
        if ($target === '' || empty($visibleTargets[$target])) { continue; }
        $text = app_connection_event_text($event);
        if ($text === '') { continue; }
        $time = strtotime((string)($event['at'] ?? '')) ?: 0;
        $feed[] = [
            'username' => $target,
            'display_name' => app_connection_display_name($accountsByUsername, $target),
            'text' => $text,
            'at' => $time > 0 ? date('M j, g:i A', $time) : '',
        ];
        if (count($feed) >= $limit) { break; }
    }
    return $feed;
}

$activityFeed = app_connection_activity_feed($user, $accounts, $connections, 20);
app_page_header('Connections');
if (!app_can_track($user)):
?>
<section class="card"><h1>Connections</h1><p>Admin accounts do not create watch-list connections.</p></section>
<?php else: ?>
<section class="card"><h1>Connections</h1><p>Connect with other users to share lists without making everything fully public.</p></section>
<?php if ($connections['incoming_requests']): ?>
<section class="card"><h2>Incoming requests</h2><?php foreach ($connections['incoming_requests'] as $from): ?><form method="post" action="api/respond-connection.php" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="from_user" value="<?= e($from) ?>"><span><?= e($from) ?></span><button name="response" value="accept">Accept</button><button name="response" value="decline" class="secondary">Decline</button></form><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($activityFeed): ?>
<section class="card"><h2>Friend activity</h2><p class="muted">Recent visible activity from connected users and users who share their list publicly.</p><div class="user-list">
<?php foreach ($activityFeed as $event): ?>
<article class="user-card"><div><strong><?= e($event['display_name']) ?></strong><p><?= e($event['text']) ?></p><p class="muted">@<?= e($event['username']) ?><?= $event['at'] !== '' ? ' · ' . e($event['at']) : '' ?></p></div><div class="actions small"><a class="button secondary" href="public.php?u=<?= e($event['username']) ?>">View list</a></div></article>
<?php endforeach; ?>
</div></section>
<?php endif; ?>
<section class="card"><h2>People</h2><div class="user-list">
<?php foreach ($accounts as $account):
    $username = (string)($account['username'] ?? '');
    if ($username === $user['username'] || ($account['role'] ?? '') === 'admin' || !empty($account['disabled'])) continue;
    $profile = app_profile($username);
    $connected = in_array($username, $connections['connections'], true);
    $pending = in_array($username, $connections['outgoing_requests'], true);
?>
<article class="user-card"><div class="profile-heading small-profile"><?= app_render_avatar($profile, $username, 44) ?><div><strong><?= e((string)($profile['display_name'] ?? $account['display_name'] ?? $username)) ?></strong><p class="muted">@<?= e($username) ?> <?= !empty($profile['public_share_enabled']) ? '· public list' : '' ?></p></div></div><div class="actions small"><?php if (app_can_view_library($user, $username)): ?><a class="button secondary" href="public.php?u=<?= e($username) ?>">View</a><?php endif; ?><?php if ($connected): ?><span class="pill success">Connected</span><?php elseif ($pending): ?><span class="pill">Pending</span><?php else: ?><form method="post" action="api/request-connection.php"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="target_user" value="<?= e($username) ?>"><button type="submit">Connect</button></form><?php endif; ?></div></article>
<?php endforeach; ?>
</div></section>
<?php endif; app_page_footer(); ?>
