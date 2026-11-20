<?php
/*
File Name: link-history.php
Revision: 2.0.0
Description: Explicit consent-based anonymous history linking to an existing User Management account.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Add opt-in history migration, personal history and delete/unlink controls.
*/
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
try {
    $auth = \Jasr\Framework\SharedIdentity::connect();
    $user = $auth->user();
    if ($user === null || !empty($user['mustChangePassword'])) {
        $auth->requireProject(FC_PROJECT, 'viewer');
        // A valid registered project is required for login return URL handling.
        exit;
    }
    $session = fc_browser_hash();
    $uid = (string)$user['id'];
    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!fc_same_origin() || !$auth->validCsrf($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid request token.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'link') {
            $count = fc_link_history($session, $uid);
            $notice = 'Linked ' . $count . ' anonymous question(s). Future questions on this browser will save to your account while signed in.';
        } elseif ($action === 'unlink') {
            fc_unlink_history($session);
            $notice = 'This browser is no longer automatically linked. Previously saved account questions remain in your account.';
        } elseif ($action === 'delete') {
            $count = fc_delete_owned_history($session, $uid);
            $notice = 'Permanently deleted ' . $count . ' saved account questions.';
        } else {
            http_response_code(400);
            exit('Unknown action.');
        }
    }
    $history = fc_recent(\Jasr\Framework\JsonStore::read(fc_store('history.json'))['records'], $session, $uid);
    $links = \Jasr\Framework\JsonStore::read(fc_store('links.json'))['records'];
    $linked = ($links[$session]['userId'] ?? null) === $uid;
} catch (Throwable $e) {
    error_log('Fake chatbot account history failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Account history is temporarily unavailable.');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Account history · Very Fake AI</title><link rel="stylesheet" href="assets/style.css?v=2.0.0"></head>
<body><main class="app">
  <p><a href="./">Back to fake chatbot</a></p>
  <h1>Your very real history of very fake answers</h1>
  <p>Signed in as <strong><?=fc_escape($user['name'] ?? $user['username'] ?? 'account')?></strong>. Linking is optional; anonymous questions remain anonymous until you choose to save them.</p>
  <?php if ($notice !== ''): ?><p class="notice" role="status"><?=fc_escape($notice)?></p><?php endif; ?>
  <section class="panel">
    <h2>Account controls</h2>
    <p><?= $linked ? 'This browser is linked to your account while you are signed in.' : 'This browser is not linked to your account.' ?></p>
    <form method="post"><input type="hidden" name="csrf" value="<?=fc_escape($auth->csrf())?>">
      <button name="action" value="link">Link this browser's anonymous history</button>
      <button name="action" value="unlink" class="secondary">Stop linking this browser</button>
      <button name="action" value="delete" class="danger" data-confirm="Delete ALL stored questions saved under your account? This cannot be undone.">Delete my saved account history</button>
    </form>
  </section>
  <section class="panel">
    <h2>Your most recent 100 questions</h2>
    <p>Includes your saved account questions and anonymous questions from this browser. Only the account's owner can view account-linked questions here.</p>
    <?php if ($history === []): ?><p>No questions stored yet.</p><?php endif; ?>
    <?php foreach ($history as $entry): ?>
    <article class="history-entry">
      <small><?=fc_escape(gmdate('Y-m-d H:i', (int)($entry['timestamp'] ?? 0)))?> UTC · <?=empty($entry['userId']) ? 'Anonymous on this browser' : 'Saved to account'?></small>
      <h3><?=fc_escape($entry['question'] ?? '')?></h3>
      <p><?=fc_escape($entry['answer'] ?? '')?></p>
    </article>
    <?php endforeach; ?>
  </section>
  <footer>Very Fake AI · Rev 2.0.0 · No AI API was harmed in the making of this website.</footer>
</main><script src="assets/confirm.js?v=2.0.0" defer></script></body></html>
