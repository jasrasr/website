<?php
/**
 * File: tests/markdown-render-test.php
 * Project: TV Binge Board
 * Description: Regression checks for the lightweight Markdown renderer.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

$markdown = <<<MARKDOWN
<!--
File: CHANGELOG.md
Project: TV Binge Board
-->

# Changelog

- Visible entry
MARKDOWN;

$html = app_simple_markdown($markdown);
$failures = [];
foreach (['&lt;!--', 'File: CHANGELOG.md', 'Project: TV Binge Board', '--&gt;'] as $hiddenText) {
    if (str_contains($html, $hiddenText)) {
        $failures[] = 'Rendered hidden metadata comment text: ' . $hiddenText;
    }
}
foreach (['<h1>Changelog</h1>', '<li>Visible entry</li>'] as $visibleHtml) {
    if (!str_contains($html, $visibleHtml)) {
        $failures[] = 'Missing expected rendered Markdown: ' . $visibleHtml;
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Markdown render checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\markdown-render-test.php
