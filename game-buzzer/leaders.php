<?php
declare(strict_types=1);
require_once __DIR__ . '/release.php';
$scriptVersion = substr(hash_file('sha256', __DIR__ . '/leaders.js'), 0, 16);
$styleVersion = substr(hash_file('sha256', __DIR__ . '/style.css'), 0, 16);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'self'; form-action 'self'");
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#101326"><title>Choose Game Questions</title><link rel="stylesheet" href="style.css?v=<?= $styleVersion ?>"></head>
<body><header><a href="./" class="brand">GAME <strong>BUZZER</strong></a><span id="connection" role="status">Leader question poll</span></header>
<main><div id="notice" role="alert" hidden></div><section class="panel leader-panel">
<p class="eyebrow">LEADER QUESTION VOTING</p><h1>Choose your favorites</h1>
<p class="intro">Select any number of questions for an upcoming game. Questions are shuffled so you can choose your own favorites. You can also submit as many of your own A/B questions as you like.</p>
<p class="muted">Save your picks before leaving. <a href="leader-results.php">View results</a>.</p>
<div class="leader-toolbar"><strong id="selectionCount">0 selected</strong><div><button id="saveSelections">Save my picks</button></div></div>
<label for="searchQuestions">Find a question<input id="searchQuestions" type="search" placeholder="Search the question bank"></label>
<p class="muted">Questions are shown in random order. A question’s prompt and both choices are shown below.</p>
<div id="questionList" class="question-list" aria-live="polite"></div>
</section>
<section class="panel"><h2>Suggest a question</h2><p class="muted">Write a fun, kind, G-rated question for church and middle school students.</p>
<form id="suggestionForm"><label>Question<input name="prompt" maxlength="200" required></label><div class="leader-answers"><label>Answer A<input name="a" maxlength="120" required></label><label>Answer B<input name="b" maxlength="120" required></label></div><button type="submit">Add suggestion</button></form>
</section><p class="muted">Your saved picks are tied to this browser. Leaders using separate browsers get separate anonymous IDs.</p></main>
<?php gb_release_footer('Voting'); ?><script src="leaders.js?v=<?= $scriptVersion ?>" defer></script></body></html>
