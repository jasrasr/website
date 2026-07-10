/**
 * Project: Family GPS Tracker
 * File: assets/js/account-security.js
 * Revision: 1.4.3
 * Description: Client behavior for account access controls, remembered devices, and data export.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var csrfToken = '';

    function status(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function getAccount(query) {
        return fetch('account.php' + (query || ''), { credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.ok) throw new Error(data.error || 'Account request failed.');
                    if (data.csrfToken) csrfToken = data.csrfToken;
                    return data;
                });
            });
    }

    function postAccount(payload) {
        return fetch('account.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Account request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function renderDevices(devices) {
        var list = document.getElementById('rememberedDeviceList');
        if (!list) return;
        list.innerHTML = '';
        if (!devices || !devices.length) {
            list.textContent = 'No remembered devices. Current browser session remains active until logout.';
            return;
        }
        devices.forEach(function (device) {
            var card = document.createElement('article');
            card.className = 'member-card';
            var main = document.createElement('div');
            var title = document.createElement('div');
            title.className = 'member-name';
            title.textContent = device.current ? 'This device' : 'Remembered device';
            var meta = document.createElement('div');
            meta.className = 'member-meta';
            meta.textContent = 'Last used: ' + (device.lastUsedAt || 'unknown') + ' • Expires: ' + (device.expiresAt || 'unknown');
            main.append(title, meta);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = device.current ? 'danger-button' : 'secondary';
            button.textContent = 'Revoke';
            button.addEventListener('click', function () { revokeDevice(device.selector); });
            card.append(main, button);
            list.append(card);
        });
    }

    function loadDevices() {
        getAccount('').then(function (data) { renderDevices(data.devices || []); })
            .catch(function (error) { status(error.message || 'Could not load remembered devices.'); });
    }

    function revokeDevice(selector) {
        if (!selector || !window.confirm('Revoke this remembered device?')) return;
        postAccount({ action: 'revoke_device', selector: selector }).then(function (data) {
            renderDevices(data.devices || []);
            status(data.message || 'Remembered device revoked.');
        }).catch(function (error) { status(error.message || 'Could not revoke device.'); });
    }

    function revokeAllDevices() {
        if (!window.confirm('Revoke all remembered devices for this account?')) return;
        postAccount({ action: 'revoke_all_devices' }).then(function (data) {
            renderDevices(data.devices || []);
            status(data.message || 'All remembered devices revoked.');
        }).catch(function (error) { status(error.message || 'Could not revoke devices.'); });
    }

    function changeSecret(event) {
        event.preventDefault();
        var current = document.getElementById('currentPasswordInput');
        var next = document.getElementById('newPasswordInput');
        var confirm = document.getElementById('confirmPasswordInput');
        if (!current || !next || !confirm) return;
        if (next.value !== confirm.value) return status('New password and confirmation do not match.');
        if (!window.confirm('Change password and revoke remembered devices?')) return;
        postAccount({ action: 'change_password', currentPassword: current.value, newPassword: next.value, confirmPassword: confirm.value })
            .then(function (data) {
                current.value = '';
                next.value = '';
                confirm.value = '';
                renderDevices(data.devices || []);
                status(data.message || 'Password changed.');
            }).catch(function (error) { status(error.message || 'Password change failed.'); });
    }

    function exportData() {
        getAccount('?action=export_my_data').then(function (data) {
            var blob = new Blob([JSON.stringify(data.export || {}, null, 2)], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'family-tracker-my-data.json';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            status('My data export prepared.');
        }).catch(function (error) { status(error.message || 'Export failed.'); });
    }

    function boot() {
        var form = document.getElementById('passwordChangeForm');
        var refresh = document.getElementById('refreshDevicesBtn');
        var revokeAll = document.getElementById('revokeAllDevicesBtn');
        var exportBtn = document.getElementById('exportMyDataBtn');
        if (form) form.addEventListener('submit', changeSecret);
        if (refresh) refresh.addEventListener('click', loadDevices);
        if (revokeAll) revokeAll.addEventListener('click', revokeAllDevices);
        if (exportBtn) exportBtn.addEventListener('click', exportData);
        loadDevices();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
