/**
 * Project: Family GPS Tracker
 * File: assets/js/history.js
 * Revision: 0.2.0
 * Description: Trail history map view.
 */
(() => {
    'use strict';

    const statusText = document.getElementById('statusText');
    const mapEl = document.getElementById('historyMap');
    const memberFilter = document.getElementById('historyMemberFilter');
    const rangeFilter = document.getElementById('historyRangeFilter');
    const refreshBtn = document.getElementById('historyRefreshBtn');
    const clearBtn = document.getElementById('historyClearFocusBtn');
    const detail = document.getElementById('historyMemberDetail');
    let map = null;
    let layers = [];
    let members = [];
    let trails = [];

    if (!mapEl || !window.L) return;

    Object.assign(mapEl.style, {
        width: '100%',
        minHeight: '62vh',
        borderRadius: '1rem',
        overflow: 'hidden',
        background: '#1f2937',
        border: '1px solid rgba(255,255,255,.12)',
    });

    function status(message) {
        if (statusText) statusText.textContent = message;
    }

    async function getJson(url) {
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
        return data;
    }

    function initMap() {
        if (map) return;
        map = L.map('historyMap').setView([41.4993, -81.6944], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
    }

    function esc(value) {
        return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }

    function fmt(value) {
        if (!value) return 'Unknown';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? 'Unknown' : date.toLocaleString();
    }

    function updateOptions() {
        const current = memberFilter.value;
        memberFilter.innerHTML = '<option value="">All members</option>';
        for (const member of members) {
            const option = document.createElement('option');
            option.value = member.id;
            option.textContent = member.displayName || member.username || 'Member';
            memberFilter.append(option);
        }
        if (current && members.some(member => member.id === current)) memberFilter.value = current;
    }

    function visibleMembers() {
        const selected = memberFilter.value;
        return selected ? members.filter(member => member.id === selected) : members;
    }

    function visibleTrails() {
        const selected = memberFilter.value;
        return selected ? trails.filter(trail => trail.member && trail.member.id === selected) : trails;
    }

    function clearLayers() {
        for (const layer of layers) layer.remove();
        layers = [];
    }

    function fit(bounds) {
        if (!bounds.length) return;
        if (bounds.length === 1) map.setView(bounds[0], 15);
        if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }

    function renderDetail() {
        const id = memberFilter.value;
        const member = members.find(item => item.id === id);
        const trail = trails.find(item => item.member && item.member.id === id);
        if (!detail) return;
        if (!member) {
            detail.innerHTML = '<p class="muted">Showing all members.</p>';
            return;
        }
        const loc = member.location || null;
        const points = trail ? trail.points || [] : [];
        const rows = [
            ['Member', member.displayName || member.username || 'Member'],
            ['Latest update', loc ? fmt(loc.serverTime) : 'No location'],
            ['History points', String(points.length)],
            ['First point', points[0] ? fmt(points[0].serverTime) : 'None in range'],
            ['Last point', points.length ? fmt(points[points.length - 1].serverTime) : 'None in range'],
        ];
        detail.innerHTML = rows.map(row => `<p><strong>${esc(row[0])}:</strong> ${esc(row[1])}</p>`).join('');
    }

    function render() {
        initMap();
        clearLayers();
        const bounds = [];

        for (const member of visibleMembers()) {
            const loc = member.location;
            if (!loc || typeof loc.latitude !== 'number' || typeof loc.longitude !== 'number') continue;
            const latLng = [loc.latitude, loc.longitude];
            bounds.push(latLng);
            const marker = L.marker(latLng).addTo(map);
            marker.bindPopup(`<strong>${esc(member.displayName || member.username || 'Member')}</strong><br>${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)}`);
            marker.on('click', () => { memberFilter.value = member.id; refresh(); });
            layers.push(marker);
        }

        for (const trail of visibleTrails()) {
            const member = trail.member || {};
            const points = (trail.points || []).filter(point => typeof point.latitude === 'number' && typeof point.longitude === 'number');
            if (points.length < 2) continue;
            const latLngs = points.map(point => [point.latitude, point.longitude]);
            bounds.push(...latLngs);
            const line = L.polyline(latLngs, { weight: memberFilter.value ? 6 : 4, opacity: memberFilter.value ? 0.9 : 0.65 }).addTo(map);
            line.bindPopup(`<strong>${esc(member.displayName || member.username || 'Member')}</strong><br>${points.length} history points`);
            layers.push(line);
        }

        renderDetail();
        fit(bounds);
    }

    async function refresh() {
        try {
            const minutes = rangeFilter.value || '240';
            const selected = memberFilter.value;
            const query = new URLSearchParams({ minutes });
            if (selected) query.set('memberId', selected);
            const latest = await getJson('api.php?action=family_locations');
            const history = await getJson(`trails.php?${query.toString()}`);
            members = latest.members || [];
            trails = history.trails || [];
            updateOptions();
            render();
            const count = trails.reduce((sum, trail) => sum + ((trail.points || []).length), 0);
            status(`History map refreshed: ${count} points.`);
        } catch (error) {
            status(error.message);
        }
    }

    refreshBtn.addEventListener('click', refresh);
    clearBtn.addEventListener('click', () => { memberFilter.value = ''; refresh(); });
    memberFilter.addEventListener('change', refresh);
    rangeFilter.addEventListener('change', refresh);
    setTimeout(refresh, 500);
    setInterval(refresh, 30000);
})();
