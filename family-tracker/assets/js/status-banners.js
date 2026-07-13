/**
 * Project: Family GPS Tracker
 * File: assets/js/status-banners.js
 * Revision: 1.5.9
 * Description: Applies visible success, error, and progress styling to the shared status card.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-12
 * Modified: 2026-07-12
 */
(function () {
    'use strict';

    function installStyles() {
        if (document.getElementById('family-tracker-status-banner-style')) return;
        var style = document.createElement('style');
        style.id = 'family-tracker-status-banner-style';
        style.textContent = '.status-card{transition:border-color .2s ease,background-color .2s ease,box-shadow .2s ease}.status-card.status-success{border-color:rgba(74,222,128,.65);background:rgba(20,83,45,.92);box-shadow:0 12px 30px rgba(34,197,94,.16)}.status-card.status-error{border-color:rgba(251,113,133,.75);background:rgba(76,5,25,.94);box-shadow:0 12px 30px rgba(244,63,94,.18)}.status-card.status-progress{border-color:rgba(96,165,250,.65);background:rgba(23,37,84,.94);box-shadow:0 12px 30px rgba(59,130,246,.16)}html[data-appearance="light"] .status-card.status-success{background:rgba(220,252,231,.96);color:#14532d}html[data-appearance="light"] .status-card.status-error{background:rgba(255,228,230,.97);color:#881337}html[data-appearance="light"] .status-card.status-progress{background:rgba(219,234,254,.97);color:#1e3a8a}';
        document.head.appendChild(style);
    }

    function boot() { installStyles(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
