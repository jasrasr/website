<?php declare(strict_types=1);

$redirectTarget = 'https://jasr.me/github/scoreboard';
$logFile = __DIR__ . '/redirect-hits.jsonl';

$hit = [
    'timestamp' => gmdate('c'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'query' => $_SERVER['QUERY_STRING'] ?? '',
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
    'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
];

$encodedHit = json_encode($hit, JSON_UNESCAPED_SLASHES);
if ($encodedHit !== false) {
    @file_put_contents($logFile, $encodedHit . PHP_EOL, FILE_APPEND | LOCK_EX);
}

header('Location: ' . $redirectTarget, true, 301);
exit;
