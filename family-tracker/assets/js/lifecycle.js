/**
 * Project: Family GPS Tracker
 * File: assets/js/lifecycle.js
 * Revision: 1.4.9
 * Description: Privacy lifecycle controls for account deletion and expired remembered-device cleanup.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText'); if (node) node.textContent = text; }

    function getLifecycle() {
        return fetch('lifecycle.php', { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Lifecycle request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function postLifecycle(payload) {
        return fetch('lifecycle.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) {
                    var error = new Error(data.error || 'Lifecycle request failed.');
                    error.payload = data;
                    throw error;
                }
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function renderCheck(data) {
        var node = $('accountDeletionCheck');
        if (!node) return;
        var check = data.accountDeletionAllowed || {};
        if (check.allowed) {
            node.textContent = 'Account deletion is currently allowed. Owned groups containing only you will also be removed.';
            return;
        }
        var names = (check.blockingGroups || []).map(function (group) { return group.name; }).join(', ');
        node.textContent = 'Deletion blocked until ownership is transferred for: ' + (names || 'one or more groups') + '.';
    }

    function load() {
        getLifecycle().then(function (data) {
            renderCheck(data);
            var count = Number(data.expiredDeviceCount || 0);
            var button = $('cleanupExpiredDevicesBtn');
            if (button) button.textContent = 'Clean Expired Device Records (' + count + ')';
        }).catch(function () { });
    }

    function cleanup() {
        status('Cleaning expired remembered-device records...');
        postLifecycle({ action: 'cleanup_expired_devices' }).then(function (data) {
            status(data.message || 'Expired records cleaned.');
            load();
        }).catch(function (error) { status(error.message || 'Cleanup failed.'); });
    }

    function deleteAccount(event) {
        event.preventDefault();
        var password = $('deleteAccountPassword');
        var confirmation = $('deleteAccountConfirmation');
        if (!password || !confirmation) return;
        if (confirmation.value.trim() !== 'DELETE MY ACCOUNT') {
            return status('Type DELETE MY ACCOUNT exactly to confirm.');
        }
        if (!window.confirm('Permanently delete your account, personal location, trail, remembered devices, and eligible single-member groups?')) return;
        status('Deleting account...');
        postLifecycle({ action: 'delete_account', password: password.value, confirmation: confirmation.value }).then(function (data) {
            status(data.message || 'Account deleted.');
            window.setTimeout(function () { window.location.href = 'index.php'; }, 900);
        }).catch(function (error) {
            var payload = error.payload || {};
            if (payload.blockingGroups && payload.blockingGroups.length) {
                status('Deletion blocked. Transfer ownership for: ' + payload.blockingGroups.map(function (g) { return g.name; }).join(', ') + '.');
            } else {
                status(error.message || 'Account deletion failed.');
            }
            load();
        });
    }

    function boot() {
        var cleanupButton = $('cleanupExpiredDevicesBtn');
        var form = $('deleteAccountForm');
        if (cleanupButton) cleanupButton.addEventListener('click', cleanup);
        if (form) form.addEventListener('submit', deleteAccount);
        load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
