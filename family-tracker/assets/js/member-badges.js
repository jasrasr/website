/**
 * Project: Family GPS Tracker
 * File: assets/js/member-badges.js
 * Revision: 1.3.5
 * Description: Adds visible You and Owner badges to the signed-in user's family member card.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */
(function () {
    'use strict';

    function css() {
        if (document.getElementById('family-tracker-member-badge-style')) return;
        var style = document.createElement('style');
        style.id = 'family-tracker-member-badge-style';
        style.textContent = '.member-header{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.role-badges{display:flex;gap:.35rem;flex-wrap:wrap}.role-badge{border:1px solid var(--border);border-radius:999px;padding:.18rem .45rem;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em;background:rgba(255,255,255,.08);color:var(--text)}.role-badge.you{background:rgba(74,222,128,.18);border-color:rgba(74,222,128,.45);color:#bbf7d0}.role-badge.owner{background:rgba(250,204,21,.14);border-color:rgba(250,204,21,.4);color:#fef08a}';
        document.head.appendChild(style);
    }

    function account() {
        var text = (document.getElementById('accountTitle') || {}).textContent || '';
        var open = text.lastIndexOf('(');
        var close = text.lastIndexOf(')');
        if (open < 1 || close < open) return null;
        return { name: text.slice(0, open).trim(), role: text.slice(open + 1, close).trim().toLowerCase() };
    }

    function norm(value) {
        return String(value || '').trim().toLowerCase();
    }

    function span(kind, text) {
        var item = document.createElement('span');
        item.className = 'role-badge ' + kind;
        item.textContent = text;
        return item;
    }

    function run() {
        css();
        var info = account();
        var list = document.getElementById('memberList');
        if (!info || !list) return;
        var cards = list.getElementsByClassName('member-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var name = card.getElementsByClassName('member-name')[0];
            if (!name || norm(name.textContent) !== norm(info.name)) continue;
            var old = card.getElementsByClassName('role-badges')[0];
            if (old) old.remove();
            var header = name.parentNode.getElementsByClassName('member-header')[0];
            if (!header) {
                header = document.createElement('div');
                header.className = 'member-header';
                name.parentNode.insertBefore(header, name);
                header.appendChild(name);
            }
            var badges = document.createElement('div');
            badges.className = 'role-badges';
            badges.appendChild(span('you', 'You'));
            if (info.role === 'owner') badges.appendChild(span('owner', 'Owner'));
            header.appendChild(badges);
            if (list.firstElementChild !== card) list.insertBefore(card, list.firstElementChild);
            break;
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
    else run();
    window.setInterval(run, 2000);
}());
