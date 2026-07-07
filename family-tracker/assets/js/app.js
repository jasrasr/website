/**
 * Project: Family GPS Tracker
 * File: assets/js/app.js
 * Revision: 1.3.0
 * Description: Front-end auth, invite-code copy, server notices, persistent-login GPS updates, family refresh, and Leaflet rendering.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */
(() => {
    'use strict';

    const AUTO_LOCATION_INTERVAL_MS = 60000;

    const state = {
        csrfToken: '',
        user: null,
        family: null,
        watchId: null,
        autoLocationTimer: null,
        lastSentAt: 0,
        map: null,
        markers: new Map(),
        circles: new Map(),
        refreshTimer: null,
        notices: [],
    };

    const $ = (id) => document.getElementById(id);
    const els = {
        status: $('statusText'),
        auth: $('authCard'),
        app: $('trackerApp'),
        login: $('loginForm'),
        register: $('registerForm'),
        join: $('joinForm'),
        account: $('accountTitle'),
        family: $('familyTitle'),
        invite: $('inviteCard'),
        inviteCode: $('inviteCodeDisplay'),
        copyInvite: $('copyInviteBtn'),
        regenerateInvite: $('regenerateInviteBtn'),
        noticeCard: $('familyNoticeCard'),
        notices: $('familyNoticeList'),
        logout: $('logoutBtn'),
        start: $('startSharingBtn'),
        stop: $('stopSharingBtn'),
        updateOnce: $('updateOnceBtn'),
        refresh: $('refreshBtn'),
        deleteLocation: $('deleteLocationBtn'),
        accuracy: $('accuracyValue'),
        speed: $('speedValue'),
        heading: $('headingValue'),
        lastUpdate: $('lastUpdateValue'),
        members: $('memberList'),
    };

    function setStatus(message) {
        els.status.textContent = message;
    }

    function showAuth() {
        stopAutoLocationUpdates();
        els.auth.classList.remove('hidden');
        els.app.classList.add('hidden');
    }

    function showApp() {
        els.auth.classList.add('hidden');
        els.app.classList.remove('hidden');
    }

    function formToObject(form) {
        const data = new FormData(form);
        const out = {};
        for (const [key, value] of data.entries()) out[key] = value;
        for (const checkbox of form.querySelectorAll('input[type="checkbox"]')) out[checkbox.name] = checkbox.checked;
        return out;
    }

    async function api(action, payload = null) {
        const options = { method: payload ? 'POST' : 'GET', headers: {}, credentials: 'same-origin' };
        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }
        if (state.csrfToken) options.headers['X-CSRF-Token'] = state.csrfToken;
        const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
        const data = await response.json().catch(() => ({ ok: false, error: 'Invalid API response.' }));
        if (!response.ok || !data.ok) throw new Error(data.error || `API request failed: ${response.status}`);
        if (data.csrfToken) state.csrfToken = data.csrfToken;
        return data;
    }

    async function noticeApi(payload = null) {
        const options = { method: payload ? 'POST' : 'GET', headers: {}, credentials: 'same-origin' };
        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.headers['X-CSRF-Token'] = state.csrfToken;
            options.body = JSON.stringify(payload);
        }
        const response = await fetch('notices.php', options);
        const data = await response.json().catch(() => ({ ok: false, error: 'Invalid notice response.' }));
        if (!response.ok || !data.ok) throw new Error(data.error || `Notice request failed: ${response.status}`);
        return data;
    }

    function applySession(data) {
        state.csrfToken = data.csrfToken || state.csrfToken;
        state.user = data.user || null;
        state.family = data.family || null;

        if (!data.authenticated) {
            setStatus('Not logged in. Create a family, join one, or login.');
            showAuth();
            return;
        }

        els.account.textContent = `${state.user.displayName} (${state.user.role})`;
        els.family.textContent = state.family ? state.family.name : 'No family loaded';

        if (state.user.role === 'owner') {
            els.invite.classList.remove('hidden');
            els.inviteCode.textContent = state.family?.inviteCodeLast4 ? `Last code ended in ${state.family.inviteCodeLast4}` : 'No invite metadata yet';
        } else {
            els.invite.classList.add('hidden');
        }

        showApp();
        initMap();
        startFamilyRefresh();
        refreshFamilyLocations();
        startAutoLocationUpdates();
        setStatus('Logged in. Automatic location updates run about every minute while this page is open.');
    }

    async function loadMe() {
        try { applySession(await api('me')); } catch (error) { setStatus(error.message); showAuth(); }
    }

    function initMap() {
        if (state.map || !window.L) return;
        state.map = L.map('map', { zoomControl: true }).setView([41.4993, -81.6944], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(state.map);
        setTimeout(() => state.map.invalidateSize(), 150);
    }

    function formatAge(seconds) {
        if (seconds === null || seconds === undefined) return 'unknown age';
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        return `${Math.round(minutes / 60)}h ago`;
    }

    function metersToFeet(value) { return value === null || value === undefined ? null : value * 3.28084; }
    function mpsToMph(value) { return value === null || value === undefined ? null : value * 2.23694; }

    function updateMetrics(coords) {
        const accuracyFeet = metersToFeet(coords.accuracy);
        const speedMph = mpsToMph(coords.speed);
        els.accuracy.textContent = Number.isFinite(accuracyFeet) ? accuracyFeet.toFixed(0) : '--';
        els.speed.textContent = Number.isFinite(speedMph) ? speedMph.toFixed(1) : '--';
        els.heading.textContent = Number.isFinite(coords.heading) ? coords.heading.toFixed(0) : '--';
        els.lastUpdate.textContent = new Date().toLocaleTimeString();
    }

    function locationPayload(position) {
        const coords = position.coords;
        return {
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy ?? null,
            speedMps: coords.speed ?? null,
            heading: coords.heading ?? null,
            altitude: coords.altitude ?? null,
            clientTime: new Date(position.timestamp || Date.now()).toISOString(),
        };
    }

    async function sendLocation(position, force = false) {
        updateMetrics(position.coords);
        const now = Date.now();
        if (!force && now - state.lastSentAt < 10000) return;
        state.lastSentAt = now;
        await api('update_location', locationPayload(position));
        setStatus(`Location updated at ${new Date().toLocaleTimeString()}.`);
        await refreshFamilyLocations();
    }

    function requestAutoLocation(reason = 'auto') {
        if (!state.user) return;
        if (!navigator.geolocation) {
            if (reason === 'login') setStatus('This browser does not support geolocation.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => sendLocation(position, true).catch((error) => setStatus(error.message)),
            (error) => {
                if (reason === 'login') {
                    setStatus(`GPS permission is needed for automatic location updates: ${error.message}`);
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );
    }

    function startAutoLocationUpdates() {
        stopAutoLocationUpdates();
        requestAutoLocation('login');
        state.autoLocationTimer = window.setInterval(() => requestAutoLocation('interval'), AUTO_LOCATION_INTERVAL_MS);
    }

    function stopAutoLocationUpdates() {
        if (state.autoLocationTimer) {
            window.clearInterval(state.autoLocationTimer);
            state.autoLocationTimer = null;
        }
    }

    function startSharing() {
        if (!navigator.geolocation) return setStatus('This browser does not support geolocation.');
        if (state.watchId !== null) return setStatus('Continuous GPS watch is already active.');
        els.start.disabled = true;
        els.stop.disabled = false;
        state.watchId = navigator.geolocation.watchPosition(
            (position) => sendLocation(position).catch((error) => setStatus(error.message)),
            (error) => setStatus(`GPS error: ${error.message}`),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );
        setStatus('Continuous GPS watch started. Automatic logged-in updates also remain active.');
    }

    function stopSharing() {
        if (state.watchId !== null) navigator.geolocation.clearWatch(state.watchId);
        state.watchId = null;
        els.start.disabled = false;
        els.stop.disabled = true;
        setStatus('Continuous GPS watch stopped. Automatic logged-in updates still run while this page is open.');
    }

    function updateOnce() {
        if (!navigator.geolocation) return setStatus('This browser does not support geolocation.');
        setStatus('Requesting current location…');
        navigator.geolocation.getCurrentPosition(
            (position) => sendLocation(position, true).catch((error) => setStatus(error.message)),
            (error) => setStatus(`GPS error: ${error.message}`),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );
    }

    async function refreshFamilyNotices() {
        if (!state.user) return;
        try {
            const data = await noticeApi();
            state.notices = data.notices || [];
            renderNotices();
        } catch (error) {
            setStatus(error.message);
        }
    }

    function renderNotices() {
        if (!els.noticeCard || !els.notices) return;
        if (!state.notices.length) {
            els.noticeCard.classList.add('hidden');
            els.notices.textContent = 'No new notices.';
            return;
        }
        els.noticeCard.classList.remove('hidden');
        els.notices.innerHTML = '';
        for (const notice of state.notices) {
            const card = document.createElement('article');
            card.className = 'member-card';
            const main = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'member-name';
            name.textContent = notice.message || 'Family notice';
            const meta = document.createElement('div');
            meta.className = 'member-meta';
            meta.textContent = notice.createdAt ? new Date(notice.createdAt).toLocaleString() : 'New notice';
            main.append(name, meta);
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'secondary';
            dismiss.textContent = 'Dismiss';
            dismiss.addEventListener('click', () => dismissNotice(notice.id));
            card.append(main, dismiss);
            els.notices.append(card);
        }
    }

    async function dismissNotice(noticeId) {
        try {
            await noticeApi({ noticeId });
            state.notices = state.notices.filter((notice) => notice.id !== noticeId);
            renderNotices();
            setStatus('Notice dismissed.');
        } catch (error) {
            setStatus(error.message);
        }
    }

    async function refreshFamilyLocations() {
        if (!state.user) return;
        try {
            const data = await api('family_locations');
            const members = data.members || [];
            renderMembers(members);
            renderMap(members);
            await refreshFamilyNotices();
        } catch (error) {
            setStatus(error.message);
        }
    }

    function renderMembers(members) {
        if (!members.length) {
            els.members.textContent = 'No family members found.';
            return;
        }
        els.members.innerHTML = '';
        for (const member of members) {
            const card = document.createElement('article');
            card.className = 'member-card';
            const main = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'member-name';
            name.textContent = member.displayName || member.username || 'Unknown';
            const meta = document.createElement('div');
            meta.className = 'member-meta';
            const loc = member.location;
            if (loc) {
                const accuracyFeet = metersToFeet(loc.accuracy);
                meta.textContent = `${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)} • ${formatAge(loc.ageSeconds)} • accuracy ${Number.isFinite(accuracyFeet) ? accuracyFeet.toFixed(0) + ' ft' : 'unknown'}`;
            } else {
                meta.textContent = 'No shared location yet.';
            }
            main.append(name, meta);
            const badge = document.createElement('span');
            badge.className = 'badge';
            if (!loc) { badge.classList.add('missing'); badge.textContent = 'No location'; }
            else if (loc.isStale) { badge.classList.add('stale'); badge.textContent = 'Stale'; }
            else { badge.textContent = 'Live-ish'; }
            card.append(main, badge);
            els.members.append(card);
        }
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    }

    function markerLabel(member) {
        const loc = member.location;
        if (!loc) return escapeHtml(member.displayName || 'Unknown');
        return `<strong>${escapeHtml(member.displayName || 'Unknown')}</strong><br>${formatAge(loc.ageSeconds)}<br>${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)}`;
    }

    function renderMap(members) {
        if (!state.map) return;
        const bounds = [];
        const activeIds = new Set();
        for (const member of members) {
            const loc = member.location;
            if (!loc || typeof loc.latitude !== 'number' || typeof loc.longitude !== 'number') continue;
            const id = member.id;
            const latLng = [loc.latitude, loc.longitude];
            activeIds.add(id);
            bounds.push(latLng);
            let marker = state.markers.get(id);
            if (!marker) { marker = L.marker(latLng).addTo(state.map); state.markers.set(id, marker); }
            else { marker.setLatLng(latLng); }
            marker.bindPopup(markerLabel(member));
            let circle = state.circles.get(id);
            if (!circle && Number.isFinite(loc.accuracy)) {
                circle = L.circle(latLng, { radius: Math.max(5, loc.accuracy), weight: 1, fillOpacity: 0.08 }).addTo(state.map);
                state.circles.set(id, circle);
            } else if (circle) {
                circle.setLatLng(latLng);
                circle.setRadius(Math.max(5, Number.isFinite(loc.accuracy) ? loc.accuracy : 5));
            }
        }
        for (const [id, marker] of state.markers.entries()) {
            if (!activeIds.has(id)) { marker.remove(); state.markers.delete(id); }
        }
        for (const [id, circle] of state.circles.entries()) {
            if (!activeIds.has(id)) { circle.remove(); state.circles.delete(id); }
        }
        if (bounds.length === 1) state.map.setView(bounds[0], 15);
        if (bounds.length > 1) state.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }

    function startFamilyRefresh() {
        if (!state.refreshTimer) state.refreshTimer = window.setInterval(refreshFamilyLocations, 15000);
    }

    function currentInviteCode() {
        const text = els.inviteCode?.textContent || '';
        const match = text.match(/[A-Z0-9]{5}-[A-Z0-9]{5}/);
        return match ? match[0] : '';
    }

    async function copyInviteCode() {
        const code = currentInviteCode();
        if (!code) return setStatus('Full invite code is not visible. Regenerate a code first, then copy it.');
        try {
            await navigator.clipboard.writeText(code);
            setStatus(`Copied invite code: ${code}`);
        } catch {
            setStatus(`Copy failed. Manually copy this invite code: ${code}`);
        }
    }

    async function regenerateInviteCode() {
        if (!window.confirm('Warning: regenerating the invite code invalidates the current invite code. Continue?')) return;
        if (!window.confirm('Second warning: anyone with the old code will no longer be able to join. Generate a new code anyway?')) return;
        try {
            const data = await api('regenerate_invite', {});
            els.inviteCode.textContent = data.inviteCode;
            setStatus(`New invite code generated: ${data.inviteCode}`);
        } catch (error) {
            setStatus(error.message);
        }
    }

    function wireForms() {
        els.login.addEventListener('submit', async (event) => {
            event.preventDefault();
            try { applySession(await api('login', formToObject(els.login))); } catch (error) { setStatus(error.message); }
        });
        els.register.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const data = await api('register_family', formToObject(els.register));
                applySession(data);
                if (data.oneTimeInviteCode) {
                    els.inviteCode.textContent = data.oneTimeInviteCode;
                    setStatus(`Family tracker created. Save invite code: ${data.oneTimeInviteCode}`);
                }
            } catch (error) { setStatus(error.message); }
        });
        els.join.addEventListener('submit', async (event) => {
            event.preventDefault();
            try { applySession(await api('join_family', formToObject(els.join))); } catch (error) { setStatus(error.message); }
        });
        els.logout.addEventListener('click', async () => {
            try {
                stopSharing();
                stopAutoLocationUpdates();
                await api('logout', {});
                state.user = null;
                state.family = null;
                state.notices = [];
                renderNotices();
                setStatus('Logged out.');
                showAuth();
            } catch (error) { setStatus(error.message); }
        });
        els.start.addEventListener('click', startSharing);
        els.stop.addEventListener('click', stopSharing);
        els.updateOnce.addEventListener('click', updateOnce);
        els.refresh.addEventListener('click', refreshFamilyLocations);
        els.copyInvite?.addEventListener('click', copyInviteCode);
        els.deleteLocation.addEventListener('click', async () => {
            if (!window.confirm('Delete your stored location and breadcrumb trail? This does not delete your account.')) return;
            try { await api('delete_my_location', {}); setStatus('Your stored location was deleted.'); await refreshFamilyLocations(); } catch (error) { setStatus(error.message); }
        });
        els.regenerateInvite.addEventListener('click', regenerateInviteCode);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && state.user) requestAutoLocation('visible');
        });
    }

    wireForms();
    loadMe();
})();
