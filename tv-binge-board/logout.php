<?php
/**
 * File: logout.php
 * Project: TV Binge Board
 * Description: Ends the current authenticated session.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
app_logout();
header('Location: login.php');
exit;
