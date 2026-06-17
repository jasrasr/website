<?php declare(strict_types=1);
/**
 * Filename: login.php
 * Revision : 1.1.0
 * Description : Login page for CVC Scoreboard admin. Handles session creation.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 Initial release
 * 1.1.0 Redirect forced-reset users directly to change-password.php
 */

require __DIR__ . '/auth.php';
authStart();
$signedInUser = authUser();

if ($signedInUser !== null) {
    if (authPasswordChangeRequired($signedInUser)) {
        header('Location: ./change-password.php?force=1&return=scoreboards.php');
        exit;
    }

    header('Location: ./enter-scores.php');
    exit;
}

$error    = '';
$redirect = htmlspecialchars($_GET['redirect'] ?? './enter-scores.php', ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $postRedirect = $_POST['redirect'] ?? './enter-scores.php';

    $user = attemptLogin($username, $password);
    if ($user !== null) {
        $_SESSION[AUTH_SESSION] = $user;
        if (authPasswordChangeRequired($user)) {
            header('Location: ./change-password.php?force=1&return=scoreboards.php');
            exit;
        }
        header('Location: ' . $postRedirect);
        exit;
    }

    $error    = 'Invalid username or password.';
    $redirect = htmlspecialchars($postRedirect, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboard — Sign In</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="login-page">
      <div class="login-card">
        <h1>CVC Scoreboard</h1>
        <p class="login-subtitle">Sign in to manage scores</p>
        <?php if ($error !== ''): ?>
          <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="./login.php" class="login-form">
          <input type="hidden" name="redirect" value="<?= $redirect ?>" />
          <div class="login-field">
            <label for="username">Username</label>
            <input
              type="text"
              id="username"
              name="username"
              autocomplete="username"
              autocapitalize="none"
              spellcheck="false"
              required
            />
          </div>
          <div class="login-field">
            <label for="password">Password</label>
            <input
              type="password"
              id="password"
              name="password"
              autocomplete="current-password"
              required
            />
          </div>
          <button type="submit" class="login-button">Sign In</button>
        </form>
      </div>
    </div>
  </body>
</html>
