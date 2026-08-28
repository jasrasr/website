<?php
/**
 * File: readme.php
 * Project: TV Binge Board
 * Description: Renders README.md inside the application shell.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$path = __DIR__ . '/README.md';
$content = is_file($path) ? file_get_contents($path) : '# README\n\nNo README found.';
app_page_header('README');
?>
<section class="card markdown-body">
    <?= app_simple_markdown((string)$content) ?>
</section>
<?php app_page_footer(); ?>
