/**
 * Project: Family GPS Tracker
 * File: assets/js/member-detail-links.js
 * Revision: 1.4.5
 * Description: Main-page member quick detail card and per-member detail links.
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
    function norm(value) { return String(value || '').replace(/\s+\(you\)$/i, '').trim().toLowerCase(); }
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
    function gps(loc) { return Number(loc.latitude).toFixed(5) + ', ' + Number(loc.longitude).toFixed(5); }
    function mapLinks(lat, lon, text) {
        var encoded = encodeURIComponent(text || 'Location');
        return [
            ['Details', 'member-detail.php?memberId='],
            ['Apple Maps', 'https://maps.apple.com/?ll=' + lat + ',' + lon + '&q=' + encoded],
            ['Google Maps', 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lon],
            ['OSM', 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=16/' + lat + '/' + lon]
        ];
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
    function selectedMember() {
        return members.find(function (m) { return m.id === selectedId; }) || members[0] || null;
    }
    function renderSelect() {
        var select = $('memberDetailSelect');
        if (!select) return;
        var old = select.value || selectedId;
        select.innerHTML = '';
        members.forEach(function (member) {
            var option = document.createElement('option');
            option.value = member.id;
            option.textContent = label(member);
            select.appendChild(option);
        });
        selectedId = old && members.some(function (m) { return m.id === old; }) ? old : (members[0] ? members[0].id : '');
        select.value = selectedId;
    }
    function stat(title, value) {
        var item = document.createElement('div');
        item.className = 'diag-item';
        item.innerHTML = '<strong></strong><span></span>';
        item.querySelector('strong').textContent = title;
        item.querySelector('span').textContent = value;
        return item;
    }
    function renderQuickDetail() {
        var out = $('memberQuickDetail');
        if (!out) return;
        var member = selectedMember();
        out.innerHTML = '';
        if (!member) {
            out.textContent = 'No members loaded yet.';
            return;
        }
        var loc = member.location;
        out.appendChild(stat('Member', label(member)));
        out.appendChild(stat('Username', '@' + (member.username || 'unknown')));
        out.appendChild(stat('Relationship', member.groupProfile && member.groupProfile.relationship ? member.groupProfile.relationship : '—'));
        out.appendChild(stat('Joined', member.joinedAt ? new Date(member.joinedAt).toLocaleDateString() : 'unknown'));
        if (!loc) {
            out.appendChild(stat('Location', 'No shared location yet'));
        } else {
            out.appendChild(stat('Last Update', age(loc.ageSeconds)));
            out.appendChild(stat('Coordinates', gps(loc)));
            out.appendChild(stat('Accuracy', feet(loc.accuracy)));
            out.appendChild(stat('Status', loc.isStale ? 'Stale' : 'Live-ish'));
        }
        var actions = document.createElement('div');
        actions.className = 'member-actions detail-actions';
        var detail = document.createElement('a');
        detail.href = 'member-detail.php?memberId=' + encodeURIComponent(member.id);
        detail.textContent = 'Open Detail';
        actions.appendChild(detail);
        if (loc) {
            mapLinks(loc.latitude, loc.longitude, label(member)).slice(1).forEach(function (pair) {
                var a = document.createElement('a');
                a.href = pair[1];
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = pair[0];
                actions.appendChild(a);
            });
        }
        out.appendChild(actions);
    }
    function matchMemberCard(card) {
        var name = card.querySelector('.member-name');
        if (!name) return null;
        var key = norm(name.textContent);
        return members.find(function (m) { return norm(label(m)) === key || norm(m.displayName) === key || norm(m.username) === key; }) || null;
    }
    function injectCardLinks() {
        var list = $('memberList');
        if (!list) return;
        Array.prototype.forEach.call(list.querySelectorAll('.member-card'), function (card) {
            if (card.dataset.detailLinks === '1') return;
            var member = matchMemberCard(card);
            if (!member) return;
            var main = card.firstElementChild;
            if (!main) return;
            var actions = main.querySelector('.member-actions') || document.createElement('div');
            actions.className = 'member-actions';
            var detail = document.createElement('a');
            detail.href = 'member-detail.php?memberId=' + encodeURIComponent(member.id);
            detail.textContent = 'Details';
            actions.appendChild(detail);
            if (!actions.parentNode) main.appendChild(actions);
            card.dataset.detailLinks = '1';
        });
    }
    function load() {
        Promise.all([fetchJson('member-management.php'), fetchJson('api.php?action=family_locations')]).then(function (results) {
            members = merge(results[0], results[1]);
            renderSelect();
            renderQuickDetail();
            injectCardLinks();
        }).catch(function () { });
    }
    function boot() {
        var select = $('memberDetailSelect');
        if (select) select.addEventListener('change', function () { selectedId = select.value; renderQuickDetail(); });
        load();
        window.setInterval(function () { load(); injectCardLinks(); }, 5000);
        window.addEventListener('familyTrackerMemberUiRefresh', load);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
