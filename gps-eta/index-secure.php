<?php
/*
Project: GPS Speed + ETA Tracker
File: gps-eta/index-secure.php
Revision: 1.8.11
Author: Jason Lamb
Created: 2026-06-30
Modified: 2026-06-30
Description: Wrapper entrypoint that loads index.php and injects client-side table rendering, trip-session, GPS signal, PHP trip-store helpers, mobile layout refinements, combined speed/time rows, no-key live map display, no-API trip enhancements, minute-based adjusted ETA, sticky drive mode, dashboard profiles, clearer selected-dashboard state, and clear toolbar active states.
*/

ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();

if (is_string($html)) {
    $html = str_replace(
        'Rev 1.5.0 &bull; Updated 2026-06-30 &bull; Per-Device History + 365-Day Retention',
        'Rev 1.8.11 &bull; Updated 2026-06-30 &bull; Clear Toolbar States',
        $html
    );

    $html = str_replace(
        '<section class="card"><p class="small"><strong>Other useful calculations to consider next:</strong> estimated route error/correction factor, average moving speed, trip start time, pause count, last GPS update age, and distance per GPS ping. The only big missing piece is true road-route distance/traffic, which needs a maps/directions API.</p></section>',
        '<section class="card"><p class="small"><strong>Useful non-map features still available:</strong> saved ETA offset presets, trip comparison, larger map refinements, and improved GPX/KML exports. Dashboard profiles, clearer selected-dashboard state, clear toolbar states, sticky drive mode, map controls, minute ETA offset, live map, GPS jump filtering, ETA drift, pause count, background resume recovery, and GPX/KML export are already included. True road-route distance and traffic-aware ETA require a maps/directions API.</p></section>',
        $html
    );
}

$styleTag = '<style id="gps-eta-mobile-distance-row">@media(max-width:650px){section.card:first-of-type .row{grid-template-columns:minmax(0,1fr) 128px!important;gap:10px!important;align-items:end!important}section.card:first-of-type .row input,section.card:first-of-type .row select{min-height:58px!important}section.card:first-of-type .row label{min-height:24px!important}}@media(max-width:390px){section.card:first-of-type .row{grid-template-columns:minmax(0,1fr) 112px!important;gap:8px!important}}</style>';
$scriptTag = '<script src="ui-render.js?v=1.5.1"></script><script src="trip-sessions.js?v=1.6.0"></script><script src="gps-quality.js?v=1.8.2"></script><script src="metric-combiner.js?v=1.8.4"></script><script src="session-store.js?v=1.8.0"></script><script src="no-api-enhancements.js?v=1.8.7"></script><script src="map-loader.js?v=1.8.8"></script><script src="drive-mode.js?v=1.8.11"></script><script src="dashboard-mode.js?v=1.8.10"></script>';

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
