/**
 * Project: Family GPS Tracker
 * File: assets/js/invite-management.js
 * Revision: 1.6.11
 * Description: Owner invite management, invite-aware joins, and unified per-device battery-aware GPS sharing controls.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-08-08
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'family-tracker-gps-mode';
    var DEFAULT_MODE = 'balanced';
    var previousScheduledMode = null;
    var lastActualRequestAt = 0;
    var latestRequest = null;
    var forceNextRequest = false;
    var liveWatchIds = {};
    var modeSelect = null;
    var modeSummary = null;
    var nextUpdateNode = null;

    var modes = {
        live: { label: 'Live', intervalMs: 0, highAccuracy: true, maximumAge: 0, description: 'Continuous GPS watch. Highest battery use; intended for active travel.' },
        frequent: { label: 'Frequent', intervalMs: 60000, highAccuracy: true, maximumAge: 0, description: 'Fresh high-accuracy location about every minute.' },
        balanced: { label: 'Balanced', intervalMs: 300000, highAccuracy: true, maximumAge: 60000, description: 'Recommended. Fresh location about every 5 minutes with moderate battery use.' },
        battery: { label: 'Battery Saver', intervalMs: 900000, highAccuracy: false, maximumAge: 300000, description: 'Location about every 15 minutes using lower-power positioning when available.' },
        maximum: { label: 'Maximum Saver', intervalMs: 1800000, highAccuracy: false, maximumAge: 600000, description: 'Location about every 30 minutes. Best for basic last-known-location sharing.' },
        manual: { label: 'Manual', intervalMs: Infinity, highAccuracy: false, maximumAge: 600000, description: 'Captures once on page load, then only when Update Once is pressed.' }
    };

    function getStoredMode() {
        try {
            var value = window.localStorage.getItem(STORAGE_KEY) || DEFAULT_MODE;
            return modes[value] ? value : DEFAULT_MODE;
        } catch (ignore) { return DEFAULT_MODE; }
    }

    function setStoredMode(value) {
        if (!modes[value]) value = DEFAULT_MODE;
        try { window.localStorage.setItem(STORAGE_KEY, value); } catch (ignore) { }
        if (modeSelect) modeSelect.value = value;
        renderModeStatus();
    }

    function currentMode() { return getStoredMode(); }
    function profile() { return modes[currentMode()] || modes[DEFAULT_MODE]; }

    function isDue(now) {
        var selected = profile();
        if (!lastActualRequestAt) return true;
        if (!Number.isFinite(selected.intervalMs)) return false;
        if (selected.intervalMs === 0) return false;
        return now - lastActualRequestAt >= selected.intervalMs;
    }

    function governedOptions(originalOptions) {
        var selected = profile();
        return Object.assign({}, originalOptions || {}, {
            enableHighAccuracy: selected.highAccuracy,
            maximumAge: selected.maximumAge,
            timeout: selected.highAccuracy ? 15000 : 10000
        });
    }

    function formatDuration(milliseconds) {
        if (!Number.isFinite(milliseconds)) return 'manual updates only';
        var seconds = Math.max(0, Math.ceil(milliseconds / 1000));
        if (seconds < 60) return seconds + 's';
        var minutes = Math.ceil(seconds / 60);
        if (minutes < 60) return minutes + 'm';
        var hours = Math.floor(minutes / 60);
        var remaining = minutes % 60;
        return remaining ? hours + 'h ' + remaining + 'm' : hours + 'h';
    }

    function renderModeStatus() {
        var selected = profile();
        if (modeSummary) modeSummary.textContent = selected.description;
        if (!nextUpdateNode) return;
        if (currentMode() === 'live') {
            nextUpdateNode.textContent = Object.keys(liveWatchIds).length
                ? 'Live sharing is active while this page remains available to the browser.'
                : 'Live sharing is starting…';
            return;
        }
        if (!Number.isFinite(selected.intervalMs)) {
            nextUpdateNode.textContent = 'Next automatic update: none after the initial page-load location.';
            return;
        }
        if (!lastActualRequestAt) {
            nextUpdateNode.textContent = 'Next automatic update: initial location is due now.';
            return;
        }
        var remaining = selected.intervalMs - (Date.now() - lastActualRequestAt);
        nextUpdateNode.textContent = remaining <= 0 ? 'Next automatic update: due now.' : 'Next automatic update in about ' + formatDuration(remaining) + '.';
    }

    function installGeolocationGovernor() {
        if (!navigator.geolocation || navigator.geolocation.__familyTrackerGoverned) return;
        var geo = navigator.geolocation;
        var originalGet = geo.getCurrentPosition.bind(geo);
        var originalWatch = geo.watchPosition.bind(geo);
        var originalClear = geo.clearWatch.bind(geo);

        function governedGet(success, error, options) {
            latestRequest = { success: success, error: error, options: options };
            var forced = forceNextRequest;
            forceNextRequest = false;
            var now = Date.now();
            var mode = currentMode();
            if (document.hidden && !forced) return;
            if (mode === 'live' && lastActualRequestAt && !forced) return;
            if (!forced && !isDue(now)) return;
            lastActualRequestAt = now;
            renderModeStatus();
            return originalGet(success, error, governedOptions(options));
        }

        function governedWatch(success, error, options) {
            var mode = currentMode();
            if (mode !== 'live') setStoredMode('live');
            lastActualRequestAt = Date.now();
            var id = originalWatch(success, error, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
            liveWatchIds[id] = true;
            renderModeStatus();
            return id;
        }

        function governedClear(id) {
            originalClear(id);
            if (liveWatchIds[id]) {
                delete liveWatchIds[id];
                renderModeStatus();
            }
        }

        try {
            geo.getCurrentPosition = governedGet;
            geo.watchPosition = governedWatch;
            geo.clearWatch = governedClear;
            geo.__familyTrackerGoverned = true;
        } catch (error) {
            try {
                Object.defineProperties(geo, {
                    getCurrentPosition: { configurable: true, value: governedGet },
                    watchPosition: { configurable: true, value: governedWatch },
                    clearWatch: { configurable: true, value: governedClear },
                    __familyTrackerGoverned: { configurable: true, value: true }
                });
            } catch (ignore) { }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden || !latestRequest || currentMode() === 'live' || !isDue(Date.now())) return;
            governedGet(latestRequest.success, latestRequest.error, latestRequest.options);
        });
    }

    function setLegacySharingButtonsHidden() {
        var start = document.getElementById('startSharingBtn');
        var stop = document.getElementById('stopSharingBtn');
        if (start) {
            start.hidden = true;
            start.setAttribute('aria-hidden', 'true');
            start.tabIndex = -1;
        }
        if (stop) {
            stop.hidden = true;
            stop.setAttribute('aria-hidden', 'true');
            stop.tabIndex = -1;
        }
    }

    function activateSelectedMode() {
        var mode = currentMode();
        var start = document.getElementById('startSharingBtn');
        var stop = document.getElementById('stopSharingBtn');
        if (mode === 'live') {
            if (start && !start.disabled) start.click();
        } else if (stop && !stop.disabled) {
            stop.click();
        }
        renderModeStatus();
    }

    function scheduleModeActivation() {
        [150, 500, 1200].forEach(function (delay) {
            window.setTimeout(function () {
                setLegacySharingButtonsHidden();
                activateSelectedMode();
            }, delay);
        });
    }

    function createBatteryCard() {
        if (document.getElementById('gpsBatteryModeCard')) return;
        var sharing = document.querySelector('.controls-card');
        if (!sharing) return;

        var sharingTitle = sharing.querySelector('h2');
        if (sharingTitle) sharingTitle.textContent = 'Location Sharing';
        var sharingDescription = sharing.querySelector('p.muted');
        if (sharingDescription) sharingDescription.textContent = 'Choose how often this device shares its location. A location is always requested when the page first loads.';

        setLegacySharingButtonsHidden();

        var controls = document.createElement('div');
        controls.id = 'gpsBatteryModeCard';
        controls.className = 'profile-edit';

        var eyebrow = document.createElement('p');
        eyebrow.className = 'eyebrow';
        eyebrow.textContent = 'Battery & GPS';

        var label = document.createElement('label');
        label.textContent = 'Update frequency';
        modeSelect = document.createElement('select');
        modeSelect.id = 'gpsUpdateModeSelect';
        Object.keys(modes).forEach(function (key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = modes[key].label + (key === 'balanced' ? ' — Recommended' : '');
            modeSelect.appendChild(option);
        });
        modeSelect.value = currentMode();
        label.appendChild(modeSelect);

        modeSummary = document.createElement('div');
        modeSummary.className = 'profile-edit-note';
        nextUpdateNode = document.createElement('div');
        nextUpdateNode.className = 'member-ident';

        modeSelect.addEventListener('change', function () {
            var oldMode = currentMode();
            var newMode = modeSelect.value;
            if (oldMode === newMode) return;
            setStoredMode(newMode);
            activateSelectedMode();
            var statusNode = document.getElementById('statusText');
            if (statusNode) statusNode.textContent = modes[newMode].label + ' location mode selected for this device.';
        });

        controls.append(eyebrow, label, modeSummary, nextUpdateNode);

        if (sharingDescription) sharingDescription.insertAdjacentElement('afterend', controls);
        else sharing.insertBefore(controls, sharing.firstChild);

        renderModeStatus();
        window.setInterval(renderModeStatus, 15000);
        scheduleModeActivation();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.id === 'updateOnceBtn') forceNextRequest = true;
    }, true);

    installGeolocationGovernor();

    var csrfToken = '';
    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText') || $('ownerStatus'); if (node) node.textContent = text; }
    function json(response) { return response.json().then(function (data) { if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.'); if (data.csrfToken) csrfToken = data.csrfToken; return data; }); }
    function getInvites() { return fetch('invite-admin.php', { credentials: 'same-origin' }).then(json); }
    function postInvite(payload) { return fetch('invite-admin.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify(payload) }).then(json); }
    function formatLimit(invite) { var expiry = invite.expiresAt ? new Date(invite.expiresAt).toLocaleString() : 'Never expires'; var uses = invite.maxUses > 0 ? invite.uses + ' / ' + invite.maxUses + ' uses' : invite.uses + ' uses / unlimited'; return expiry + ' • ' + uses + ' • code ends ' + (invite.last4 || '----'); }

    function renderInvites(data) {
        var list = $('ownerInviteList');
        if (!list) return;
        list.innerHTML = '';
        var invites = data.invites || [];
        if (!invites.length) { list.textContent = 'No managed invites yet. The original group invite code remains available in the tracker.'; return; }
        invites.forEach(function (invite) {
            var card = document.createElement('article'); card.className = 'member-card';
            var main = document.createElement('div');
            var title = document.createElement('div'); title.className = 'member-name'; title.textContent = invite.label || 'Group invite';
            var meta = document.createElement('div'); meta.className = 'member-meta'; meta.textContent = formatLimit(invite);
            main.append(title, meta);
            var button = document.createElement('button'); button.type = 'button'; button.className = invite.active ? 'danger-button' : 'secondary'; button.textContent = invite.active ? 'Revoke' : (invite.expired ? 'Expired' : invite.exhausted ? 'Used Up' : 'Revoked'); button.disabled = !invite.active;
            button.addEventListener('click', function () { if (!window.confirm('Revoke this invite now?')) return; postInvite({ action: 'revoke_invite', inviteId: invite.id }).then(function (result) { renderInvites(result); status(result.message || 'Invite revoked.'); }).catch(function (error) { status(error.message); }); });
            card.append(main, button); list.appendChild(card);
        });
    }

    function loadInvites() { if (!$('ownerInviteList')) return; getInvites().then(renderInvites).catch(function (error) { status(error.message); }); }
    function createInvite(event) { event.preventDefault(); var label = $('ownerInviteLabel').value.trim(); var expiry = $('ownerInviteExpiry').value; var maxUses = Number($('ownerInviteUses').value || 0); status('Creating invite...'); postInvite({ action: 'create_invite', label: label, expiry: expiry, maxUses: maxUses }).then(function (result) { renderInvites(result); $('ownerInviteLabel').value = ''; var box = $('ownerInviteCodeBox'); var code = $('ownerInviteFullCode'); if (box && code && result.oneTimeInviteCode) { code.textContent = result.oneTimeInviteCode; box.classList.remove('hidden'); } status(result.message || 'Invite created.'); }).catch(function (error) { status(error.message); }); }
    function guestJoin(event) { var form = event.target; if (!form || form.id !== 'joinForm') return; event.preventDefault(); event.stopImmediatePropagation(); var payload = { action: 'guest_join', inviteCode: form.elements.inviteCode.value, displayName: form.elements.displayName.value, username: form.elements.username.value, password: form.elements.password.value, consentAccepted: form.elements.consentAccepted.checked, rememberMe: form.elements.rememberMe.checked }; status('Joining group...'); fetch('invite-join.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(json).then(function () { window.location.reload(); }).catch(function (error) { status(error.message); }); }
    function existingJoin(event) { var target = event.target; if (!target || target.id !== 'joinGroupBtn') return; event.preventDefault(); event.stopImmediatePropagation(); var input = $('joinGroupCode'); var code = input ? input.value.trim() : ''; if (!code) return status('Invite code is required.'); status('Joining group...'); fetch('groups.php', { credentials: 'same-origin' }).then(json).then(function () { return fetch('invite-join.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'existing_join', inviteCode: code }) }).then(json); }).then(function () { window.location.reload(); }).catch(function (error) { status(error.message); }); }

    function boot() {
        createBatteryCard();
        document.addEventListener('submit', guestJoin, true);
        document.addEventListener('click', existingJoin, true);
        var form = $('ownerInviteForm'); var refresh = $('ownerInviteRefreshBtn');
        if (form) form.addEventListener('submit', createInvite);
        if (refresh) refresh.addEventListener('click', loadInvites);
        loadInvites();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
}());
