/**
 * Project: Family GPS Tracker
 * File: assets/js/account-settings.js
 * Revision: 1.4.2
 * Description: Account settings, group rename, member-location display preferences, and diagnostics panel behavior.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var csrfToken = '';
    var currentGroups = [];

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

    function hydrateDisplayNameForm() {
        var info = account();
        var input = document.getElementById('displayNameInput');
        if (info && input && !input.value) input.value = info.name;
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
        var info = account();
        if (!input || !info) return;
        var value = input.value.trim();
        if (!value) return setStatus('Display name is required.');
        setStatus('Saving display name...');
        apiGet('profile.php').then(function () {
            return apiPost('profile.php', { displayName: value });
        }).then(function () {
            var title = document.getElementById('accountTitle');
            if (title) title.textContent = value + ' (' + info.role + ')';
            var refresh = document.getElementById('refreshBtn');
            if (refresh) refresh.click();
            window.dispatchEvent(new CustomEvent('familyTrackerMemberUiRefresh'));
            setStatus('Display name updated.');
        }).catch(function (error) {
            setStatus(error.message || 'Display name update failed.');
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
