/**
 * Project: Family GPS Tracker
 * File: assets/js/map-layout.js
 * Revision: 1.3.3
 * Description: Runtime map image layout helper for mobile browser reload rendering.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */
(() => {
    'use strict';

    function addMapLayoutStyles() {
        if (document.getElementById('family-tracker-map-layout-style')) return;
        const style = document.createElement('style');
        style.id = 'family-tracker-map-layout-style';
        style.textContent = '#map .leaflet-tile,#map .leaflet-marker-icon,#map .leaflet-marker-shadow{max-width:none;max-height:none;}';
        document.head.appendChild(style);
    }

    function applyMapImageLayout() {
        const map = document.getElementById('map');
        if (!map) return;
        const images = map.getElementsByTagName('img');
        for (const image of images) {
            image.style.maxWidth = 'none';
            image.style.maxHeight = 'none';
        }
    }

    function run() {
        addMapLayoutStyles();
        applyMapImageLayout();
        [100, 300, 700, 1200, 2000, 3500].forEach((delay) => window.setTimeout(applyMapImageLayout, delay));
    }

    document.addEventListener('DOMContentLoaded', run);
    window.addEventListener('load', run);
    window.addEventListener('resize', run);
    window.addEventListener('orientationchange', () => window.setTimeout(run, 450));
})();
