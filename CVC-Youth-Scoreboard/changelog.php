<?php declare(strict_types=1);
/**
 * Filename: changelog.php
 * Revision : 1.0.0
 * Description : Signed-in web viewer for the CVC Scoreboard CHANGELOG.md file.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-02
 * Changelog :
 * 1.0.0 initial release
 */

require __DIR__ . '/auth.php';
$user = requireAuth('root', './login.php');

$changelogFile = __DIR__ . '/CHANGELOG.md';
$markdown = is_file($changelogFile) ? file_get_contents($changelogFile) : '# Changelog' . PHP_EOL . PHP_EOL . 'No changelog entries found.';

function changelogInline(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
}

function renderChangelogMarkdown(string $markdown): string
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $level = strlen($matches[1]);
            $html .= '<h' . $level . '>' . changelogInline($matches[2]) . '</h' . $level . ">\n";
            continue;
        }

        if (preg_match('/^-\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= '<li>' . changelogInline($matches[1]) . "</li>\n";
            continue;
        }

        if ($inList) {
            $html .= "</ul>\n";
            $inList = false;
        }
        $html .= '<p>' . changelogInline($trimmed) . "</p>\n";
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboard Changelog</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="page-header">
        <div>
          <p>Signed in as <?= htmlspecialchars($user['username']) ?></p>
          <h1>Changelog</h1>
          <p class="updated-at">Source: CHANGELOG.md</p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./enter-scores.php">Scoreboard</a>
          <a class="au-btn" href="./logout.php">Sign Out</a>
        </div>
      </header>

      <main class="au-section changelog-content">
        <?= renderChangelogMarkdown($markdown ?: '') ?>
      </main>
    </div>
  </body>
</html>

