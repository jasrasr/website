<?php
/*
Filename: plugin.php
Revision: 1.0.1
Description: YOURLS plugin that routes unknown short URL keywords to /smart-404.php for logging and mapping.
Author: Jason Lamb (with help from Codex CLI)
Created Date: 2026-05-18
Modified Date: 2026-05-18
Changelog:
1.0.0 initial release
1.0.1 rename display label so the plugin sorts near the top of the YOURLS plugin list

Plugin Name: 1 Smart 404 Unknown Keyword
Plugin URI: https://jasr.me/
Description: Sends unknown YOURLS keywords to /smart-404.php so misses can be logged and mapped.
Version: 1.0.1
Author: Jason Lamb (with help from Codex CLI)
*/
if (!defined('YOURLS_ABSPATH')) {
    die();
}

yourls_add_action('redirect_keyword_not_found', 'jasr_smart_404_unknown_keyword');

function jasr_smart_404_unknown_keyword($keyword): void
{
    if (is_array($keyword)) {
        $keyword = $keyword[0] ?? '';
    }

    $keyword = trim((string) $keyword, "/ \t\n\r\0\x0B");
    $target = '/smart-404.php';

    if ($keyword !== '') {
        $target .= '?missing=' . rawurlencode($keyword);
    }

    header('Location: ' . $target, true, 302);
    exit;
}
