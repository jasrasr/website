/**
 * Project: Family GPS Tracker
 * File: assets/js/membership-lifecycle.js
 * Revision: 1.5.2
 * Description: Adds a safe leave-active-group card for non-owner members.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';

    function status(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function request(payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('member-management.php', options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Membership request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function ensureCard() {
        var existing = document.getElementById('leaveGroupCard');
        if (existing) return existing;
        var groupsCard = document.getElementById('groupsCard');
        var accountCard = document.querySelector('.account-card');
        var anchor = groupsCard || accountCard;
        if (!anchor) return null;
        var card = document.createElement('section');
        card.id = 'leaveGroupCard';
        card.className = 'card profile-edit hidden';
        card.innerHTML = '<div><p class="eyebrow">Membership</p><h2>Leave Active Group</h2><p class="muted">Leaving removes your membership, check-in, trip, and group-profile data. Your account remains available for your other groups.</p></div><button id="leaveGroupLifecycleBtn" type="button" class="danger-button">Leave Active Group</button>';
        anchor.insertAdjacentElement('afterend', card);
        document.getElementById('leaveGroupLifecycleBtn').addEventListener('click', leaveGroup);
        return card;
    }

    function render(data) {
        var card = ensureCard();
        if (!card) return;
        if (data.canLeave) card.classList.remove('hidden');
        else card.classList.add('hidden');
    }

    function load() {
        request(null).then(render).catch(function () { });
    }

    function leaveGroup() {
        if (!window.confirm('Leave the active group? You will need a new invite to rejoin.')) return;
        status('Leaving active group...');
        request({ action: 'leave_group' }).then(function (data) {
            status(data.message || 'You left the group.');
            window.setTimeout(function () { window.location.reload(); }, 500);
        }).catch(function (error) {
            status(error.message || 'Could not leave the group.');
        });
    }

    function boot() {
        ensureCard();
        load();
        window.setInterval(load, 60000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
