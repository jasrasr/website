/**
 * Project: Friends & Family GPS Tracker
 * File: assets/js/share-code.js
 * Revision: 1.6.6
 * Description: Persistent owner-visible active share code with show/hide, copy, and reset controls.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-08-02
 * Modified: 2026-08-02
 */
(function () {
    'use strict';

    var csrfToken = '';
    var family = null;

    function $(id) { return document.getElementById(id); }

    function status(text) {
        var node = $('statusText');
        if (node) node.textContent = text;
    }

    function request(action, payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('api.php?action=' + encodeURIComponent(action), options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Share-code request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function installControls() {
        var card = $('inviteCard');
        var code = $('inviteCodeDisplay');
        var copy = $('copyInviteBtn');
        var reset = $('regenerateInviteBtn');
        if (!card || !code || !copy || !reset) return;

        var heading = card.querySelector('h2');
        if (heading) heading.textContent = 'Active Share Code';

        var descriptions = card.querySelectorAll('.muted');
        if (descriptions[0]) descriptions[0].textContent = 'Owner-only. This active code remains available until you hide it or reset it.';
        if (descriptions[1]) descriptions[1].textContent = 'Reset invalidates the previous code immediately. Hide only changes what is displayed to the owner; it does not disable the code.';

        reset.textContent = 'Reset Share Code';

        if (!$('toggleInviteVisibilityBtn')) {
            var toggle = document.createElement('button');
            toggle.id = 'toggleInviteVisibilityBtn';
            toggle.type = 'button';
            toggle.className = 'secondary';
            toggle.textContent = 'Hide Code';
            reset.parentNode.insertBefore(toggle, reset);
            toggle.addEventListener('click', toggleVisibility);
        }

        copy.addEventListener('click', copyCode, true);
        reset.addEventListener('click', resetCode, true);
    }

    function render() {
        installControls();
        var card = $('inviteCard');
        var code = $('inviteCodeDisplay');
        var copy = $('copyInviteBtn');
        var toggle = $('toggleInviteVisibilityBtn');
        if (!card || !code || !copy || !toggle || !family) return;

        card.classList.remove('hidden');
        var activeCode = String(family.inviteCode || '');
        var hidden = Boolean(family.inviteCodeHidden);

        if (!activeCode) {
            code.textContent = 'Reset required to create a persistent owner-visible code';
            copy.disabled = true;
            toggle.disabled = true;
            toggle.textContent = 'Hide Code';
            return;
        }

        code.textContent = hidden ? '•••••-•••••' : activeCode;
        code.dataset.fullCode = activeCode;
        copy.disabled = hidden;
        toggle.disabled = false;
        toggle.textContent = hidden ? 'Show Code' : 'Hide Code';
    }

    function load() {
        request('me').then(function (data) {
            csrfToken = data.csrfToken || csrfToken;
            if (!data.authenticated || !data.user || data.user.role !== 'owner') return;
            family = data.family || null;
            render();
        }).catch(function () { });
    }

    function copyCode(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var code = $('inviteCodeDisplay');
        var value = code ? String(code.dataset.fullCode || '') : '';
        if (!value || (family && family.inviteCodeHidden)) return status('Show the share code before copying it.');
        navigator.clipboard.writeText(value).then(function () {
            status('Share code copied.');
        }).catch(function () {
            status('Could not copy the share code.');
        });
    }

    function toggleVisibility(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!family || !family.inviteCode) return;
        request('set_invite_visibility', { hidden: !Boolean(family.inviteCodeHidden) }).then(function (data) {
            family = data.family || family;
            render();
            status(data.message || 'Share-code visibility updated.');
        }).catch(function (error) { status(error.message); });
    }

    function resetCode(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!window.confirm('Reset the active share code? The current code will stop working immediately.')) return;
        status('Resetting share code...');
        request('regenerate_invite', {}).then(function (data) {
            family = data.family || family;
            if (data.inviteCode && family) family.inviteCode = data.inviteCode;
            render();
            status(data.message || 'Share code reset.');
        }).catch(function (error) { status(error.message); });
    }

    function boot() {
        installControls();
        load();
        window.setInterval(load, 60000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
