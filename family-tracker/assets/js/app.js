/**
 * Project: Family GPS Tracker
 * File: assets/js/app.js
 * Revision: 1.3.4
 * Description: Front-end auth, invite-code copy, update notices, server notices, persistent-login GPS updates, mobile map fallback, family refresh, and Leaflet desktop rendering.
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
        tileLayer: null,
        mobileMap: false,
        mapResizeObserver: null,
        markers: new Map(),
        circles: new Map(),
        refreshTimer: null,
        notices: [],
    };

    const $ = (id) => document.getElementById(id);
    const els = {
        status: $('statusText'),
        statusCard: $('statusCard'),
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

    function installMapLayoutFix() {
        if (document.getElementById('family-tracker-map-layout-style')) return;
        const style = document.createElement('style');
        style.id = 'family-tracker-map-layout-style';
        style.textContent = [
            '#map img.leaflet-tile,#map img.leaflet-marker-icon,#map img.leaflet-marker-shadow{max-width:none;max-height:none;}',
            '.mobile-map-iframe{width:100%;height:100%;border:0;display:block;background:#1f2937;}',
            '.mobile-map-empty{height:100%;display:grid;place-items:center;text-align:center;padding:1rem;color:var(--muted);}',
            '.member-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.55rem;}',
            '.member-actions a{border:1px solid var(--border);border-radius:999px;color:var(--text);padding:.38rem .65rem;text-decoration:none;font-weight:800;font-size:.82rem;background:rgba(255,255,255,.06);}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function applyMapImageLayout() {
        const map = $('map');
        if (!map) return;
        for (const image of map.getElementsByTagName('img')) {
            image.style.maxWidth = 'none';
            image.style.maxHeight = 'none';
        }
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

    function appRevision() {
        const shell = document.querySelector('.app-shell');
        const fromData = shell?.getAttribute('data-app-revision');
        if (fromData) return fromData;
        const eyebrow = document.querySelector('.eyebrow')?.textContent || '';
        const match = eyebrow.match(/Rev\s+([0-9]+\.[0-9]+\.[0-9]+)/i);
        return match ? match[1] : 'unknown';
    }

    function showUpdateNoticeIfNeeded() {
        const revision = appRevision();
        const storageKey = 'family-tracker-dismissed-update-revision';
        if (window.localStorage.getItem(storageKey) === revision) return;
        if (document.getElementById('appUpdateNotice')) return;

        const card = document.createElement('section');
        card.id = 'appUpdateNotice';
        card.className = 'card';
        card.setAttribute('aria-live', 'polite');
        card.style.display = 'flex';
        card.style.alignItems = 'center';
        card.style.justifyContent = 'space-between';
        card.style.gap = '0.75rem';
        card.style.padding = '0.75rem 1rem';

        const text = document.createElement('div');
        const strong = document.createElement('strong');
        strong.textContent = `App updated to Rev ${revision}. `;
        const link = document.createElement('a');
        link.href = 'changelog.php';
        link.textContent = 'View changelog';
        link.style.color = 'var(--accent)';
        link.style.fontWeight = '850';
        text.append(strong, link);

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'secondary';
        dismiss.textContent = 'Dismiss';
        dismiss.style.width = 'auto';
        dismiss.addEventListener('click', () => {
            window.localStorage.setItem(storageKey, revision);
            card.remove();
        });

        card.append(text, dismiss);
        els.statusCard?.insertAdjacentElement('afterend', card);
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
        window.requestAnimationFrame(() => {
            initMap();
            startFamilyRefresh();
            refreshFamilyLocations();
            startAutoLocationUpdates();
            invalidateMapSoon();
        });
        setStatus('Logged in. Automatic location updates run about every minute while this page is open.');
    }

    async function loadMe() {
        try { applySession(await api('me')); } catch (error) { setStatus(error.message); showAuth(); }
    }

    function isCoarsePointer() {
        return window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    }

    function useMobileMapFallback() {
        return isCoarsePointer() || window.innerWidth <= 850;
    }

    function invalidateMapSoon() {
        if (state.mobileMap) return;
        if (!state.map) return;
        const run = () => {
            if (!state.map) return;
            state.map.invalidateSize({ pan: false });
            applyMapImageLayout();
        };
        window.requestAnimationFrame(run);
        [100, 300, 700, 1200, 2000, 3500].forEach((delay) => window.setTimeout(run, delay));
    }

    function attachMapResizeWatch(mapEl) {
        if (!window.ResizeObserver || state.mapResizeObserver) return;
        state.mapResizeObserver = new ResizeObserver(() => invalidateMapSoon());
        state.mapResizeObserver.observe(mapEl);
    }

    function initMap() {
        if (state.map || state.mobileMap) return;
        const mapEl = $('map');
        if (!mapEl) return;
        if (useMobileMapFallback()) {
            state.mobileMap = true;
            renderMobileMap([]);
            return;
        }
        if (!window.L) return;
        state.map = L.map(mapEl, {
            zoomControl: true,
            dragging: true,
            tap: false,
            scrollWheelZoom: false,
        }).setView([41.4993, -81.6944], 10);
        state.tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            detectRetina: false,
            keepBuffer: 8,
            updateWhenIdle: false,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(state.map);
        state.tileLayer.on('tileload', applyMapImageLayout);
        attachMapResizeWatch(mapEl);
        window.addEventListener('resize', invalidateMapSoon);
        window.addEventListener('orientationchange', () => window.setTimeout(invalidateMapSoon, 450));
        state.map.whenReady(invalidateMapSoon);
        invalidateMapSoon();
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

    function mapLinks(lat, lon, label) {
        const encodedLabel = encodeURIComponent(label || 'Location');
        return {
            apple: `https://maps.apple.com/?ll=${lat},${lon}&q=${encodedLabel}`,
            google: `https://www.google.com/maps/search/?api=1&query=${lat},${lon}`,
            osm: `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=16/${lat}/${lon}`,
        };
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
                const actions = document.createElement('div');
                actions.className = 'member-actions';
                const links = mapLinks(loc.latitude, loc.longitude, member.displayName || member.username || 'Location');
                for (const [label, href] of [['Apple Maps', links.apple], ['Google Maps', links.google], ['OSM', links.osm]]) {
                    const a = document.createElement('a');
                    a.href = href;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.textContent = label;
                    actions.appendChild(a);
                }
                main.append(name, meta, actions);
            } else {
                meta.textContent = 'No shared location yet.';
                main.append(name, meta);
            }
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

    function validLocations(members) {
        return members.filter((member) => {
            const loc = member.location;
            return loc && typeof loc.latitude === 'number' && typeof loc.longitude === 'number';
        });
    }

    function osmEmbedUrl(locations) {
        const first = locations.find((member) => member.id === state.user?.id) || locations[0];
        const firstLoc = first.location;
        let south = firstLoc.latitude - 0.01;
        let north = firstLoc.latitude + 0.01;
        let west = firstLoc.longitude - 0.01;
        let east = firstLoc.longitude + 0.01;

        for (const member of locations) {
            const loc = member.location;
            south = Math.min(south, loc.latitude - 0.006);
            north = Math.max(north, loc.latitude + 0.006);
            west = Math.min(west, loc.longitude - 0.006);
            east = Math.max(east, loc.longitude + 0.006);
        }

        const marker = `${firstLoc.latitude},${firstLoc.longitude}`;
        const bbox = `${west},${south},${east},${north}`;
        return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(marker)}`;
    }

    function renderMobileMap(members) {
        const mapEl = $('map');
        if (!mapEl) return;
        const locations = validLocations(members);
        mapEl.innerHTML = '';
        if (!locations.length) {
            const empty = document.createElement('div');
            empty.className = 'mobile-map-empty';
            empty.innerHTML = '<div><strong>No shared locations yet.</strong><br><span>Locations will appear here after a member grants GPS permission.</span></div>';
            mapEl.appendChild(empty);
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.className = 'mobile-map-iframe';
        iframe.title = 'Family location map';
        iframe.loading = 'lazy';
        iframe.referrerPolicy = 'no-referrer-when-downgrade';
        iframe.src = osmEmbedUrl(locations);
        mapEl.appendChild(iframe);
    }

    function renderMap(members) {
        if (state.mobileMap) {
            renderMobileMap(members);
            return;
        }
        if (!state.map) return;
        invalidateMapSoon();
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
        if (bounds.length === 1) state.map.setView(bounds[0], 15, { animate: false });
        if (bounds.length > 1) state.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15, animate: false });
        invalidateMapSoon();
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
        if (!code) return setStatus('Only the last four characters are visible. The full invite code is not stored for security; regenerate a code to copy the full code.');
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

    installMapLayoutFix();
    showUpdateNoticeIfNeeded();
    wireForms();
    loadMe();
})();
