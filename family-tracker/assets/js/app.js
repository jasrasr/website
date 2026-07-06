/**
 * Project: Family GPS Tracker
 * File: assets/js/app.js
 * Revision: 0.1.0
 * Description: Front-end auth, geolocation sharing, family refresh, and Leaflet rendering.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

(() => {
    'use strict';

    const state = {
        csrfToken: '',
        user: null,
        family: null,
        watchId: null,
        sharing: false,
        lastSentAt: 0,
        map: null,
        markers: new Map(),
        accuracyCircles: new Map(),
        refreshTimer: null,
    };

    const $ = (id) => document.getElementById(id);

    const els = {
        statusText: $('statusText'),
        authCard: $('authCard'),
        trackerApp: $('trackerApp'),
        loginForm: $('loginForm'),
        registerForm: $('registerForm'),
        joinForm: $('joinForm'),
        accountTitle: $('accountTitle'),
        familyTitle: $('familyTitle'),
        inviteCard: $('inviteCard'),
        inviteCodeDisplay: $('inviteCodeDisplay'),
        regenerateInviteBtn: $('regenerateInviteBtn'),
        logoutBtn: $('logoutBtn'),
        startSharingBtn: $('startSharingBtn'),
        stopSharingBtn: $('stopSharingBtn'),
        updateOnceBtn: $('updateOnceBtn'),
        refreshBtn: $('refreshBtn'),
        deleteLocationBtn: $('deleteLocationBtn'),
        accuracyValue: $('accuracyValue'),
        speedValue: $('speedValue'),
        headingValue: $('headingValue'),
        lastUpdateValue: $('lastUpdateValue'),
        memberList: $('memberList'),
    };

    function setStatus(message) {
        els.statusText.textContent = message;
    }

    function showAuth() {
        els.authCard.classList.remove('hidden');
        els.trackerApp.classList.add('hidden');
    }

    function showTracker() {
        els.authCard.classList.add('hidden');
        els.trackerApp.classList.remove('hidden');
    }

    function formToObject(form) {
        const data = new FormData(form);
        const out = {};
        for (const [key, value] of data.entries()) {
            out[key] = value;
        }
        for (const checkbox of form.querySelectorAll('input[type="checkbox"]')) {
            out[checkbox.name] = checkbox.checked;
        }
        return out;
    }

    async function api(action, payload = null) {
        const options = {
            method: payload ? 'POST' : 'GET',
            headers: {},
            credentials: 'same-origin',
        };

        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }
        if (state.csrfToken) {
            options.headers['X-CSRF-Token'] = state.csrfToken;
        }

        const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
        const data = await response.json().catch(() => ({ ok: false, error: 'Invalid API response.' }));

        if (!response.ok || !data.ok) {
            throw new Error(data.error || `API request failed: ${response.status}`);
        }

        if (data.csrfToken) {
            state.csrfToken = data.csrfToken;
        }
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

        els.accountTitle.textContent = `${state.user.displayName} (${state.user.role})`;
        els.familyTitle.textContent = state.family ? state.family.name : 'No family loaded';

        if (state.user.role === 'owner') {
            els.inviteCard.classList.remove('hidden');
            const last4 = state.family?.inviteCodeLast4 ? `Last code ended in ${state.family.inviteCodeLast4}` : 'No invite metadata yet';
            els.inviteCodeDisplay.textContent = last4;
        } else {
            els.inviteCard.classList.add('hidden');
        }

        showTracker();
        initMap();
        setStatus('Logged in. Location sharing is off until you start it.');
        startFamilyRefresh();
        refreshFamilyLocations();
    }

    async function loadMe() {
        try {
            const data = await api('me');
            applySession(data);
        } catch (error) {
            setStatus(error.message);
            showAuth();
        }
    }

    function initMap() {
        if (state.map || !window.L) return;

        state.map = L.map('map', {
            zoomControl: true,
        }).setView([41.4993, -81.6944], 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(state.map);

        setTimeout(() => state.map.invalidateSize(), 150);
    }

    function formatAge(seconds) {
        if (seconds === null || seconds === undefined) return 'unknown age';
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.round(minutes / 60);
        return `${hours}h ago`;
    }

    function metersToFeet(value) {
        return value === null || value === undefined ? null : value * 3.28084;
    }

    function mpsToMph(value) {
        return value === null || value === undefined ? null : value * 2.23694;
    }

    function updateLocalMetrics(coords) {
        const accuracyFeet = metersToFeet(coords.accuracy);
        const speedMph = mpsToMph(coords.speed);

        els.accuracyValue.textContent = Number.isFinite(accuracyFeet) ? accuracyFeet.toFixed(0) : '--';
        els.speedValue.textContent = Number.isFinite(speedMph) ? speedMph.toFixed(1) : '--';
        els.headingValue.textContent = Number.isFinite(coords.heading) ? coords.heading.toFixed(0) : '--';
        els.lastUpdateValue.textContent = new Date().toLocaleTimeString();
    }

    function buildLocationPayload(position) {
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
        updateLocalMetrics(position.coords);

        const now = Date.now();
        if (!force && now - state.lastSentAt < 10000) {
            return;
        }

        state.lastSentAt = now;
        const payload = buildLocationPayload(position);
        await api('update_location', payload);
        setStatus(`Location updated at ${new Date().toLocaleTimeString()}.`);
        await refreshFamilyLocations();
    }

    function startSharing() {
        if (!navigator.geolocation) {
            setStatus('This browser does not support geolocation.');
            return;
        }

        if (state.watchId !== null) {
            setStatus('Location sharing is already active.');
            return;
        }

        state.sharing = true;
        els.startSharingBtn.disabled = true;
        els.stopSharingBtn.disabled = false;

        state.watchId = navigator.geolocation.watchPosition(
            (position) => {
                sendLocation(position).catch((error) => setStatus(error.message));
            },
            (error) => {
                setStatus(`GPS error: ${error.message}`);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 15000,
            }
        );

        setStatus('Location sharing started. Browser permission may be required.');
    }

    function stopSharing() {
        if (state.watchId !== null) {
            navigator.geolocation.clearWatch(state.watchId);
        }
        state.watchId = null;
        state.sharing = false;
        els.startSharingBtn.disabled = false;
        els.stopSharingBtn.disabled = true;
        setStatus('Location sharing stopped. Last stored location remains visible until deleted or replaced.');
    }

    function updateOnce() {
        if (!navigator.geolocation) {
            setStatus('This browser does not support geolocation.');
            return;
        }

        setStatus('Requesting current location…');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                sendLocation(position, true).catch((error) => setStatus(error.message));
            },
            (error) => setStatus(`GPS error: ${error.message}`),
            {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 15000,
            }
        );
    }

    async function refreshFamilyLocations() {
        if (!state.user) return;
        try {
            const data = await api('family_locations');
            renderMembers(data.members || []);
            renderMap(data.members || []);
        } catch (error) {
            setStatus(error.message);
        }
    }

    function renderMembers(members) {
        if (!members.length) {
            els.memberList.textContent = 'No family members found.';
            return;
        }

        els.memberList.innerHTML = '';
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
                const age = formatAge(loc.ageSeconds);
                meta.textContent = `${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)} • ${age} • accuracy ${Number.isFinite(accuracyFeet) ? accuracyFeet.toFixed(0) + ' ft' : 'unknown'}`;
            } else {
                meta.textContent = 'No shared location yet.';
            }

            main.append(name, meta);

            const badge = document.createElement('span');
            badge.className = 'badge';
            if (!loc) {
                badge.classList.add('missing');
                badge.textContent = 'No location';
            } else if (loc.isStale) {
                badge.classList.add('stale');
                badge.textContent = 'Stale';
            } else {
                badge.textContent = 'Live-ish';
            }

            card.append(main, badge);
            els.memberList.append(card);
        }
    }

    function markerLabel(member) {
        const loc = member.location;
        if (!loc) return member.displayName || 'Unknown';
        return `<strong>${escapeHtml(member.displayName || 'Unknown')}</strong><br>${formatAge(loc.ageSeconds)}<br>${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)}`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;',
        }[char]));
    }

    function renderMap(members) {
        if (!state.map) return;

        const bounds = [];
        const activeIds = new Set();

        for (const member of members) {
            const loc = member.location;
            if (!loc || typeof loc.latitude !== 'number' || typeof loc.longitude !== 'number') {
                continue;
            }

            const userId = member.id;
            activeIds.add(userId);
            const latLng = [loc.latitude, loc.longitude];
            bounds.push(latLng);

            let marker = state.markers.get(userId);
            if (!marker) {
                marker = L.marker(latLng).addTo(state.map);
                state.markers.set(userId, marker);
            } else {
                marker.setLatLng(latLng);
            }
            marker.bindPopup(markerLabel(member));

            let circle = state.accuracyCircles.get(userId);
            if (!circle && Number.isFinite(loc.accuracy)) {
                circle = L.circle(latLng, {
                    radius: Math.max(5, loc.accuracy),
                    weight: 1,
                    fillOpacity: 0.08,
                }).addTo(state.map);
                state.accuracyCircles.set(userId, circle);
            } else if (circle) {
                circle.setLatLng(latLng);
                circle.setRadius(Math.max(5, Number.isFinite(loc.accuracy) ? loc.accuracy : 5));
            }
        }

        for (const [userId, marker] of state.markers.entries()) {
            if (!activeIds.has(userId)) {
                marker.remove();
                state.markers.delete(userId);
            }
        }
        for (const [userId, circle] of state.accuracyCircles.entries()) {
            if (!activeIds.has(userId)) {
                circle.remove();
                state.accuracyCircles.delete(userId);
            }
        }

        if (bounds.length === 1) {
            state.map.setView(bounds[0], 15);
        } else if (bounds.length > 1) {
            state.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
        }
    }

    function startFamilyRefresh() {
        if (state.refreshTimer) return;
        state.refreshTimer = window.setInterval(refreshFamilyLocations, 15000);
    }

    function wireForms() {
        els.loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                setStatus('Logging in…');
                const data = await api('login', formToObject(els.loginForm));
                applySession(data);
            } catch (error) {
                setStatus(error.message);
            }
        });

        els.registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                setStatus('Creating family tracker…');
                const data = await api('register_family', formToObject(els.registerForm));
                applySession(data);
                if (data.oneTimeInviteCode) {
                    els.inviteCodeDisplay.textContent = data.oneTimeInviteCode;
                    setStatus(`Family tracker created. Save invite code: ${data.oneTimeInviteCode}`);
                }
            } catch (error) {
                setStatus(error.message);
            }
        });

        els.joinForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                setStatus('Joining family tracker…');
                const data = await api('join_family', formToObject(els.joinForm));
                applySession(data);
            } catch (error) {
                setStatus(error.message);
            }
        });

        els.logoutBtn.addEventListener('click', async () => {
            try {
                stopSharing();
                await api('logout', {});
                state.user = null;
                state.family = null;
                setStatus('Logged out.');
                showAuth();
            } catch (error) {
                setStatus(error.message);
            }
        });

        els.startSharingBtn.addEventListener('click', startSharing);
        els.stopSharingBtn.addEventListener('click', stopSharing);
        els.updateOnceBtn.addEventListener('click', updateOnce);
        els.refreshBtn.addEventListener('click', refreshFamilyLocations);

        els.deleteLocationBtn.addEventListener('click', async () => {
            const confirmed = window.confirm('Delete your stored location and breadcrumb trail? This does not delete your account.');
            if (!confirmed) return;
            try {
                await api('delete_my_location', {});
                setStatus('Your stored location was deleted.');
                await refreshFamilyLocations();
            } catch (error) {
                setStatus(error.message);
            }
        });

        els.regenerateInviteBtn.addEventListener('click', async () => {
            try {
                const data = await api('regenerate_invite', {});
                els.inviteCodeDisplay.textContent = data.inviteCode;
                setStatus(`New invite code generated: ${data.inviteCode}`);
            } catch (error) {
                setStatus(error.message);
            }
        });
    }

    wireForms();
    loadMe();
})();
