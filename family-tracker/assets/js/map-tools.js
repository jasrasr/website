/**
 * Project: Family GPS Tracker
 * File: assets/js/map-tools.js
 * Revision: 1.4.6
 * Description: Map mode preference, center controls, and external map links for active-group locations.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var members = [];
    var selectedId = '';

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText'); if (node) node.textContent = text; }
    function label(member) { return member.displayLabel || member.displayName || member.username || 'Unknown'; }
    function activeLocationMembers() { return members.filter(function (m) { return m.location && typeof m.location.latitude === 'number' && typeof m.location.longitude === 'number'; }); }
    function currentUserName() {
        var text = ($('accountTitle') || {}).textContent || '';
        var open = text.lastIndexOf('(');
        return (open > 0 ? text.slice(0, open) : text).trim().toLowerCase();
    }
    function mapMode() {
        try { return window.localStorage.getItem('family-tracker-map-mode') || 'embedded'; }
        catch (ignore) { return 'embedded'; }
    }
    function setMapMode(value) {
        try { window.localStorage.setItem('family-tracker-map-mode', value); } catch (ignore) { }
        renderTools();
    }
    function age(seconds) {
        if (seconds == null) return 'unknown';
        if (seconds < 60) return seconds + 's ago';
        var minutes = Math.round(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.round(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        return Math.round(hours / 24) + 'd ago';
    }
    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
                return data;
            });
        });
    }
    function merge(management, locations) {
        var byId = {};
        (management.members || []).forEach(function (m) { byId[m.id] = m; });
        (locations.members || []).forEach(function (m) { byId[m.id] = Object.assign({}, byId[m.id] || {}, m); });
        return Object.values(byId).sort(function (a, b) { return label(a).localeCompare(label(b)); });
    }
    function linksFor(member) {
        var loc = member.location;
        if (!loc) return [];
        var lat = loc.latitude;
        var lon = loc.longitude;
        var encoded = encodeURIComponent(label(member));
        return [
            ['Apple Maps', 'https://maps.apple.com/?ll=' + lat + ',' + lon + '&q=' + encoded],
            ['Google Maps', 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lon],
            ['OSM', 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=16/' + lat + '/' + lon]
        ];
    }
    function groupOsmLink() {
        var locs = activeLocationMembers();
        if (!locs.length) return '';
        var south = locs[0].location.latitude;
        var north = locs[0].location.latitude;
        var west = locs[0].location.longitude;
        var east = locs[0].location.longitude;
        locs.forEach(function (m) {
            south = Math.min(south, m.location.latitude);
            north = Math.max(north, m.location.latitude);
            west = Math.min(west, m.location.longitude);
            east = Math.max(east, m.location.longitude);
        });
        var pad = 0.02;
        return 'https://www.openstreetmap.org/#map=11/' + ((south + north) / 2).toFixed(5) + '/' + ((west + east) / 2).toFixed(5);
    }
    function embedUrl(member) {
        var loc = member.location;
        var lat = Number(loc.latitude);
        var lon = Number(loc.longitude);
        var bbox = [lon - 0.01, lat - 0.01, lon + 0.01, lat + 0.01].join(',');
        return 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(lat + ',' + lon);
    }
    function renderSelect() {
        var select = $('mapCenterMemberSelect');
        if (!select) return;
        var old = select.value || selectedId;
        select.innerHTML = '';
        activeLocationMembers().forEach(function (member) {
            var option = document.createElement('option');
            option.value = member.id;
            option.textContent = label(member) + (member.location && member.location.isStale ? ' (stale)' : '');
            select.appendChild(option);
        });
        selectedId = old && activeLocationMembers().some(function (m) { return m.id === old; }) ? old : (activeLocationMembers()[0] ? activeLocationMembers()[0].id : '');
        select.value = selectedId;
    }
    function selectedMember() { return members.find(function (m) { return m.id === selectedId; }) || activeLocationMembers()[0] || null; }
    function ownMember() {
        var name = currentUserName();
        return activeLocationMembers().find(function (m) { return String(label(m)).trim().toLowerCase() === name || String(m.displayName || '').trim().toLowerCase() === name; }) || null;
    }
    function buttonLink(text, href) {
        var a = document.createElement('a');
        a.className = 'secondary-link';
        a.href = href;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = text;
        return a;
    }
    function renderMemberPreview(member) {
        var out = $('mapToolsOutput');
        if (!out) return;
        out.innerHTML = '';
        if (!member || !member.location) {
            out.textContent = 'No member with a saved location is available yet.';
            return;
        }
        var card = document.createElement('article');
        card.className = 'member-card';
        var main = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'member-name';
        title.textContent = 'Centered on ' + label(member);
        var meta = document.createElement('div');
        meta.className = 'member-meta';
        meta.textContent = Number(member.location.latitude).toFixed(5) + ', ' + Number(member.location.longitude).toFixed(5) + ' • ' + age(member.location.ageSeconds) + (member.location.isStale ? ' • stale' : '');
        var actions = document.createElement('div');
        actions.className = 'member-actions';
        linksFor(member).forEach(function (pair) { actions.appendChild(buttonLink(pair[0], pair[1])); });
        var group = groupOsmLink();
        if (group) actions.appendChild(buttonLink('Open Group Area', group));
        main.append(title, meta, actions);
        card.appendChild(main);
        out.appendChild(card);
        if (mapMode() === 'static') {
            var mapBox = document.createElement('div');
            mapBox.className = 'detail-map-box';
            var iframe = document.createElement('iframe');
            iframe.className = 'mobile-map-iframe';
            iframe.title = 'Static preview for ' + label(member);
            iframe.src = embedUrl(member);
            mapBox.appendChild(iframe);
            out.appendChild(mapBox);
        }
        if (mapMode() === 'external') {
            var note = document.createElement('div');
            note.className = 'member-meta';
            note.textContent = 'External mode keeps the app map unchanged and uses the links above for navigation.';
            out.appendChild(note);
        }
    }
    function renderTools() {
        var mode = $('mapModeSelect');
        if (mode) mode.value = mapMode();
        renderSelect();
        renderMemberPreview(selectedMember());
    }
    function centerOn(member) {
        if (!member || !member.location) return status('No saved location for that member yet.');
        selectedId = member.id;
        var select = $('mapCenterMemberSelect');
        if (select) select.value = selectedId;
        renderMemberPreview(member);
        var card = $('mapToolsCard');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        status('Map tools centered on ' + label(member) + '.');
    }
    function load() {
        Promise.all([fetchJson('member-management.php'), fetchJson('api.php?action=family_locations')]).then(function (results) {
            members = merge(results[0], results[1]);
            renderTools();
        }).catch(function () { });
    }
    function boot() {
        var mode = $('mapModeSelect');
        var select = $('mapCenterMemberSelect');
        var me = $('centerMapOnMeBtn');
        var member = $('centerMapOnMemberBtn');
        if (mode) mode.addEventListener('change', function () { setMapMode(mode.value); });
        if (select) select.addEventListener('change', function () { selectedId = select.value; renderMemberPreview(selectedMember()); });
        if (me) me.addEventListener('click', function () { centerOn(ownMember()); });
        if (member) member.addEventListener('click', function () { centerOn(selectedMember()); });
        load();
        window.setInterval(load, 15000);
        window.addEventListener('familyTrackerMemberUiRefresh', load);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
