<?php
/*
File Name: index.php
Revision: 2.0.0
Description: Anonymous fake AI chat portal backed by framework JsonStore and optional identity linking.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Add persistent anonymous chat, explicit fake AI labels and account/history links.
*/
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
fc_browser_hash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Laughably fake AI. Real question history, pretend answers, and zero AI.">
  <title>Very Fake AI | JASR</title>
  <link rel="stylesheet" href="assets/style.css?v=2.0.0">
  <script src="assets/app.js?v=2.0.0" defer></script>
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div>
        <p class="eyebrow">JASR / HIGHLY UNQUALIFIED TECHNOLOGY</p>
        <h1>Very Fake <span class="dot">AI</span></h1>
        <p class="subtitle">Artificial? Yes. Intelligence? Our lawyers told us to stop answering that.</p>
      </div>
      <span class="status-pill">100% FAKE · 0% AI API · 110% ATTITUDE</span>
    </header>

    <nav class="links" aria-label="Chatbot navigation">
      <a href="link-history.php">Link or view account history</a>
      <a href="admin/">Admin portal</a>
      <a href="https://github.com/jasrasr/website/tree/main/fake-chatbot">Source code</a>
    </nav>

    <main class="chat-card">
      <div class="chat-header">
        <div>
          <h2>Questionable Answers Department</h2>
          <p id="status" role="status">Connecting to the fake brain...</p>
        </div>
        <button type="button" id="clear" class="secondary">Clear screen</button>
      </div>
      <section id="messages" class="messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Chat conversation">
        <div class="message bot"><span class="speaker">FAKE BOT</span><p>Initializing imaginary intelligence...</p></div>
      </section>
      <form id="chat-form" autocomplete="off">
        <label for="message">Ask anything. Accuracy sold separately.</label>
        <div class="composer">
          <input id="message" name="question" type="text" maxlength="500" placeholder="Try: potato salad, Mr. Potato Head, printer, meaning of life..." required>
          <button id="send" type="submit" disabled>Ask the fake brain</button>
        </div>
      </form>
    </main>

    <section class="history-tools" aria-label="Conversation storage">
      <div>
        <h2>Yes, it remembers your questions.</h2>
        <p>Questions and selected answers are saved privately on this server. Anonymous history stays attached to this browser's random cookie. Account linking is optional.</p>
      </div>
      <button type="button" id="forget" class="secondary">Delete anonymous history</button>
    </section>
    <p class="disclaimer"><strong>This is not artificial intelligence.</strong> A collection of human-written jokes competes for keyword matches. Do not enter personal, confidential, or sensitive information. Site administrators can review submitted questions. Your anonymous questions expire after 90 days; linked questions are retained until you delete them, subject to the site's total history cap.</p>
    <footer>Very Fake AI · Rev 2.0.0 · Updated 2026-11-20 · Original PowerShell chatbot preserved in <a href="https://github.com/jasrasr/website/tree/main/fake-chatbot/legacy">legacy/</a></footer>
  </div>
</body>
</html>
