/**
 * Project: Family GPS Tracker
 * File: assets/js/member-sections.js
 * Revision: 1.4.3
 * Description: Separates active group members into live, stale, and no-location sections.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    function statusOf(card) {
        var badge = card.querySelector('.badge');
        var text = badge ? badge.textContent.trim().toLowerCase() : '';
        if (text.indexOf('stale') >= 0) return 'stale';
        if (text.indexOf('no location') >= 0) return 'missing';
        return 'live';
    }

    function heading(text) {
        var node = document.createElement('div');
        node.className = 'member-section-heading';
        node.textContent = text;
        return node;
    }

    function applySections() {
        var list = document.getElementById('memberList');
        if (!list) return;
        var cards = Array.from(list.querySelectorAll('.member-card'));
        if (!cards.length) return;
        list.querySelectorAll('.member-section-heading').forEach(function (node) { node.remove(); });

        var groups = { live: [], stale: [], missing: [] };
        cards.forEach(function (card) { groups[statusOf(card)].push(card); });
        list.innerHTML = '';

        if (groups.live.length) {
            list.appendChild(heading('Live / Recent'));
            groups.live.forEach(function (card) { list.appendChild(card); });
        }
        if (groups.stale.length) {
            list.appendChild(heading('Stale'));
            groups.stale.forEach(function (card) { list.appendChild(card); });
        }
        if (groups.missing.length) {
            list.appendChild(heading('No Location Yet'));
            groups.missing.forEach(function (card) { list.appendChild(card); });
        }
    }

    function boot() {
        applySections();
        window.setInterval(applySections, 3000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
