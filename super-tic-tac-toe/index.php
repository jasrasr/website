<?php
declare(strict_types=1);
/*
File Name: index.php
Revision: 1.1.0
Description: Responsive Super Tic-Tac-Toe and configurable classic Tic-Tac-Toe.
Author: Jason Lamb
Created: 2026-11-18
Modified: 2026-11-18
Changelog: 1.1.0 - Add unlimited in-game undo and redo. 1.0.0 - Initial release.
*/
$identity = null;
$identityUser = null;
$canSaveHistory = false;
try {
    require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
    $identity = \Jasr\Framework\SharedIdentity::connect();
    $identityUser = $identity->user();
    $canSaveHistory = $identityUser !== null && $identity->can('super-tic-tac-toe', 'member');
} catch (Throwable $error) {
    // Public play stays available when shared identity is not configured.
}
$csrf = $identity ? $identity->csrf() : '';
$loginUrl = $identity ? $identity->loginUrl($_SERVER['REQUEST_URI'] ?? '/super-tic-tac-toe/') : '/user-management/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f5f8ff">
  <title>Super Tic-Tac-Toe</title>
  <link rel="stylesheet" href="style.css?v=1.1.0">
  <script defer src="game.js?v=1.1.0"></script>
</head>
<body>
  <main class="app-shell" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" data-save-history="<?= $canSaveHistory ? 'true' : 'false' ?>">
    <header class="topbar">
      <a class="brand" href="./" aria-label="Super Tic-Tac-Toe home"><span class="brand-mark">✳</span><span>PLAY / STRATEGY</span></a>
      <div class="account">
        <?php if ($identityUser): ?>
          <span class="signed-in">Playing as <?= htmlspecialchars((string) ($identityUser['name'] ?? $identityUser['username']), ENT_QUOTES, 'UTF-8') ?></span>
          <?php if (!$canSaveHistory): ?><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Enable score history</a><?php endif; ?>
        <?php else: ?>
          <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Sign in for score history</a>
        <?php endif; ?>
      </div>
    </header>

    <section class="hero">
      <div class="eyebrow"><span class="eyebrow-dot"></span> A classic with another layer</div>
      <h1>Super Tic-Tac-Toe</h1>
      <p>Play a quick round or send your opponent across the board.</p>
    </section>

    <section class="game-layout" aria-label="Tic-Tac-Toe game">
      <aside class="control-panel" aria-label="Game settings">
        <div class="panel-heading"><span class="section-label">SET UP YOUR GAME</span><button id="newGame" class="icon-button" type="button" aria-label="Start a new game" title="New game">↻</button></div>
        <label class="field-label" for="gameMode">Board</label>
        <select id="gameMode"><option value="super">Super Tic-Tac-Toe · 9 boards</option><option value="classic">Classic Tic-Tac-Toe</option></select>
        <div id="sizeField" class="field-group" hidden>
          <label class="field-label" for="boardSize">Board size</label>
          <select id="boardSize"><option value="3">3 × 3 · three in a row</option><option value="4">4 × 4 · four in a row</option><option value="5">5 × 5 · five in a row</option></select>
        </div>
        <div class="field-group">
          <span class="field-label">Players</span>
          <div class="segmented" role="group" aria-label="Number of players"><button type="button" data-setting="players" data-value="computer" aria-pressed="true">Vs computer</button><button type="button" data-setting="players" data-value="human" aria-pressed="false">Two players</button></div>
        </div>
        <div id="symbolField" class="field-group">
          <span class="field-label">You play as</span>
          <div class="segmented symbol-segment" role="group" aria-label="Your symbol"><button type="button" data-setting="symbol" data-value="X" aria-pressed="true">X</button><button type="button" data-setting="symbol" data-value="O" aria-pressed="false">O</button></div>
        </div>
        <div class="field-group">
          <label class="field-label" for="difficulty">Computer level</label>
          <select id="difficulty"><option value="easy">Easy</option><option value="medium" selected>Medium · balanced</option><option value="hard">Hard</option></select>
        </div>
        <div class="rule-card"><span class="rule-icon">✦</span><div><strong id="ruleTitle">Your move sets the next board</strong><p id="ruleText">Play in a mini-board, then your opponent must play in the matching position.</p></div></div>
        <button class="new-game-button" id="newGameBottom" type="button">New game</button>
      </aside>

      <section class="play-area">
        <div class="game-topline"><div><span class="section-label">CURRENT MATCH</span><h2 id="gameTitle">Super Tic-Tac-Toe</h2></div><span id="modeBadge" class="mode-badge">SUPER · 9 BOARDS</span></div>
        <div class="score-strip" aria-label="Match score"><div class="score-card active" id="scoreX"><span class="score-symbol x-text">X</span><span class="score-name" id="nameX">You</span><strong id="winsX">0</strong></div><div class="score-middle"><span class="vs-text">MATCH</span><span id="turnHint">X starts</span></div><div class="score-card" id="scoreO"><span class="score-symbol o-text">O</span><span class="score-name" id="nameO">Computer</span><strong id="winsO">0</strong></div></div>
        <div class="status-row"><div class="turn-status"><span class="turn-chip x-chip" id="turnChip">X</span><span id="statusText" aria-live="polite">X to move</span></div><span id="targetText" class="target-note">Choose any mini-board</span></div>
        <div class="move-controls" aria-label="Move history"><button id="undoMove" type="button" disabled>Undo</button><button id="redoMove" type="button" disabled>Redo</button><span id="historyPosition">Move 0</span></div>
        <div id="boardWrap" class="board-wrap super-wrap"><div id="board" class="board" role="grid" aria-label="Game board"></div></div>
        <div class="board-legend"><span><i class="legend-dot target-dot"></i>Playable board</span><span><i class="legend-dot previous-dot"></i>Previous move</span><span><i class="legend-dot won-dot"></i>Won board</span></div>
        <div class="bottom-row"><span id="moveCount">0 moves</span><span id="saveStatus">Scores on this device</span></div>
      </section>
    </section>
    <footer><span>Think ahead. Have fun.</span><span id="userHistoryLink"></span></footer>
  </main>
</body>
</html>
