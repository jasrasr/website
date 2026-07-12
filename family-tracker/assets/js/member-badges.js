/**
 * Project: Family GPS Tracker
 * File: assets/js/member-badges.js
 * Revision: 1.5.8
 * Description: Enhances member cards without high-frequency polling or repeated DOM teardown.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-12
 */
(function () {
    'use strict';

    var cityCache = {};
    var latestMembers = [];
    var refreshTimer = null;
    var stateAbbr = {
        'Alabama':'AL','Alaska':'AK','Arizona':'AZ','Arkansas':'AR','California':'CA','Colorado':'CO','Connecticut':'CT','Delaware':'DE','Florida':'FL','Georgia':'GA','Hawaii':'HI','Idaho':'ID','Illinois':'IL','Indiana':'IN','Iowa':'IA','Kansas':'KS','Kentucky':'KY','Louisiana':'LA','Maine':'ME','Maryland':'MD','Massachusetts':'MA','Michigan':'MI','Minnesota':'MN','Mississippi':'MS','Missouri':'MO','Montana':'MT','Nebraska':'NE','Nevada':'NV','New Hampshire':'NH','New Jersey':'NJ','New Mexico':'NM','New York':'NY','North Carolina':'NC','North Dakota':'ND','Ohio':'OH','Oklahoma':'OK','Oregon':'OR','Pennsylvania':'PA','Rhode Island':'RI','South Carolina':'SC','South Dakota':'SD','Tennessee':'TN','Texas':'TX','Utah':'UT','Vermont':'VT','Virginia':'VA','Washington':'WA','West Virginia':'WV','Wisconsin':'WI','Wyoming':'WY','District of Columbia':'DC'
    };

    function account() {
        var text = (document.getElementById('accountTitle') || {}).textContent || '';
        var open = text.lastIndexOf('('), close = text.lastIndexOf(')');
        if (open < 1 || close < open) return null;
        return { name: text.slice(0, open).trim(), role: text.slice(open + 1, close).trim().toLowerCase() };
    }

    function norm(value) { return String(value || '').trim().toLowerCase(); }
    function locationFormat() { try { return localStorage.getItem('family-tracker-location-format') || 'city'; } catch (ignore) { return 'city'; } }
    function gpsLabel(lat, lon) { return Number(lat).toFixed(3) + ', ' + Number(lon).toFixed(3); }

    function badge(kind, text) {
        var node = document.createElement('span');
        node.className = 'role-badge ' + kind;
        node.textContent = text;
        return node;
    }

    function cityKey(lat, lon) { return 'family-tracker-city-v1:' + Number(lat).toFixed(3) + ',' + Number(lon).toFixed(3); }
    function cachedCity(key) {
        if (cityCache[key]) return cityCache[key];
        try { return localStorage.getItem(key) || ''; } catch (ignore) { return ''; }
    }
    function saveCity(key, value) {
        cityCache[key] = value;
        try { localStorage.setItem(key, value); } catch (ignore) { }
    }
    function clearCityCache() {
        cityCache = {};
        try {
            Object.keys(localStorage).filter(function (key) { return key.indexOf('family-tracker-city-v1:') === 0; })
                .forEach(function (key) { localStorage.removeItem(key); });
        } catch (ignore) { }
    }
    function cityFromAddress(address) {
        if (!address) return '';
        var city = address.city || address.town || address.village || address.municipality || address.hamlet || address.suburb || address.county || '';
        var state = address.state_code || stateAbbr[address.state] || address.state || '';
        return city && state ? city + ', ' + state : city || state || '';
    }
    function lookupCity(lat, lon, callback) {
        var key = cityKey(lat, lon), cached = cachedCity(key);
        if (cached) return callback(cached);
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&addressdetails=1&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var city = cityFromAddress(data && data.address) || gpsLabel(lat, lon);
                saveCity(key, city);
                callback(city);
            }).catch(function () { callback(gpsLabel(lat, lon)); });
    }

    function normalizeAge(text) {
        var match = String(text || '').trim().match(/^(\d+)\s*([smhd])\s*ago$/i);
        if (!match) return String(text || '').trim();
        var value = Number(match[1]), unit = match[2].toLowerCase();
        if (unit === 'h' && value >= 24) return Math.floor(value / 24) + 'd ago';
        return value + unit + ' ago';
    }

    function parseMeta(meta) {
        if (meta.dataset.rawLat && meta.dataset.rawLon && meta.dataset.rawAge) {
            return { lat: Number(meta.dataset.rawLat), lon: Number(meta.dataset.rawLon), age: meta.dataset.rawAge };
        }
        var match = String(meta.textContent || '').match(/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)\s*•\s*([^•]+)/);
        if (!match) return null;
        var parsed = { lat: Number(match[1]), lon: Number(match[2]), age: normalizeAge(match[3]) };
        meta.dataset.rawLat = String(parsed.lat);
        meta.dataset.rawLon = String(parsed.lon);
        meta.dataset.rawAge = parsed.age;
        return parsed;
    }

    function locationText(city, parsed) {
        var format = locationFormat();
        if (format === 'gps') return gpsLabel(parsed.lat, parsed.lon) + ' • ' + parsed.age;
        if (format === 'both') return city + ' • ' + gpsLabel(parsed.lat, parsed.lon) + ' • ' + parsed.age;
        return city + ' • ' + parsed.age;
    }

    function enhanceLocationLabels() {
        var list = document.getElementById('memberList');
        if (!list) return;
        Array.from(list.getElementsByClassName('member-meta')).forEach(function (meta) {
            var parsed = parseMeta(meta);
            if (!parsed) return;
            var marker = parsed.lat.toFixed(5) + ',' + parsed.lon.toFixed(5) + ':' + parsed.age + ':' + locationFormat();
            if (meta.dataset.locationLabel === marker) return;
            meta.dataset.locationLabel = marker;
            if (locationFormat() === 'gps') {
                meta.textContent = locationText('', parsed);
                return;
            }
            lookupCity(parsed.lat, parsed.lon, function (city) {
                if (meta.dataset.locationLabel === marker) meta.textContent = locationText(city, parsed);
            });
        });
    }

    function findMember(card) {
        var id = card.dataset.memberId || '';
        if (id) return latestMembers.find(function (member) { return member.id === id; }) || null;
        var nameNode = card.querySelector('.member-name');
        var name = nameNode ? norm(nameNode.textContent.replace(/\s*\(you\)$/i, '')) : '';
        return latestMembers.find(function (member) {
            return norm(member.displayLabel) === name || norm(member.displayName) === name || norm(member.username) === name;
        }) || null;
    }

    function desiredBadges(info, member) {
        var values = [];
        var profile = (member && member.groupProfile) || {};
        if (info && member && norm(member.displayName) === norm(info.name)) values.push(['you', 'You']);
        if (member && String(member.role || '').toLowerCase() === 'owner') values.push(['owner', 'Owner']);
        if (profile.relationship) values.push(['relationship', profile.relationship]);
        if (member && member.hasDuplicateDisplayLabel) values.push(['duplicate', 'Duplicate Name']);
        return values;
    }

    function enhanceCard(card, info, member) {
        if (!member) return;
        card.dataset.memberId = member.id || '';
        var name = card.querySelector('.member-name');
        if (!name) return;
        var label = member.displayLabel || member.displayName || member.username || 'Unknown';
        if (name.textContent !== label) name.textContent = label;
        var profile = member.groupProfile || {};
        name.style.color = /^#[0-9a-fA-F]{6}$/.test(profile.color || '') ? profile.color : '';

        var main = card.firstElementChild;
        var ident = main && main.querySelector('.member-ident');
        if (!ident && main) {
            ident = document.createElement('div');
            ident.className = 'member-ident';
            main.appendChild(ident);
        }
        if (ident) {
            var parts = ['@' + (member.username || 'unknown'), 'ID ' + String(member.id || '').slice(-8)];
            if (profile.relationship) parts.push(profile.relationship);
            if (member.joinedAt) parts.push('Joined ' + new Date(member.joinedAt).toLocaleDateString());
            if (member.hasDuplicateDisplayLabel) parts.push('duplicate name');
            var identText = parts.join(' • ');
            if (ident.textContent !== identText) ident.textContent = identText;
        }

        var header = name.parentNode.querySelector('.member-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'member-header';
            name.parentNode.insertBefore(header, name);
            header.appendChild(name);
        }
        var values = desiredBadges(info, member);
        var signature = values.map(function (item) { return item.join(':'); }).join('|');
        var existing = header.querySelector('.role-badges');
        if ((existing && existing.dataset.signature) !== signature) {
            if (existing) existing.remove();
            if (values.length) {
                var container = document.createElement('div');
                container.className = 'role-badges';
                container.dataset.signature = signature;
                values.forEach(function (item) { container.appendChild(badge(item[0], item[1])); });
                header.appendChild(container);
            }
        }
    }

    function run() {
        var list = document.getElementById('memberList');
        if (!list) return;
        var info = account();
        Array.from(list.querySelectorAll(':scope > .member-card')).forEach(function (card) {
            enhanceCard(card, info, findMember(card));
        });
        enhanceLocationLabels();
        window.dispatchEvent(new CustomEvent('familyTrackerMemberCardsEnhanced'));
    }

    function fetchMembers() {
        return fetch('api.php?action=family_locations', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { if (data && data.ok) latestMembers = data.members || []; })
            .catch(function () { });
    }

    function refreshAll() {
        if (refreshTimer) window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(function () { fetchMembers().then(run); }, 100);
    }

    window.addEventListener('familyTrackerLocationFormatChanged', function (event) {
        if (event.detail && event.detail.clearCache) clearCityCache();
        Array.from(document.getElementsByClassName('member-meta')).forEach(function (meta) { delete meta.dataset.locationLabel; });
        enhanceLocationLabels();
    });
    window.addEventListener('familyTrackerMemberUiRefresh', refreshAll);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshAll);
    else refreshAll();
    window.setInterval(refreshAll, 30000);
}());
