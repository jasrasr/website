/**
 * Project: Family GPS Tracker
 * File: assets/js/member-detail.js
 * Revision: 1.4.5
 * Description: Member detail page behavior for last-known location, external maps, and trail preview.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var members = [];
    var locations = [];

    function $(id) { return document.getElementById(id); }
    function setStatus(text) { var node = $('statusText'); if (node) node.textContent = text; }
    function params() { return new URLSearchParams(window.location.search); }
    function wantedMemberId() { return params().get('memberId') || ''; }
    function fmt(value) { return value == null || value === '' ? '—' : String(value); }
    function age(seconds) {
        if (seconds == null) return 'unknown';
        if (seconds < 60) return seconds + 's ago';
        var minutes = Math.round(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.round(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        return Math.round(hours / 24) + 'd ago';
    }
    function feet(meters) { return Number.isFinite(Number(meters)) ? Math.round(Number(meters) * 3.28084) + ' ft' : 'unknown'; }
    function mph(mps) { return Number.isFinite(Number(mps)) ? (Number(mps) * 2.23694).toFixed(1) + ' mph' : 'unknown'; }
    function memberLabel(member) { return member.displayLabel || member.displayName || member.username || 'Unknown'; }
    function mapLinks(lat, lon, label) {
        var encoded = encodeURIComponent(label || 'Location');
        return [
            ['Apple Maps', 'https://maps.apple.com/?ll=' + lat + ',' + lon + '&q=' + encoded],
            ['Google Maps', 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lon],
            ['OSM', 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=16/' + lat + '/' + lon]
        ];
    }
    function stat(label, value) {
        var box = document.createElement('div');
        box.className = 'diag-item';
        box.innerHTML = '<strong></strong><span></span>';
        box.querySelector('strong').textContent = label;
        box.querySelector('span').textContent = value;
        return box;
    }
    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || !data.ok) throw new Error(data.error || 'Request failed.');
                return data;
            });
        });
    }
    function mergeMembers(management, familyLocations) {
        var byId = {};
        (management.members || []).forEach(function (m) { byId[m.id] = m; });
        (familyLocations.members || []).forEach(function (m) {
            byId[m.id] = Object.assign({}, byId[m.id] || {}, m);
        });
        return Object.values(byId).sort(function (a, b) { return memberLabel(a).localeCompare(memberLabel(b)); });
    }
    function selectedMember() {
        var select = $('memberDetailSelect');
        var id = select && select.value ? select.value : wantedMemberId();
        return members.find(function (m) { return m.id === id; }) || members[0] || null;
    }
    function renderSelect() {
        var select = $('memberDetailSelect');
        if (!select) return;
        var wanted = wantedMemberId();
        select.innerHTML = '';
        members.forEach(function (member) {
            var option = document.createElement('option');
            option.value = member.id;
            option.textContent = memberLabel(member);
            option.selected = member.id === wanted;
            select.appendChild(option);
        });
        if (wanted && !select.value && members[0]) select.value = members[0].id;
    }
    function renderSummary(member) {
        var title = $('memberDetailTitle');
        var summary = $('memberDetailSummary');
        if (!member || !summary) return;
        var loc = member.location;
        if (title) title.textContent = memberLabel(member);
        summary.innerHTML = '';
        summary.appendChild(stat('Username', '@' + fmt(member.username)));
        summary.appendChild(stat('Role', fmt(member.role)));
        summary.appendChild(stat('Relationship', fmt(member.groupProfile && member.groupProfile.relationship)));
        summary.appendChild(stat('Joined', member.joinedAt ? new Date(member.joinedAt).toLocaleDateString() : 'unknown'));
        if (!loc) {
            summary.appendChild(stat('Location', 'No shared location yet'));
            summary.appendChild(stat('Status', 'No location'));
            renderMap(null, member);
            return;
        }
        summary.appendChild(stat('Last update', age(loc.ageSeconds)));
        summary.appendChild(stat('Coordinates', Number(loc.latitude).toFixed(5) + ', ' + Number(loc.longitude).toFixed(5)));
        summary.appendChild(stat('Accuracy', feet(loc.accuracy)));
        summary.appendChild(stat('Speed', mph(loc.speedMps)));
        summary.appendChild(stat('Heading', Number.isFinite(Number(loc.heading)) ? Math.round(Number(loc.heading)) + '°' : 'unknown'));
        summary.appendChild(stat('Status', loc.isStale ? 'Stale' : 'Live-ish'));
        var actions = document.createElement('div');
        actions.className = 'member-actions detail-actions';
        mapLinks(loc.latitude, loc.longitude, memberLabel(member)).forEach(function (pair) {
            var a = document.createElement('a');
            a.href = pair[1];
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = pair[0];
            actions.appendChild(a);
        });
        summary.appendChild(actions);
        renderMap(loc, member);
    }
    function renderMap(loc, member) {
        var map = $('memberDetailMap');
        if (!map) return;
        map.innerHTML = '';
        if (!loc) {
            map.innerHTML = '<div class="mobile-map-empty"><strong>No map yet.</strong><br><span>This member has not shared a location in this group.</span></div>';
            return;
        }
        var lat = Number(loc.latitude);
        var lon = Number(loc.longitude);
        var bbox = [lon - 0.01, lat - 0.01, lon + 0.01, lat + 0.01].join(',');
        var iframe = document.createElement('iframe');
        iframe.className = 'mobile-map-iframe';
        iframe.title = 'Last known map for ' + memberLabel(member);
        iframe.src = 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(lat + ',' + lon);
        map.appendChild(iframe);
    }
    function renderTrail(member) {
        var output = $('memberTrailSummary');
        var minutes = $('memberDetailWindow') ? $('memberDetailWindow').value : '240';
        if (!member || !output) return;
        output.textContent = 'Loading trail...';
        fetchJson('trails.php?memberId=' + encodeURIComponent(member.id) + '&minutes=' + encodeURIComponent(minutes)).then(function (data) {
            var trail = (data.trails || [])[0];
            var points = trail ? (trail.points || []) : [];
            output.innerHTML = '';
            var top = document.createElement('article');
            top.className = 'member-card';
            top.innerHTML = '<div><div class="member-name"></div><div class="member-meta"></div></div><span class="badge"></span>';
            top.querySelector('.member-name').textContent = memberLabel(member) + ' trail';
            top.querySelector('.member-meta').textContent = points.length ? 'Showing latest ' + Math.min(points.length, 10) + ' of ' + points.length + ' points.' : 'No trail points in selected window.';
            top.querySelector('.badge').textContent = points.length + ' points';
            output.appendChild(top);
            points.slice(-10).reverse().forEach(function (point) {
                var row = document.createElement('article');
                row.className = 'member-card';
                row.innerHTML = '<div><div class="member-name"></div><div class="member-meta"></div></div>';
                row.querySelector('.member-name').textContent = point.serverTime ? new Date(point.serverTime).toLocaleString() : 'Unknown time';
                row.querySelector('.member-meta').textContent = Number(point.latitude).toFixed(5) + ', ' + Number(point.longitude).toFixed(5) + ' • accuracy ' + feet(point.accuracy) + ' • speed ' + mph(point.speedMps);
                output.appendChild(row);
            });
        }).catch(function (error) { output.textContent = error.message || 'Trail failed to load.'; });
    }
    function renderAll() {
        var member = selectedMember();
        renderSummary(member);
        renderTrail(member);
        setStatus(member ? 'Showing detail for ' + memberLabel(member) + '.' : 'No members found.');
    }
    function load() {
        Promise.all([fetchJson('member-management.php'), fetchJson('api.php?action=family_locations')]).then(function (results) {
            members = mergeMembers(results[0], results[1]);
            locations = results[1].members || [];
            renderSelect();
            renderAll();
        }).catch(function (error) { setStatus(error.message || 'Could not load member detail.'); });
    }
    function boot() {
        var select = $('memberDetailSelect');
        var windowSelect = $('memberDetailWindow');
        if (select) select.addEventListener('change', renderAll);
        if (windowSelect) windowSelect.addEventListener('change', renderAll);
        load();
        window.setInterval(load, 30000);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
