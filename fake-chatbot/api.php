<?php
/*
File Name: api.php
Revision: 2.0.1
Description: Public fake-chat endpoint with server-side question history and browser-only history controls.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-22
Changelog: 2.0.1 - Clear the visitor view without removing admin history. 2.0.0 - Use ranked PHP rules, private JsonStore persistence, and per-browser rate limits.
*/
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

try {
    $session = fc_browser_hash();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') !== 'history') fc_json(405, ['error' => 'Unsupported request.']);
        $uid = fc_linked_user($session);
        $entries = fc_recent(\Jasr\Framework\JsonStore::read(fc_store('history.json'))['records'], $session, $uid);
        fc_json(200, ['history' => $entries, 'linked' => $uid !== null]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fc_json(405, ['error' => 'Only GET and POST are supported.']);
    if (!fc_same_origin() || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        fc_json(403, ['error' => 'Use the chatbot page to submit requests.']);
    }
    $action = $_POST['action'] ?? 'chat';
    if (!is_string($action)) fc_json(400, ['error' => 'Invalid action.']);
    if ($action === 'clear_guest_history') {
        \Jasr\Framework\JsonStore::update(fc_store('history.json'), static fn(array $r): array =>
            fc_hide_guest_history($r, $session)
        );
        fc_json(200, ['ok' => true]);
    }
    if ($action !== 'chat') fc_json(400, ['error' => 'Unknown action.']);
    $question = $_POST['question'] ?? '';
    if (!is_string($question)) fc_json(400, ['error' => 'Enter a question.']);
    $question = trim($question);
    if ($question === '' || strlen($question) > FC_MAX_QUESTION_BYTES || preg_match('//u', $question) !== 1) {
        fc_json(422, ['error' => 'Enter a valid question under 1,200 bytes (500 characters recommended).']);
    }
    if (!fc_rate_limit($session)) fc_json(429, ['error' => 'My fake CPU is overheating. Please try again later.']);
    $response = fc_answer($question, fc_rules());
    $uid = fc_linked_user($session);
    fc_record($session, $question, $response, $uid);
    fc_json(200, [
        'answer' => $response['answer'],
        'rule' => $response['rule'],
        'matchType' => $response['trigger']['type'] ?? 'none',
        'score' => $response['score'],
        'linked' => $uid !== null
    ]);
} catch (Throwable $error) {
    error_log('Fake chatbot public endpoint failed: ' . $error->getMessage());
    fc_json(503, ['error' => 'The fake brain is temporarily unavailable. Please try again later.']);
}
