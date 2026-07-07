/**
 * Project: Family GPS Tracker
 * File: assets/js/member-badges.js
 * Revision: 1.3.6
 * Description: Adds You and Owner badges plus a display-name update form.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */
(function () {
    'use strict';

    var csrfToken = '';

    function css() {
        if (document.getElementById('family-tracker-member-badge-style')) return;
        var style = document.createElement('style');
        style.id = 'family-tracker-member-badge-style';
        style.textContent = '.member-header{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.role-badges{display:flex;gap:.35rem;flex-wrap:wrap}.role-badge{border:1px solid var(--border);border-radius:999px;padding:.18rem .45rem;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em;background:rgba(255,255,255,.08);color:var(--text)}.role-badge.you{background:rgba(74,222,128,.18);border-color:rgba(74,222,128,.45);color:#bbf7d0}.role-badge.owner{background:rgba(250,204,21,.14);border-color:rgba(250,204,21,.4);color:#fef08a}.profile-edit{display:grid;gap:.55rem;margin-top:.75rem}.profile-edit-row{display:grid;grid-template-columns:1fr auto;gap:.5rem}.profile-edit input{min-width:0}.profile-edit button{width:auto}.profile-edit-note{color:var(--muted);font-size:.85rem}@media(max-width:850px){.profile-edit-row{grid-template-columns:1fr}.profile-edit button{width:100%}}';
        document.head.appendChild(style);
    }

    function setStatus(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
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

    function addProfileForm() {
        var info = account();
        var card = document.getElementById('accountTitle');
        if (!info || !card || document.getElementById('displayNameForm')) return;

        var form = document.createElement('form');
        form.id = 'displayNameForm';
        form.className = 'profile-edit';
        form.innerHTML = '<label>Display name</label><div class="profile-edit-row"><input id="displayNameInput" name="displayName" maxlength="80" required><button type="submit" class="secondary">Save Name</button></div><div class="profile-edit-note">This changes the name shown in the account card, member list, and map labels.</div>';
        card.parentNode.appendChild(form);
        document.getElementById('displayNameInput').value = info.name;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            saveDisplayName();
        });
    }

    function refreshToken() {
        return fetch('profile.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
    }

    function saveDisplayName() {
        var input = document.getElementById('displayNameInput');
        var info = account();
        if (!input || !info) return;
        var value = input.value.trim();
        if (!value) return setStatus('Display name is required.');
        setStatus('Saving display name...');
        refreshToken().then(function () {
            return fetch('profile.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ displayName: value })
            });
        }).then(function (r) {
            return r.json();
        }).then(function (data) {
            if (!data || !data.ok) throw new Error((data && data.error) || 'Display name update failed.');
            if (data.csrfToken) csrfToken = data.csrfToken;
            var accountTitle = document.getElementById('accountTitle');
            if (accountTitle) accountTitle.textContent = value + ' (' + info.role + ')';
            setStatus('Display name updated.');
            var refresh = document.getElementById('refreshBtn');
            if (refresh) refresh.click();
            run();
        }).catch(function (error) {
            setStatus(error.message || 'Display name update failed.');
        });
    }

    function run() {
        css();
        addProfileForm();
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
