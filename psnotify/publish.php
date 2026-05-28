<?php
/*
Filename : publish.php
Revision : 1.2
Description : Accepts ntfy-style POST requests and stores notifications locally for PSNotify.
Author : Jason Lamb (with help from ChatGPT)
Created Date : 2026-03-20
Modified Date : 2026-05-27
Changelog :
1.0 initial release
1.1 standardized header and changelog format
1.2 added message size validation and per-topic publish rate limiting
*/

require_once __DIR__ . '/common.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT'], true)) {
    psnotify_json_response([
        'ok' => false,
        'error' => 'Use POST or PUT to publish a notification.'
    ], 405);
}

psnotify_require_publish_auth();

$topic = psnotify_clean_topic((string) ($_GET['topic'] ?? $_POST['topic'] ?? DEFAULT_TOPIC));
$rawBody = trim((string) file_get_contents('php://input'));
$message = $rawBody !== '' ? $rawBody : trim((string) ($_POST['message'] ?? ''));
$title = trim((string) (psnotify_header_value('Title', $_POST['title'] ?? '')));
$priority = psnotify_normalize_priority((string) (psnotify_header_value('Priority', $_POST['priority'] ?? 'default')));
$tagString = (string) (psnotify_header_value('Tags', $_POST['tags'] ?? ''));
$markdown = filter_var((string) (psnotify_header_value('Markdown', $_POST['markdown'] ?? 'false')), FILTER_VALIDATE_BOOLEAN);

if ($message === '') {
    psnotify_json_response([
        'ok' => false,
        'error' => 'Message body is empty.'
    ], 400);
}

psnotify_enforce_message_size($message);
psnotify_enforce_publish_rate_limit($topic);

$item = [
    'id' => date('YmdHis') . '-' . bin2hex(random_bytes(4)),
    'topic' => $topic,
    'title' => $title,
    'message' => $message,
    'priority' => $priority,
    'tags' => psnotify_split_tags($tagString),
    'markdown' => $markdown,
    'created_unix' => time(),
    'created_local' => date('Y-m-d h:i:s A T'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

psnotify_append_notification($item);
psnotify_send_optional_email($item);

psnotify_json_response([
    'ok' => true,
    'message' => 'Notification accepted.',
    'id' => $item['id'],
    'topic' => $item['topic']
]);
