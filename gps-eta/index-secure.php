<?php
/*
Project: GPS Speed + ETA Tracker
File: gps-eta/index-secure.php
Revision: 1.7.0
Author: Jason Lamb
Created: 2026-06-30
Modified: 2026-06-30
Description: Wrapper entrypoint that loads index.php and injects client-side table rendering, trip-session, and GPS quality helpers.
*/

ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();

if (is_string($html)) {
    $html = str_replace(
        'Rev 1.5.0 &bull; Updated 2026-06-30 &bull; Per-Device History + 365-Day Retention',
        'Rev 1.7.0 &bull; Updated 2026-06-30 &bull; GPS Quality + Trip Sessions',
        $html
    );
}

$scriptTag = '<script src="ui-render.js?v=1.5.1"></script><script src="trip-sessions.js?v=1.6.0"></script><script src="gps-quality.js?v=1.7.0"></script>';

if (is_string($html) && strpos($html, '</body>') !== false) {
    echo str_replace('</body>', $scriptTag . '</body>', $html);
} else {
    echo $html;
    echo $scriptTag;
}
