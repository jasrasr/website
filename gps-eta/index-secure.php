<?php
/*
Project: GPS Speed + ETA Tracker
File: gps-eta/index-secure.php
Revision: 1.8.2
Author: Jason Lamb
Created: 2026-06-30
Modified: 2026-06-30
Description: Wrapper entrypoint that loads index.php and injects client-side table rendering, trip-session, GPS signal, PHP trip-store helpers, and mobile layout refinements.
*/

ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();

if (is_string($html)) {
    $html = str_replace(
        'Rev 1.5.0 &bull; Updated 2026-06-30 &bull; Per-Device History + 365-Day Retention',
        'Rev 1.8.2 &bull; Updated 2026-06-30 &bull; Combined GPS Signal Row',
        $html
    );
}

$styleTag = '<style id="gps-eta-mobile-distance-row">@media(max-width:650px){section.card:first-of-type .row{grid-template-columns:minmax(0,1fr) 128px!important;gap:10px!important;align-items:end!important}section.card:first-of-type .row input,section.card:first-of-type .row select{min-height:58px!important}section.card:first-of-type .row label{min-height:24px!important}}@media(max-width:390px){section.card:first-of-type .row{grid-template-columns:minmax(0,1fr) 112px!important;gap:8px!important}}</style>';
$scriptTag = '<script src="ui-render.js?v=1.5.1"></script><script src="trip-sessions.js?v=1.6.0"></script><script src="gps-quality.js?v=1.8.2"></script><script src="session-store.js?v=1.8.0"></script>';

if (is_string($html) && strpos($html, '</head>') !== false) {
    $html = str_replace('</head>', $styleTag . '</head>', $html);
}

if (is_string($html) && strpos($html, '</body>') !== false) {
    echo str_replace('</body>', $scriptTag . '</body>', $html);
} else {
    echo $html;
    echo $styleTag;
    echo $scriptTag;
}
