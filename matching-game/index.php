<?php
declare(strict_types=1);
$identity = null;
$user = null;
$canSaveHistory = false;
try {
    require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
    $identity = \Jasr\Framework\SharedIdentity::connect();
    $user = $identity->user();
    $canSaveHistory = $user !== null && $identity->can('matching-game', 'member');
} catch (Throwable $error) {
    // Public play works without shared identity.
}
$loginUrl = $identity ? $identity->loginUrl($_SERVER['REQUEST_URI'] ?? '/github/matching-game/') : '/user-management/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#d8f1ff">
  <title>Matching Game</title>
  <link rel="stylesheet" href="style.css?v=1.5.0">
  <script src="game.js?v=1.5.1" defer></script>
</head>
<body>
  <main class="game" aria-labelledby="title" data-save-history="<?= $canSaveHistory ? 'true' : 'false' ?>" data-csrf="<?= htmlspecialchars($identity ? $identity->csrf() : '', ENT_QUOTES, 'UTF-8') ?>">
    <h1 id="title">Matching Game</h1>
    <div class="play-layout">
      <div id="board" class="board" role="group" aria-label="Color matching board"></div>
      <div class="game-side">
        <p id="progress" class="progress" aria-live="polite">Pairs 0 of 8</p>
        <div class="game-stats" aria-label="Current game"><span>Time <strong id="timer">0:00</strong></span><span>Tries <strong id="attempts">0</strong></span></div>
        <p id="message" class="message" aria-live="polite">Find two colors that match!</p>
        <div class="setup">
          <label class="mode-field">Match
            <select id="match-mode">
              <option value="colors">Colors</option>
              <option value="letters">Letters</option>
              <option value="numbers">Numbers</option>
              <option value="objects">Objects</option>
            </select>
          </label>
          <label class="sound-field"><input id="read-aloud" type="checkbox"> Read tiles aloud</label>
          <details class="sound-help">
            <summary>Where do my recordings go?</summary>
            <p>Save MP3 files in <code>matching-game/sounds/</code>:</p>
            <ul>
              <li><code>colors/red.mp3</code></li>
              <li><code>letters/a.mp3</code></li>
              <li><code>numbers/1.mp3</code></li>
              <li><code>objects/apple.mp3</code></li>
            </ul>
            <p>The browser voice is used when a recording is missing. See <code>sounds/README.md</code> for every filename.</p>
          </details>
          <p class="setup-title">Board size</p>
          <div class="size-fields">
            <label>Rows <input id="rows" type="number" min="2" max="6" step="1" value="4" inputmode="numeric"></label>
            <span aria-hidden="true">×</span>
            <label>Columns <input id="columns" type="number" min="2" max="6" step="1" value="4" inputmode="numeric"></label>
          </div>
          <p id="size-error" class="size-error" role="alert" hidden>Choose 2–6 rows and columns with an even number of blocks.</p>
        </div>
        <button id="new-game" class="new-game" type="button">New game</button>
        <div class="session-score" aria-live="polite"><strong>This session</strong><span id="session-totals">0 games · 0 pairs</span><span id="session-best">Best on 4×4: —</span></div>
        <div class="account-history">
          <?php if ($canSaveHistory): ?>
            <p id="save-status" aria-live="polite">Finished games save to your account.</p>
            <button id="history-toggle" type="button" aria-expanded="false" aria-controls="history-panel">View saved games</button>
          <?php else: ?>
            <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Sign in with User Management to save game history</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if ($canSaveHistory): ?>
      <section id="history-panel" class="history-panel" aria-labelledby="history-title" hidden><h2 id="history-title">Saved games</h2><p id="history-summary">Loading…</p><ol id="history-list"></ol></section>
    <?php endif; ?>
  </main>
</body>
</html>
