/**
 * Project: Family GPS Tracker
 * File: assets/js/account-settings.js
 * Revision: 1.6.3
 * Description: Account profile settings, group rename, member-location display preferences, and diagnostics panel behavior.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-14
 */
(function () {
    'use strict';

    var csrfToken = '';
    var currentGroups = [];
    var currentUser = null;

    function setStatus(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function account() {
        if (currentUser) return { name: currentUser.displayName || currentUser.username || 'Account', role: currentUser.role || 'member' };
        var text = (document.getElementById('accountTitle') || {}).textContent || '';
        var open = text.lastIndexOf('(');
        var close = text.lastIndexOf(')');
        if (open < 1 || close < open) return null;
        return { name: text.slice(0, open).trim(), role: text.slice(open + 1, close).trim().toLowerCase() };
    }

    function activeGroup() {
        for (var i = 0; i < currentGroups.length; i++) {
            if (currentGroups[i].isActive) return currentGroups[i];
        }
        return currentGroups[0] || null;
    }

    function apiGet(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function apiPost(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function loadGroups() {
        return apiGet('groups.php').then(function (data) {
            currentGroups = data.groups || [];
            hydrateGroupForm();
            updateDiagnosticsValues('OK');
            return data;
        });
    }

    function hydrateProfile(data) {
        if (data && data.user) currentUser = data.user;
        var info = account();
        var displayName = document.getElementById('displayNameInput');
        var nickname = document.getElementById('nicknameInput');
        var avatarMode = document.getElementById('avatarModeSelect');
        var avatarUrl = document.getElementById('avatarUrlInput');
        var title = document.getElementById('accountTitle');
        var familyTitle = document.getElementById('familyTitle');
        var profile = (currentUser && currentUser.profile) || {};
        if (displayName && currentUser) displayName.value = currentUser.displayName || '';
        else if (displayName && info && !displayName.value) displayName.value = info.name;
        if (nickname) nickname.value = profile.nickname || '';
        if (avatarMode) avatarMode.value = profile.avatarMode || 'generated';
        if (avatarUrl) avatarUrl.value = profile.avatarUrl || '';
        if (title && info) title.textContent = info.name + ' (' + info.role + ')';
        if (familyTitle && data && data.family) familyTitle.textContent = data.family.name || 'Active group';
    }

    function hydrateDisplayNameForm() {
        apiGet('profile.php').then(hydrateProfile).catch(function () {
            hydrateProfile(null);
        });
    }

    function hydrateGroupForm() {
        var group = activeGroup();
        var form = document.getElementById('groupNameForm');
        var input = document.getElementById('groupNameInput');
        if (!form || !input || !group) return;
        input.value = group.name || '';
        if (group.role === 'owner') form.classList.remove('hidden');
        else form.classList.add('hidden');
    }

    function saveDisplayName(event) {
        event.preventDefault();
        var input = document.getElementById('displayNameInput');
        var nickname = document.getElementById('nicknameInput');
        var avatarMode = document.getElementById('avatarModeSelect');
        var avatarUrl = document.getElementById('avatarUrlInput');
        if (!input) return;
        var value = input.value.trim();
        if (!value) return setStatus('Display name is required.');
        setStatus('Saving profile...');
        apiPost('profile.php', {
            displayName: value,
            nickname: nickname ? nickname.value.trim() : '',
            avatarMode: avatarMode ? avatarMode.value : 'generated',
            avatarUrl: avatarUrl ? avatarUrl.value.trim() : ''
        }).then(function (data) {
            hydrateProfile(data);
            var refresh = document.getElementById('refreshBtn');
            if (refresh) refresh.click();
            window.dispatchEvent(new CustomEvent('familyTrackerMemberUiRefresh'));
            setStatus('Profile updated.');
        }).catch(function (error) {
            setStatus(error.message || 'Profile update failed.');
        });
    }

    function saveGroupName(event) {
        event.preventDefault();
        var group = activeGroup();
        var input = document.getElementById('groupNameInput');
        if (!group || !input) return;
        var value = input.value.trim();
        if (!value) return setStatus('Group name is required.');
        setStatus('Saving group name...');
        apiPost('groups.php', { action: 'rename_group', groupId: group.id, groupName: value }).then(function (data) {
            currentGroups = data.groups || [];
            hydrateGroupForm();
            var familyTitle = document.getElementById('familyTitle');
            var updated = activeGroup();
            if (familyTitle && updated) familyTitle.textContent = updated.name + ' • ' + updated.role;
            var select = document.getElementById('activeGroupSelect');
            if (select && updated) {
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === updated.id) select.options[i].textContent = updated.name + ' (' + updated.role + ')';
                }
            }
            setStatus('Group name updated.');
        }).catch(function (error) {
            setStatus(error.message || 'Group name update failed.');
        });
    }

    function locationFormat() {
        try { return window.localStorage.getItem('family-tracker-location-format') || 'city'; }
        catch (ignore) { return 'city'; }
    }

    function setLocationFormat(value) {
        try { window.localStorage.setItem('family-tracker-location-format', value); } catch (ignore) { }
        window.dispatchEvent(new CustomEvent('familyTrackerLocationFormatChanged', { detail: { format: value, clearCache: false } }));
    }

    function refreshLocationLabels() {
        window.dispatchEvent(new CustomEvent('familyTrackerLocationFormatChanged', { detail: { format: locationFormat(), clearCache: true } }));
        setStatus('Refreshing closest-city labels.');
    }

    function wireLocationControls() {
        var select = document.getElementById('locationFormatSelect');
        var refresh = document.getElementById('refreshLocationLabelsBtn');
        if (select) {
            select.value = locationFormat();
            select.addEventListener('change', function () { setLocationFormat(select.value); });
        }
        if (refresh) refresh.addEventListener('click', refreshLocationLabels);
    }

    function updateDiagnosticsValues(apiStatus) {
        var info = account();
        var group = activeGroup();
        var online = document.getElementById('diagOnline');
        var api = document.getElementById('diagApi');
        var user = document.getElementById('diagUser');
        var active = document.getElementById('diagGroup');
        if (online) online.textContent = navigator.onLine ? 'Online' : 'Offline';
        if (api) api.textContent = apiStatus || 'Unknown';
        if (user) user.textContent = info ? info.name + ' / ' + info.role : 'Unknown';
        if (active) active.textContent = group ? group.name + ' / ' + group.role : 'Unknown';
    }

    function refreshDiagnostics() {
        var started = Date.now();
        var permissionNode = document.getElementById('diagPermission');
        if (permissionNode) permissionNode.textContent = 'Checking...';

        var permissionPromise = Promise.resolve('Unsupported');
        if (navigator.permissions && navigator.permissions.query) {
            permissionPromise = navigator.permissions.query({ name: 'geolocation' }).then(function (result) { return result.state; }).catch(function () { return 'Unavailable'; });
        }

        permissionPromise.then(function (permission) {
            if (permissionNode) permissionNode.textContent = permission;
        });

        apiGet('groups.php').then(function (data) {
            currentGroups = data.groups || [];
            hydrateGroupForm();
            updateDiagnosticsValues((Date.now() - started) + ' ms');
        }).catch(function (error) {
            updateDiagnosticsValues(error.message || 'Failed');
        });
    }

    function boot() {
        hydrateDisplayNameForm();
        wireLocationControls();
        var displayForm = document.getElementById('displayNameForm');
        var groupForm = document.getElementById('groupNameForm');
        var diagBtn = document.getElementById('refreshDiagnosticsBtn');
        if (displayForm) displayForm.addEventListener('submit', saveDisplayName);
        if (groupForm) groupForm.addEventListener('submit', saveGroupName);
        if (diagBtn) diagBtn.addEventListener('click', refreshDiagnostics);
        loadGroups().catch(function () { updateDiagnosticsValues('Not signed in'); });
        refreshDiagnostics();
        window.addEventListener('online', refreshDiagnostics);
        window.addEventListener('offline', refreshDiagnostics);
        window.setInterval(function () {
            hydrateDisplayNameForm();
            loadGroups().catch(function () { });
        }, 30000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
