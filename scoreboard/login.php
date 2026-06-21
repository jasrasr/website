<?php declare(strict_types=1);
/**
 * Filename: login.php
 * Revision : 1.2.1
 * Description : Login page for CVC Scoreboard admin. Handles session creation
 *               and returns users to the scoreboard page they originally requested.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial release
 * 1.1.0 Redirect forced-reset users directly to change-password.php
 * 1.2.0 Preserve and validate the requested scoreboard destination through normal login,
 *       existing-session login, and forced password changes
 * 1.2.1 Load the shared light/dark theme toggle
 */

require __DIR__ . '/auth.php';

function loginAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/login.php'));
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $basePath === '.' ? '' : $basePath;
}

function safeLoginRedirect(string $candidate): string
{
    $default = './enter-scores.php';
    $candidate = trim($candidate);

    if ($candidate === '' || preg_match('/[\r\n]/', $candidate) === 1 || str_contains($candidate, '\\')) {
        return $default;
    }

    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])) {
        return $default;
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === '' || str_starts_with($path, '//') || preg_match('~(^|/)\.\.(/|$)~', $path) === 1) {
        return $default;
    }

    if (str_starts_with($path, '/')) {
        $basePath = loginAppBasePath();
        if ($basePath !== '' && $path !== $basePath && !str_starts_with($path, $basePath . '/')) {
            return $default;
        }
    }

    return $candidate;
}

function loginPasswordReturnPath(string $redirect): string
{
    $path = (string) (parse_url($redirect, PHP_URL_PATH) ?? '');
    $basePath = loginAppBasePath();

    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath) + 1);
    } else {
        $path = ltrim($path, '/');
    }

    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    return $path !== '' ? $path : 'enter-scores.php';
}

authStart();
$redirect = safeLoginRedirect((string) ($_GET['redirect'] ?? './enter-scores.php'));
$signedInUser = authUser();

if ($signedInUser !== null) {
    if (authPasswordChangeRequired($signedInUser)) {
        $returnTo = rawurlencode(loginPasswordReturnPath($redirect));
        header("Location: ./change-password.php?force=1&return={$returnTo}");
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $redirect = safeLoginRedirect((string) ($_POST['redirect'] ?? './enter-scores.php'));

    $user = attemptLogin($username, $password);
    if ($user !== null) {
        $_SESSION[AUTH_SESSION] = $user;
        if (authPasswordChangeRequired($user)) {
            $returnTo = rawurlencode(loginPasswordReturnPath($redirect));
            header("Location: ./change-password.php?force=1&return={$returnTo}");
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    }

    $error = 'Invalid username or password.';
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
          <p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="POST" action="./login.php" class="login-form">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>" />
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
    <script src="./public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
