<?php declare(strict_types=1);
/**
 * Filename: logout.php
 * Revision : 1.0.0
 * Description : Destroys the user session and redirects to login.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-04-13
 * Changelog :
 * 1.0.0 Initial release
 */

require __DIR__ . '/auth.php';
authStart();
$_SESSION = [];
session_destroy();
header('Location: ./login.php');
exit;
