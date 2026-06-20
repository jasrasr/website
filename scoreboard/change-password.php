<?php declare(strict_types=1);
/**
 * Filename: change-password.php
 * Revision : 1.2.0
 * Description : Signed-in user page for updating their own CVC Scoreboard password.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-05-28
 * Modified Date : 2026-06-20
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Support forced password changes for first-run and reset credentials
 * 1.2.0 Add a cancel action on forced password changes that signs out and returns to login
 */

require __DIR__ . '/auth.php';

authStart();
$currentUser = authUser();

if ($currentUser === null) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? './change-password.php');
    header("Location: ./login.php?redirect={$redirect}");
    exit;
}

$safeReturnPaths = [
    './enter-scores.php',
    'enter-scores.php',
    './scoreboards.php',
    'scoreboards.php',
    './changelog.php',
    'changelog.php',
    'youth/enter-scores.php',
    'collide/enter-scores.php',
    'frontlines/enter-scores.php',
    'enter-scores-quick.php',
    'youth/enter-scores-quick.php',
    'collide/enter-scores-quick.php',
    'frontlines/enter-scores-quick.php',
];

$returnTo = $_GET['return'] ?? './enter-scores.php';
if (!in_array($returnTo, $safeReturnPaths, true)) {
    $returnTo = './enter-scores.php';
}

$message = '';
$error = '';
$forceChange = authPasswordChangeRequired($currentUser);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = $_POST['return'] ?? $returnTo;
    if (!in_array($returnTo, $safeReturnPaths, true)) {
        $returnTo = './enter-scores.php';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        $result = changeCurrentUserPassword($currentUser['id'], $currentPassword, $newPassword);
        if ($result['ok']) {
            $message = $result['message'];
            $currentUser = authUser();
            $forceChange = false;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboard - Change Password</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="login-page">
      <div class="login-card">
        <h1>Change Password</h1>
        <p class="login-subtitle">
          <?= $forceChange ? 'Set a new password before continuing.' : 'Signed in as ' . htmlspecialchars($currentUser['username'] ?? '') ?>
        </p>
        <?php if ($message !== ''): ?>
          <p class="login-error" style="color:var(--positive)"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="./change-password.php" class="login-form">
          <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
          <div class="login-field">
            <label for="current_password">Current Password</label>
            <input
              type="password"
              id="current_password"
              name="current_password"
              autocomplete="current-password"
              required
            />
          </div>
          <div class="login-field">
            <label for="new_password">New Password</label>
            <input
              type="password"
              id="new_password"
              name="new_password"
              autocomplete="new-password"
              required
            />
          </div>
          <div class="login-field">
            <label for="confirm_password">Confirm New Password</label>
            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              autocomplete="new-password"
              required
            />
          </div>
          <button type="submit" class="login-button">Update Password</button>
        </form>
        <p class="status-text" style="margin-top:1rem">
          <?php if ($forceChange): ?>
            <a class="au-btn" href="./logout.php">Cancel and return to login</a>
          <?php else: ?>
            <a class="au-btn" href="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">Back to Scoreboard</a>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </body>
</html>
