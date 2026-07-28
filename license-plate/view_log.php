<?php
/* Enhanced Plate Log wrapper - Revision 1.2.31 */
ob_start();
require __DIR__ . '/view_log_legacy.php';
$html = (string)ob_get_clean();
$version = rawurlencode(function_exists('readProjectRevision') ? readProjectRevision() : '1.2.31');
$html = str_replace('</head>', '<link rel="stylesheet" href="view-log-enhancements.css?v=' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '">\n</head>', $html);
$html = str_replace('</body>', '<script src="view-log-enhancements.js?v=' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '"></script>\n</body>', $html);
echo $html;
