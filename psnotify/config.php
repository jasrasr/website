<?php
/*
Filename : config.php
Revision : 1.3
Description : Public-safe defaults and local override loader for PSNotify.
Author : Jason Lamb (with help from ChatGPT)
Created Date : 2026-03-20
Modified Date : 2026-05-27
Changelog :
1.0 initial release
1.1 standardized header and changelog format
1.2 updated application revision to match the latest viewer layout release
1.3 moved secrets into ignored config.local.php and added safe runtime defaults
*/

function psnotify_define_if_missing(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

psnotify_define_if_missing('APP_NAME', 'PSNotify');
psnotify_define_if_missing('APP_REVISION', '1.3');
psnotify_define_if_missing('APP_TIMEZONE', 'America/New_York');

psnotify_define_if_missing('DATA_FILE', __DIR__ . '/data/notifications.json');
psnotify_define_if_missing('RATE_LIMIT_FILE', __DIR__ . '/data/rate-limit.json');
psnotify_define_if_missing('MAX_ITEMS', 500);
psnotify_define_if_missing('MAX_MESSAGE_BYTES', 51200);
psnotify_define_if_missing('RATE_LIMIT_SECONDS', 5);

psnotify_define_if_missing('REQUIRE_PUBLISH_TOKEN', true);
psnotify_define_if_missing('PUBLISH_TOKEN', '');

psnotify_define_if_missing('REQUIRE_VIEW_KEY', true);
psnotify_define_if_missing('VIEW_KEY', '');

psnotify_define_if_missing('DEFAULT_TOPIC', 'psnotify');

psnotify_define_if_missing('ENABLE_EMAIL_FORWARD', false);
psnotify_define_if_missing('EMAIL_TO', '');
psnotify_define_if_missing('EMAIL_FROM', 'psnotify@jasr.me');
psnotify_define_if_missing('EMAIL_SUBJECT_PREFIX', '[PSNotify] ');

$publishConfigured = !REQUIRE_PUBLISH_TOKEN || PUBLISH_TOKEN !== '';
$viewConfigured = !REQUIRE_VIEW_KEY || VIEW_KEY !== '';
psnotify_define_if_missing('PSNOTIFY_CONFIGURED', $publishConfigured && $viewConfigured);
