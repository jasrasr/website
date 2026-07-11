/**
 * Project: Family GPS Tracker
 * File: assets/js/invite-management.js
 * Revision: 1.4.8
 * Description: Owner invite management plus invite-aware join interception for new and existing accounts.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';

    function $(id) { return document.getElementById(id); }
    function status(text) {
        var node = $('statusText') || $('ownerStatus');
        if (node) node.textContent = text;
    }

    function json(response) {
        return response.json().then(function (data) {
            if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
            if (data.csrfToken) csrfToken = data.csrfToken;
            return data;
        });
    }

    function getInvites() {
        return fetch('invite-admin.php', { credentials: 'same-origin' }).then(json);
    }

    function postInvite(payload) {
        return fetch('invite-admin.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(json);
    }

    function formatLimit(invite) {
        var expiry = invite.expiresAt ? new Date(invite.expiresAt).toLocaleString() : 'Never expires';
        var uses = invite.maxUses > 0 ? invite.uses + ' / ' + invite.maxUses + ' uses' : invite.uses + ' uses / unlimited';
        return expiry + ' • ' + uses + ' • code ends ' + (invite.last4 || '----');
    }

    function renderInvites(data) {
        var list = $('ownerInviteList');
        if (!list) return;
        list.innerHTML = '';
        var invites = data.invites || [];
        if (!invites.length) {
            list.textContent = 'No managed invites yet. The original group invite code remains available in the tracker.';
            return;
        }
        invites.forEach(function (invite) {
            var card = document.createElement('article');
            card.className = 'member-card';
            var main = document.createElement('div');
            var title = document.createElement('div');
            title.className = 'member-name';
            title.textContent = invite.label || 'Group invite';
            var meta = document.createElement('div');
            meta.className = 'member-meta';
            meta.textContent = formatLimit(invite);
            main.append(title, meta);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = invite.active ? 'danger-button' : 'secondary';
            button.textContent = invite.active ? 'Revoke' : (invite.expired ? 'Expired' : invite.exhausted ? 'Used Up' : 'Revoked');
            button.disabled = !invite.active;
            button.addEventListener('click', function () {
                if (!window.confirm('Revoke this invite now?')) return;
                postInvite({ action: 'revoke_invite', inviteId: invite.id }).then(function (result) {
                    renderInvites(result);
                    status(result.message || 'Invite revoked.');
                }).catch(function (error) { status(error.message); });
            });
            card.append(main, button);
            list.appendChild(card);
        });
    }

    function loadInvites() {
        if (!$('ownerInviteList')) return;
        getInvites().then(renderInvites).catch(function (error) { status(error.message); });
    }

    function createInvite(event) {
        event.preventDefault();
        var label = $('ownerInviteLabel').value.trim();
        var expiry = $('ownerInviteExpiry').value;
        var maxUses = Number($('ownerInviteUses').value || 0);
        status('Creating invite...');
        postInvite({ action: 'create_invite', label: label, expiry: expiry, maxUses: maxUses }).then(function (result) {
            renderInvites(result);
            $('ownerInviteLabel').value = '';
            var box = $('ownerInviteCodeBox');
            var code = $('ownerInviteFullCode');
            if (box && code && result.oneTimeInviteCode) {
                code.textContent = result.oneTimeInviteCode;
                box.classList.remove('hidden');
            }
            status(result.message || 'Invite created.');
        }).catch(function (error) { status(error.message); });
    }

    function guestJoin(event) {
        var form = event.target;
        if (!form || form.id !== 'joinForm') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        var payload = {
            action: 'guest_join',
            inviteCode: form.elements.inviteCode.value,
            displayName: form.elements.displayName.value,
            username: form.elements.username.value,
            password: form.elements.password.value,
            consentAccepted: form.elements.consentAccepted.checked,
            rememberMe: form.elements.rememberMe.checked
        };
        status('Joining group...');
        fetch('invite-join.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(json).then(function () { window.location.reload(); }).catch(function (error) { status(error.message); });
    }

    function existingJoin(event) {
        var target = event.target;
        if (!target || target.id !== 'joinGroupBtn') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        var input = $('joinGroupCode');
        var code = input ? input.value.trim() : '';
        if (!code) return status('Invite code is required.');
        status('Joining group...');
        fetch('groups.php', { credentials: 'same-origin' }).then(json).then(function () {
            return fetch('invite-join.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'existing_join', inviteCode: code })
            }).then(json);
        }).then(function () { window.location.reload(); }).catch(function (error) { status(error.message); });
    }

    function boot() {
        document.addEventListener('submit', guestJoin, true);
        document.addEventListener('click', existingJoin, true);
        var form = $('ownerInviteForm');
        var refresh = $('ownerInviteRefreshBtn');
        if (form) form.addEventListener('submit', createInvite);
        if (refresh) refresh.addEventListener('click', loadInvites);
        loadInvites();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
