/**
 * Project: Family GPS Tracker
 * File: assets/js/history.js
 * Revision: 0.2.0
 * Description: Separate all-family and individual trail-history map.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

(() => {
    'use strict';

    const state = {
        map: null,
        memberMarkers: new Map(),
        trailLayers: new Map(),
        members: [],
        trails: [],
        selectedMemberId: '',
        timer: null,
    };

    const els = {
        trackerApp: document.getElementById('trackerApp'),
        historyMap: document.getElementById('historyMap'),
        memberFilter: document.getElementById('historyMemberFilter'),
        rangeFilter: document.getElementById('historyRangeFilter'),
        refreshBtn: document.getElementById('historyRefreshBtn'),
        clearFocusBtn: document.getElementById('historyClearFocusBtn'),
        detail: document.getElementById('historyMemberDetail'),
        statusText: document.getElementById('statusText'),
    };

    if (!els.historyMap || !window.L) {
        return;
    }

    function setStatus(message) {
        if (els.statusText) {
            els.statusText.textContent = message;
        }
    }

    async function getJson(url) {
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json().catch(() => ({ ok: false, error: 'Invalid JSON response.' }));
        if (!response.ok || !data.ok) {
            throw new Error(data.error || `Request failed: ${response.status}`);
        }
        return data;
    }

    function initMap() {
        if (state.map) {
            return;
        }

        state.map = L.map('historyMap', { zoomControl: true }).setView([41.4993, -81.6944], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(state.map);

        setTimeout(() => state.map.invalidateSize(), 250);
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

    function metersToFeet(value) {
        return value === null || value === undefined ? null : value * 3.28084;
    }

    function mpsToMph(value) {
        return value === null || value === undefined ? null : value * 2.23694;
    }

    function formatDateTime(value) {
        if (!value) return 'Unknown';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? 'Unknown' : date.toLocaleString();
    }

    function formatAge(seconds) {
        if (seconds === null || seconds === undefined) return 'unknown age';
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.round(minutes / 60);
        return `${hours}h ago`;
    }

    function currentMinutes() {
        return Number.parseInt(els.rangeFilter?.value || '240', 10) || 240;
    }

    function selectedMemberId() {
        return els.memberFilter?.value || '';
    }

    function fitBounds(bounds) {
        if (!state.map || !bounds.length) return;
        if (bounds.length === 1) {
            state.map.setView(bounds[0], 15);
        } else {
            state.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
        }
    }

    function clearMapLayers() {
        for (const layer of state.memberMarkers.values()) {
            layer.remove();
        }
        state.memberMarkers.clear();

        for (const layer of state.trailLayers.values()) {
            layer.remove();
        }
        state.trailLayers.clear();
    }

    function updateMemberOptions() {
        if (!els.memberFilter) return;
        const current = selectedMemberId();
        els.memberFilter.innerHTML = '<option value="">All members</option>';

        for (const member of state.members) {
            const option = document.createElement('option');
            option.value = member.id;
            option.textContent = member.displayName || member.username || 'Unknown';
            els.memberFilter.append(option);
        }

        if (current && state.members.some((member) => member.id === current)) {
            els.memberFilter.value = current;
        }
    }

    function visibleMembers() {
        const selected = selectedMemberId();
        return selected ? state.members.filter((member) => member.id === selected) : state.members;
    }

    function visibleTrails() {
        const selected = selectedMemberId();
        return selected ? state.trails.filter((trail) => trail.member?.id === selected) : state.trails;
    }

    function renderLatestMarkers() {
        const bounds = [];
        for (const member of visibleMembers()) {
            const loc = member.location;
            if (!loc || !Number.isFinite(loc.latitude) || !Number.isFinite(loc.longitude)) {
                continue;
            }

            const latLng = [loc.latitude, loc.longitude];
            bounds.push(latLng);
            const marker = L.marker(latLng).addTo(state.map);
            const name = escapeHtml(member.displayName || member.username || 'Member');
            marker.bindPopup(`<strong>${name}</strong><br>Latest: ${escapeHtml(formatAge(loc.ageSeconds))}<br>${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)}`);
            marker.on('click', () => focusMember(member.id));
            state.memberMarkers.set(member.id, marker);
        }
        return bounds;
    }

    function renderTrailLines() {
        const bounds = [];
        for (const trail of visibleTrails()) {
            const member = trail.member || {};
            const points = (trail.points || [])
                .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude))
                .map((point) => [point.latitude, point.longitude, point.serverTime]);

            if (points.length < 2) {
                continue;
            }

            const latLngs = points.map((point) => [point[0], point[1]]);
            bounds.push(...latLngs);

            const line = L.polyline(latLngs, {
                weight: selectedMemberId() ? 6 : 4,
                opacity: selectedMemberId() ? 0.9 : 0.65,
            }).addTo(state.map);

            const first = formatDateTime(points[0][2]);
            const last = formatDateTime(points[points.length - 1][2]);
            const name = escapeHtml(member.displayName || member.username || 'Member');
            line.bindPopup(`<strong>${name}</strong><br>${points.length} history points<br>${escapeHtml(first)} → ${escapeHtml(last)}`);
            state.trailLayers.set(member.id || String(Math.random()), line);
        }
        return bounds;
    }

    function renderDetail() {
        if (!els.detail) return;
        const memberId = selectedMemberId();
        const member = state.members.find((item) => item.id === memberId);
        const trail = state.trails.find((item) => item.member?.id === memberId);

        if (!member) {
            els.detail.innerHTML = '<div class="detail-item"><span>Mode</span><strong>Showing everyone</strong></div>';
            return;
        }

        const loc = member.location;
        const points = trail?.points || [];
        const firstPoint = points[0] || null;
        const lastPoint = points[points.length - 1] || null;
        const accuracyFeet = loc ? metersToFeet(loc.accuracy) : null;
        const speedMph = loc ? mpsToMph(loc.speedMps) : null;

        const items = [
            ['Member', member.displayName || member.username || 'Member'],
            ['Status', !loc ? 'No location' : loc.isStale ? 'Stale' : 'Live-ish'],
            ['Last update', loc ? formatDateTime(loc.serverTime) : 'Never'],
            ['Location age', loc ? formatAge(loc.ageSeconds) : 'Unknown'],
            ['Accuracy', Number.isFinite(accuracyFeet) ? `${accuracyFeet.toFixed(0)} ft` : 'Unknown'],
            ['Speed', Number.isFinite(speedMph) ? `${speedMph.toFixed(1)} mph` : 'Unknown'],
            ['Heading', loc && Number.isFinite(loc.heading) ? `${loc.heading.toFixed(0)}°` : 'Unknown'],
            ['History points', String(points.length)],
            ['First trail point', firstPoint ? formatDateTime(firstPoint.serverTime) : 'None in range'],
            ['Last trail point', lastPoint ? formatDateTime(lastPoint.serverTime) : 'None in range'],
        ];

        els.detail.innerHTML = '';
        for (const [label, value] of items) {
            const item = document.createElement('div');
            item.className = 'detail-item';
            const labelEl = document.createElement('span');
            labelEl.textContent = label;
            const valueEl = document.createElement('strong');
            valueEl.textContent = value;
            item.append(labelEl, valueEl);
            els.detail.append(item);
        }
    }

    function renderHistoryMap() {
        initMap();
        clearMapLayers();
        const markerBounds = renderLatestMarkers();
        const trailBounds = renderTrailLines();
        renderDetail();
        fitBounds([...markerBounds, ...trailBounds]);
    }

    async function refreshHistory() {
        if (!els.trackerApp || els.trackerApp.classList.contains('hidden')) {
            return;
        }

        initMap();
        const memberId = selectedMemberId();
        const minutes = currentMinutes();
        const trailQuery = new URLSearchParams({ minutes: String(minutes) });
        if (memberId) {
            trailQuery.set('memberId', memberId);
        }

        const [locationData, trailData] = await Promise.all([
            getJson('api.php?action=family_locations'),
            getJson(`trails.php?${trailQuery.toString()}`),
        ]);

        state.members = locationData.members || [];
        state.trails = trailData.trails || [];
        updateMemberOptions();
        renderHistoryMap();

        const pointCount = state.trails.reduce((total, trail) => total + (trail.points?.length || 0), 0);
        setStatus(`History map refreshed: ${pointCount} trail points in the last ${trailData.minutes} minutes.`);
    }

    function focusMember(memberId) {
        if (!els.memberFilter) return;
        els.memberFilter.value = memberId;
        refreshHistory().catch((error) => setStatus(error.message));
    }

    function showEveryone() {
        if (els.memberFilter) {
            els.memberFilter.value = '';
        }
        refreshHistory().catch((error) => setStatus(error.message));
    }

    function wireEvents() {
        els.refreshBtn?.addEventListener('click', () => refreshHistory().catch((error) => setStatus(error.message)));
        els.clearFocusBtn?.addEventListener('click', showEveryone);
        els.memberFilter?.addEventListener('change', () => refreshHistory().catch((error) => setStatus(error.message)));
        els.rangeFilter?.addEventListener('change', () => refreshHistory().catch((error) => setStatus(error.message)));
    }

    wireEvents();
    setTimeout(() => refreshHistory().catch(() => {}), 1200);
    state.timer = window.setInterval(() => refreshHistory().catch(() => {}), 30000);
})();
