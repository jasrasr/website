<?php
/*
Filename : config.sample.php
Revision : 1.0
Description : Sample private configuration values for PSNotify.
Author : Jason Lamb (with help from Codex CLI)
Created Date : 2026-05-27
Modified Date : 2026-05-27
Changelog :
1.0 initial release
*/

define('PUBLISH_TOKEN', 'replace-with-a-long-random-publish-token');
define('VIEW_KEY', 'replace-with-a-long-random-view-key');
define('DEFAULT_TOPIC', 'jason-longjobs-83hd72');

define('MAX_ITEMS', 500);
define('MAX_MESSAGE_BYTES', 51200);
define('RATE_LIMIT_SECONDS', 5);

define('ENABLE_EMAIL_FORWARD', false);
define('EMAIL_TO', '');
define('EMAIL_FROM', 'psnotify@jasr.me');
define('EMAIL_SUBJECT_PREFIX', '[PSNotify] ');
