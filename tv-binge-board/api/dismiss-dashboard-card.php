<?php
/**
 * File: api/dismiss-dashboard-card.php
 * Project: TV Binge Board
 * Description: Persists per-user dismissals for optional dashboard cards.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
$user = app_require_login();
app_verify_csrf();

$card = (string)($_POST['card'] ?? '');
$profile = app_profile((string)$user['username']);

if ($card === 'install_prompt') {
    $profile['hide_install_prompt'] = true;
    $profile['install_prompt_dismissed_at'] = date(DATE_ATOM);
    app_save_profile((string)$user['username'], $profile);
    app_flash('Home Screen reminder dismissed. You can bring it back from Settings.', 'success');
}

header('Location: ../dashboard.php');
exit;
