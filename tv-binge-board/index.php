<?php
/**
 * File: index.php
 * Project: TV Binge Board
 * Description: Entry point that routes authenticated users to the dashboard and guests to login.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Location: ' . (app_current_user() ? 'dashboard.php' : 'login.php'));
exit;
