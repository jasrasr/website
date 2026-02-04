<?php
/*
# filename: log-search.php
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-02-03
# revision: 1.0
# changelog:
# - 1.0: Append-only NDJSON logging for blog searches (server-side)
*/

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['query'])) {
    http_response_code(400);
    echo json_encode(["status" => "ignored"]);
    exit;
}

$query = trim($input['query']);
if (strlen($query) > 100) {
    echo json_encode(["status" => "ignored"]);
    exit;
}

$entry = [
    "timestamp" => gmdate("c"),
    "query"     => $query,
    "results"   => isset($input["results"]) ? (int)$input["results"] : 0,
    "page"      => $_SERVER["REQUEST_URI"] ?? "",
    "ip"        => $_SERVER["REMOTE_ADDR"] ?? "unknown",
    "ua"        => $_SERVER["HTTP_USER_AGENT"] ?? "unknown"
];

$logDir  = __DIR__ . "/logs";
$logFile = $logDir . "/search-log.ndjson";

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

file_put_contents(
    $logFile,
    json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

echo json_encode(["status" => "ok"]);
