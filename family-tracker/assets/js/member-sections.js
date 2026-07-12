/**
 * Project: Family GPS Tracker
 * File: assets/js/member-sections.js
 * Revision: 1.5.8
 * Description: Separates active group members into stable live, stale, and no-location sections without polling flicker.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-12
 */
(function () {
    'use strict';

    var lastSignature = '';
    var observer = null;
    var scheduled = false;

    function statusOf(card) {
        var badge = card.querySelector('.badge');
        var text = badge ? badge.textContent.trim().toLowerCase() : '';
        if (text.indexOf('stale') >= 0) return 'stale';
        if (text.indexOf('no location') >= 0) return 'missing';
        return 'live';
    }

    function memberKey(card) {
        return card.dataset.memberId || ((card.querySelector('.member-name') || {}).textContent || '').trim();
    }

    function heading(kind, text) {
        var node = document.createElement('div');
        node.className = 'member-section-heading';
        node.dataset.sectionKind = kind;
        node.textContent = text;
        return node;
    }

    function signatureFor(cards) {
        return cards.map(function (card) { return memberKey(card) + ':' + statusOf(card); }).join('|');
    }

    function applySections() {
        scheduled = false;
        var list = document.getElementById('memberList');
        if (!list) return;
        var cards = Array.from(list.querySelectorAll(':scope > .member-card'));
        if (!cards.length) return;

        var signature = signatureFor(cards);
        if (signature === lastSignature && list.querySelector('.member-section-heading')) return;
        lastSignature = signature;

        var groups = { live: [], stale: [], missing: [] };
        cards.forEach(function (card) { groups[statusOf(card)].push(card); });

        if (observer) observer.disconnect();
        list.querySelectorAll(':scope > .member-section-heading').forEach(function (node) { node.remove(); });

        var fragment = document.createDocumentFragment();
        if (groups.live.length) {
            fragment.appendChild(heading('live', 'Live / Recent'));
            groups.live.forEach(function (card) { fragment.appendChild(card); });
        }
        if (groups.stale.length) {
            fragment.appendChild(heading('stale', 'Stale'));
            groups.stale.forEach(function (card) { fragment.appendChild(card); });
        }
        if (groups.missing.length) {
            fragment.appendChild(heading('missing', 'No Location Yet'));
            groups.missing.forEach(function (card) { fragment.appendChild(card); });
        }
        list.appendChild(fragment);
        observe(list);
    }

    function scheduleApply() {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(applySections);
    }

    function observe(list) {
        if (!('MutationObserver' in window)) return;
        if (!observer) {
            observer = new MutationObserver(function (mutations) {
                var relevant = mutations.some(function (mutation) {
                    return mutation.type === 'childList' ||
                        (mutation.type === 'characterData') ||
                        (mutation.type === 'attributes' && mutation.attributeName === 'class');
                });
                if (relevant) scheduleApply();
            });
        }
        observer.observe(list, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class'] });
    }

    function boot() {
        var list = document.getElementById('memberList');
        if (!list) return;
        observe(list);
        scheduleApply();
        window.addEventListener('familyTrackerMemberUiRefresh', scheduleApply);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
