<?php
/*
===========================================================
 File: admin/login.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-19
 Modified: 2026-01-19
 Revision: 1.0

 Description:
   Admin login page.
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';
$returnTo = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/box/admin/login.php'), '/') . '/index.php';
header('Location: ' . box_auth()->loginUrl($returnTo), true, 303);
exit;
