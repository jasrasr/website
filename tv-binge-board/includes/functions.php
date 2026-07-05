<?php
/**
 * File: includes/functions.php
 * Project: TV Binge Board
 * Description: Shared UI, library, profile, import/export, stats, local artwork display, TMDB-link display, app script loading, and connection helper functions.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-05
 * Revision: 1.5.17
 */
declare(strict_types=1);


require_once __DIR__ . '/auth.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_href(string $path): string
{
    return app_base_prefix() . ltrim($path, '/');
}

function app_flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function app_render_avatar(array $profile, string $username, int $size = 44): string
{
    $url = trim((string)($profile['avatar_url'] ?? ''));
    $name = trim((string)($profile['display_name'] ?? $username));
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        return '<img class="avatar" width="' . $size . '" height="' . $size . '" src="' . e($url) . '" alt="Avatar for ' . e($name) . '">';
    }
    $initials = strtoupper(substr($name !== '' ? $name : $username, 0, 1));
    return '<span class="avatar avatar-fallback" style="width:' . $size . 'px;height:' . $size . 'px">' . e($initials) . '</span>';
}

function app_page_header(string $title): void
{
    $user = app_current_user();
    $flash = app_flash();
    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_href('assets/css/app.css?v=' . rawurlencode(APP_VERSION))) ?>">
    <link rel="manifest" href="<?= e(app_href('manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_href('assets/icons/apple-touch-icon-180.png')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(app_href('dashboard.php')) ?>"><?= e(APP_NAME) ?></a>
    <span class="version">rev <?= e(APP_VERSION) ?></span>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="alert <?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div>
<?php endif; ?>
<?php if ($user): $profile = app_profile((string)$user['username']); ?>
    <section class="user-strip">
        <span class="user-strip-name"><?= app_render_avatar($profile, (string)$user['username'], 32) ?> Signed in as <strong><?= e($user['display_name'] ?? $user['username']) ?></strong></span>
        <?php if (app_is_admin($user)): ?><span class="pill admin">Admin</span><?php endif; ?>
    </section>
<?php endif; ?>
<?php
}

function app_page_footer(): void
{
    $user = app_current_user();
    ?>
</main>
<?php if ($user): ?>
<nav class="bottom-nav" aria-label="Main navigation">
    <a href="<?= e(app_href('dashboard.php')) ?>">Home</a>
    <?php if (app_can_track($user)): ?>
        <a href="<?= e(app_href('search.php')) ?>">Search</a>
        <a href="<?= e(app_href('watchlist.php')) ?>">List</a>
        <a href="<?= e(app_href('connections.php')) ?>">People</a>
        <a href="<?= e(app_href('import.php')) ?>">Import</a>
    <?php else: ?>
        <a href="<?= e(app_href('admin/users.php')) ?>">Users</a>
        <a href="<?= e(app_href('admin/site-settings.php')) ?>">Site</a>
    <?php endif; ?>
    <a href="<?= e(app_href('settings.php')) ?>">Settings</a>
    <a href="<?= e(app_href('logout.php')) ?>">Logout</a>
</nav>
<?php endif; ?>
<footer class="footer">
    <p><?= e(APP_PUBLIC_SITE_NOTE) ?></p>
    <p>Metadata may use TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.</p>
    <p><a href="<?= e(app_href('changelog.php')) ?>">CHANGELOG</a> · <a href="<?= e(app_href('readme.php')) ?>">README</a> · <a href="<?= e(app_href('tasks.php')) ?>">TASKS</a></p>
</footer>
<script src="<?= e(app_href('assets/js/app.js?v=' . rawurlencode(APP_VERSION))) ?>"></script>
<script src="<?= e(app_href('assets/js/app-controls.js?v=' . rawurlencode(APP_VERSION))) ?>"></script>
</body>
</html><?php
}

function app_library(string $username): array
{
    app_seed_user_files($username);
    $library = app_load_json(app_user_file($username, 'library.json'), [
        '_meta' => app_json_meta('Tracked shows and movies.'),
        'items' => [],
    ]);
    if (!isset($library['items']) || !is_array($library['items'])) {
        $library['items'] = [];
    }
    return $library;
}

function app_save_library(string $username, array $library): void
{
    $library['_meta']['updated_at'] = date(DATE_ATOM);
    $library['_meta']['version'] = APP_VERSION;
    app_save_json(app_user_file($username, 'library.json'), $library);
}

function app_make_media_uid(string $type, ?int $tmdbId, string $title): string
{
    $type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';
    if ($tmdbId !== null && $tmdbId > 0) {
        return $type . '-' . $tmdbId;
    }
    return 'manual-' . substr(sha1($type . '|' . strtolower(trim($title))), 0, 16);
}
