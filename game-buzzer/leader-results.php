<?php
declare(strict_types=1);
$scriptVersion = substr(hash_file('sha256', __DIR__ . '/leader-results.js'), 0, 16);
$styleVersion = substr(hash_file('sha256', __DIR__ . '/style.css'), 0, 16);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'self'; form-action 'self'");
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#101326"><title>Question Rankings</title><link rel="stylesheet" href="style.css?v=<?= $styleVersion ?>"></head>
<body><header><a href="./" class="brand">GAME <strong>BUZZER</strong></a><span id="connection" role="status">Question rankings</span></header>
<main><div id="notice" role="alert" hidden></div><section class="panel leader-panel">
<p class="eyebrow">LEADER QUESTION RESULTS</p><h1>Question rankings</h1>
<p class="intro">Combined saved picks, most popular first. Equal counts share a rank.</p>
<button id="copyTopTen">Copy top 10 for game setup</button>
<label for="searchQuestions">Find a question<input id="searchQuestions" type="search" placeholder="Search the question bank"></label>
<div id="questionList" class="question-list" aria-live="polite"></div></section>
<section class="panel"><h2>Anonymous activity</h2><p class="muted">No sign-in or personal details. Each browser gets a random saved ID; 20 pick saves or 5 suggestions are flagged for review, never blocked. Clearing browser storage creates a new ID.</p><p id="activitySummary" class="muted"></p><div id="activityList" class="activity-list" aria-live="polite"></div></section>
</main><footer>Game Buzzer · Question rankings</footer><script src="leader-results.js?v=<?= $scriptVersion ?>" defer></script></body></html>
