/**
 * Project: Family GPS Tracker
 * File: assets/js/account-security.js
 * Revision: 1.6.12
 * Description: Account security, remembered devices, privacy summary, exports, guarded deletion, and status-banner behavior.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-08-16
 */
(function () {
    'use strict';

    var csrfToken = '';
    var accountData = null;

    function $(id) { return document.getElementById(id); }

    function status(text, kind) {
        var node = $('statusText');
        if (node) node.textContent = text;
        applyStatusKind(kind || classifyStatus(text));
    }

    function classifyStatus(text) {
        var value = String(text || '').toLowerCase();
        if (/failed|error|incorrect|invalid|could not|required|denied|blocked|unavailable|not found/.test(value)) return 'error';
        if (/updated|saved|prepared|complete|created|joined|switched|revoked|deleted|copied|loaded|refreshed/.test(value)) return 'success';
        if (/loading|saving|changing|checking|refreshing|updating|deleting|creating|joining|switching/.test(value)) return 'progress';
        return 'neutral';
    }

    function applyStatusKind(kind) {
        var card = $('statusCard');
        if (!card) return;
        card.classList.remove('status-success', 'status-error', 'status-progress');
        if (kind && kind !== 'neutral') card.classList.add('status-' + kind);
    }

    function watchStatusCard() {
        var node = $('statusText');
        if (!node || node.dataset.watched === 'true') return;
        node.dataset.watched = 'true';
        new MutationObserver(function () { applyStatusKind(classifyStatus(node.textContent)); })
            .observe(node, { childList: true, characterData: true, subtree: true });
        applyStatusKind(classifyStatus(node.textContent));
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
        var list = $('rememberedDeviceList');
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

    function createPrivacyCard() {
        if ($('privacyLifecycleCard')) return;
        var security = $('accountSecurityCard');
        var tracker = $('trackerApp');
        if (!security || !tracker) return;
        var card = document.createElement('section');
        card.id = 'privacyLifecycleCard';
        card.className = 'card profile-edit';
        card.innerHTML = '<div><p class="eyebrow">Privacy</p><h2>Stored Data & Account Lifecycle</h2><p class="muted">Review what this account stores, export data, or permanently delete the account. Browser background location is not guaranteed.</p></div><div id="privacySummaryGrid" class="diag-grid"><div class="diag-item"><strong>Groups</strong><span id="privacyGroupCount">—</span></div><div class="diag-item"><strong>Latest Location</strong><span id="privacyLocationStored">—</span></div><div class="diag-item"><strong>Trail Points</strong><span id="privacyTrailCount">—</span></div><div class="diag-item"><strong>Remembered Devices</strong><span id="privacyDeviceCount">—</span></div><div class="diag-item"><strong>Consent Version</strong><span id="privacyConsentVersion">—</span></div><div class="diag-item"><strong>Background Tracking</strong><span>Not guaranteed</span></div></div><div class="button-row"><button id="privacyExportMyDataBtn" type="button" class="secondary">Download My Data</button><button id="privacyExportGroupBtn" type="button" class="secondary hidden">Download Active Group</button></div><div class="danger-zone profile-edit"><h3>Delete My Account</h3><p class="muted">You cannot delete an account that still owns a group. Transfer ownership or delete those groups first. This removes your account, saved location, trail, and group memberships.</p><div id="ownedGroupWarning" class="profile-edit-note"></div><div class="settings-grid"><label>Current password<input id="deleteAccountPassword" type="password" autocomplete="current-password"></label><label>Type exact username<input id="deleteAccountConfirmation" autocomplete="off"></label></div><button id="deleteAccountBtn" type="button" class="danger-button">Permanently Delete Account</button></div>';
        security.insertAdjacentElement('afterend', card);
        $('privacyExportMyDataBtn').addEventListener('click', exportData);
        $('privacyExportGroupBtn').addEventListener('click', exportActiveGroup);
        $('deleteAccountBtn').addEventListener('click', deleteAccount);
    }

    function renderPrivacy(privacy) {
        if (!privacy) return;
        createPrivacyCard();
        $('privacyGroupCount').textContent = String(privacy.groupCount == null ? 0 : privacy.groupCount);
        $('privacyLocationStored').textContent = privacy.hasLatestLocation ? 'Stored' : 'None';
        $('privacyTrailCount').textContent = String(privacy.trailPointCount == null ? 0 : privacy.trailPointCount);
        $('privacyDeviceCount').textContent = String(privacy.rememberedDeviceCount == null ? 0 : privacy.rememberedDeviceCount);
        $('privacyConsentVersion').textContent = privacy.consentVersion || 'Legacy';
        $('deleteAccountConfirmation').placeholder = privacy.username || 'username';
        var owned = privacy.ownedGroups || [];
        $('ownedGroupWarning').textContent = owned.length ? 'Owned groups blocking deletion: ' + owned.map(function (group) { return group.name; }).join(', ') : 'No owned groups are blocking account deletion.';
        $('deleteAccountBtn').disabled = owned.length > 0;
        var info = accountInfo();
        $('privacyExportGroupBtn').classList.toggle('hidden', !info || info.role !== 'owner');
    }

    function accountInfo() {
        var text = ($('accountTitle') || {}).textContent || '';
        var match = text.match(/^(.*)\s+\(([^)]+)\)$/);
        return match ? { name: match[1].trim(), role: match[2].trim().toLowerCase() } : null;
    }

    function loadAccount() {
        return getAccount('').then(function (data) {
            accountData = data;
            renderDevices(data.devices || []);
            renderPrivacy(data.privacy || {});
            return data;
        }).catch(function (error) { status(error.message || 'Could not load account details.', 'error'); });
    }

    function revokeDevice(selector) {
        if (!selector || !window.confirm('Revoke this remembered device?')) return;
        postAccount({ action: 'revoke_device', selector: selector }).then(function (data) {
            renderDevices(data.devices || []);
            if (accountData && accountData.privacy) {
                accountData.privacy.rememberedDeviceCount = (data.devices || []).length;
                renderPrivacy(accountData.privacy);
            }
            status(data.message || 'Remembered device revoked.', 'success');
        }).catch(function (error) { status(error.message || 'Could not revoke device.', 'error'); });
    }

    function revokeAllDevices() {
        if (!window.confirm('Revoke all remembered devices for this account?')) return;
        postAccount({ action: 'revoke_all_devices' }).then(function (data) {
            renderDevices(data.devices || []);
            if (accountData && accountData.privacy) {
                accountData.privacy.rememberedDeviceCount = 0;
                renderPrivacy(accountData.privacy);
            }
            status(data.message || 'All remembered devices revoked.', 'success');
        }).catch(function (error) { status(error.message || 'Could not revoke devices.', 'error'); });
    }

    function changeSecret(event) {
        event.preventDefault();
        var current = $('currentPasswordInput');
        var next = $('newPasswordInput');
        var confirm = $('confirmPasswordInput');
        if (!current || !next || !confirm) return;
        if (next.value !== confirm.value) return status('New password and confirmation do not match.', 'error');
        if (!window.confirm('Change password and revoke remembered devices?')) return;
        status('Changing password...', 'progress');
        postAccount({ action: 'change_password', currentPassword: current.value, newPassword: next.value, confirmPassword: confirm.value })
            .then(function (data) {
                current.value = '';
                next.value = '';
                confirm.value = '';
                renderDevices(data.devices || []);
                status(data.message || 'Password changed.', 'success');
                window.dispatchEvent(new CustomEvent('familyTrackerPasswordChanged'));
                loadAccount();
            }).catch(function (error) { status(error.message || 'Password change failed.', 'error'); });
    }

    function downloadJson(filename, payload) {
        var blob = new Blob([JSON.stringify(payload || {}, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function exportData() {
        status('Preparing my data export...', 'progress');
        getAccount('?action=export_my_data').then(function (data) {
            downloadJson('family-tracker-my-data.json', data.export);
            status('My data export prepared.', 'success');
        }).catch(function (error) { status(error.message || 'Export failed.', 'error'); });
    }

    function exportActiveGroup() {
        status('Preparing active group export...', 'progress');
        fetch('owner-admin.php?action=export_group', { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Group export failed.');
                downloadJson('family-tracker-active-group.json', data.export);
                status('Active group export prepared.', 'success');
            });
        }).catch(function (error) { status(error.message || 'Group export failed.', 'error'); });
    }

    function deleteAccount() {
        var password = $('deleteAccountPassword');
        var confirmation = $('deleteAccountConfirmation');
        if (!password || !confirmation || !password.value || !confirmation.value.trim()) return status('Current password and exact username are required.', 'error');
        if (!window.confirm('Permanently delete this account and its saved location and trail? This cannot be undone.')) return;
        status('Deleting account...', 'progress');
        postAccount({ action: 'delete_account', currentPassword: password.value, confirmation: confirmation.value.trim() })
            .then(function (data) {
                status(data.message || 'Account deleted.', 'success');
                window.setTimeout(function () { window.location.href = data.redirect || 'index.php'; }, 500);
            }).catch(function (error) { status(error.message || 'Account deletion failed.', 'error'); });
    }

    function boot() {
        watchStatusCard();
        createPrivacyCard();
        var form = $('passwordChangeForm');
        var refresh = $('refreshDevicesBtn');
        var revokeAll = $('revokeAllDevicesBtn');
        var exportBtn = $('exportMyDataBtn');
        if (form) form.addEventListener('submit', changeSecret);
        if (refresh) refresh.addEventListener('click', loadAccount);
        if (revokeAll) revokeAll.addEventListener('click', revokeAllDevices);
        if (exportBtn) exportBtn.addEventListener('click', exportData);
        loadAccount();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
