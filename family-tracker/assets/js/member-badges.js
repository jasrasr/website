/**
 * Project: Family GPS Tracker
 * File: assets/js/member-badges.js
 * Revision: 1.4.2
 * Description: Enhances member cards with You/Owner badges, identifiers, and configurable location labels.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var cityCache = {};
    var latestMembers = [];
    var stateAbbr = {
        'Alabama': 'AL', 'Alaska': 'AK', 'Arizona': 'AZ', 'Arkansas': 'AR', 'California': 'CA', 'Colorado': 'CO', 'Connecticut': 'CT', 'Delaware': 'DE', 'Florida': 'FL', 'Georgia': 'GA', 'Hawaii': 'HI', 'Idaho': 'ID', 'Illinois': 'ID', 'Indiana': 'IN', 'Iowa': 'IA', 'Kansas': 'KS', 'Kentucky': 'KY', 'Louisiana': 'LA', 'Maine': 'ME', 'Maryland': 'MD', 'Massachusetts': 'MA', 'Michigan': 'MI', 'Minnesota': 'MN', 'Mississippi': 'MS', 'Missouri': 'MO', 'Montana': 'MT', 'Nebraska': 'NE', 'Nevada': 'NV', 'New Hampshire': 'NH', 'New Jersey': 'NJ', 'New Mexico': 'NM', 'New York': 'NY', 'North Carolina': 'NC', 'North Dakota': 'ND', 'Ohio': 'OH', 'Oklahoma': 'OK', 'Oregon': 'OR', 'Pennsylvania': 'PA', 'Rhode Island': 'RI', 'South Carolina': 'SC', 'South Dakota': 'SD', 'Tennessee': 'TN', 'Texas': 'TX', 'Utah': 'UT', 'Vermont': 'VT', 'Virginia': 'VA', 'Washington': 'WA', 'West Virginia': 'WV', 'Wisconsin': 'WI', 'Wyoming': 'WY', 'District of Columbia': 'DC'
    };

    function setStatus(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function account() {
        var text = (document.getElementById('accountTitle') || {}).textContent || '';
        var open = text.lastIndexOf('(');
        var close = text.lastIndexOf(')');
        if (open < 1 || close < open) return null;
        return { name: text.slice(0, open).trim(), role: text.slice(open + 1, close).trim().toLowerCase() };
    }

    function norm(value) { return String(value || '').trim().toLowerCase(); }

    function locationFormat() {
        try { return window.localStorage.getItem('family-tracker-location-format') || 'city'; }
        catch (ignore) { return 'city'; }
    }

    function span(kind, text) {
        var item = document.createElement('span');
        item.className = 'role-badge ' + kind;
        item.textContent = text;
        return item;
    }

    function cityCacheKey(lat, lon) {
        return 'family-tracker-city-v1:' + Number(lat).toFixed(3) + ',' + Number(lon).toFixed(3);
    }

    function storedCity(key) {
        if (cityCache[key]) return cityCache[key];
        try {
            var stored = window.localStorage.getItem(key);
            if (stored) {
                cityCache[key] = stored;
                return stored;
            }
        } catch (ignore) { }
        return '';
    }

    function saveCity(key, city) {
        cityCache[key] = city;
        try { window.localStorage.setItem(key, city); } catch (ignore) { }
    }

    function clearCityCache() {
        cityCache = {};
        try {
            var keys = [];
            for (var i = 0; i < window.localStorage.length; i++) {
                var key = window.localStorage.key(i);
                if (key && key.indexOf('family-tracker-city-v1:') === 0) keys.push(key);
            }
            keys.forEach(function (key) { window.localStorage.removeItem(key); });
        } catch (ignore) { }
    }

    function cityFromAddress(address) {
        if (!address) return '';
        var city = address.city || address.town || address.village || address.municipality || address.hamlet || address.suburb || address.county || '';
        var state = address.state_code || stateAbbr[address.state] || address.state || '';
        if (city && state) return city + ', ' + state;
        return city || state || '';
    }

    function gpsLabel(lat, lon) {
        return Number(lat).toFixed(3) + ', ' + Number(lon).toFixed(3);
    }

    function lookupCity(lat, lon, callback) {
        var key = cityCacheKey(lat, lon);
        var cached = storedCity(key);
        if (cached) return callback(cached);

        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&addressdetails=1&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var city = cityFromAddress(data && data.address) || gpsLabel(lat, lon);
                saveCity(key, city);
                callback(city);
            })
            .catch(function () { callback(gpsLabel(lat, lon)); });
    }

    function normalizeAge(text) {
        var match = String(text || '').trim().match(/^(\d+)\s*([smhd])\s*ago$/i);
        if (!match) return String(text || '').trim();
        var number = Number(match[1]);
        var unit = match[2].toLowerCase();
        if (unit === 'h' && number >= 24) return Math.floor(number / 24) + 'd ago';
        return number + unit + ' ago';
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

    function formatLocationText(city, parsed) {
        var format = locationFormat();
        if (format === 'gps') return gpsLabel(parsed.lat, parsed.lon) + ' • ' + parsed.age;
        if (format === 'both') return city + ' • ' + gpsLabel(parsed.lat, parsed.lon) + ' • ' + parsed.age;
        return city + ' • ' + parsed.age;
    }

    function enhanceLocationLabels() {
        var list = document.getElementById('memberList');
        if (!list) return;
        var metas = list.getElementsByClassName('member-meta');
        for (var i = 0; i < metas.length; i++) {
            (function (meta) {
                var parsed = parseMeta(meta);
                if (!parsed) return;
                var marker = Number(parsed.lat).toFixed(5) + ',' + Number(parsed.lon).toFixed(5) + ':' + parsed.age + ':' + locationFormat();
                if (meta.getAttribute('data-location-label') === marker) return;
                meta.setAttribute('data-location-label', marker);
                if (locationFormat() === 'gps') {
                    meta.textContent = formatLocationText('', parsed);
                    return;
                }
                meta.textContent = 'Closest city... • ' + parsed.age;
                lookupCity(parsed.lat, parsed.lon, function (city) {
                    if (meta.getAttribute('data-location-label') === marker) meta.textContent = formatLocationText(city, parsed);
                });
            }(metas[i]));
        }
    }

    function fetchMembers() {
        return fetch('api.php?action=family_locations', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.ok) latestMembers = data.members || [];
            }).catch(function () { });
    }

    function findMemberForCard(card) {
        var nameNode = card.getElementsByClassName('member-name')[0];
        var name = nameNode ? norm(nameNode.textContent) : '';
        for (var i = 0; i < latestMembers.length; i++) {
            if (norm(latestMembers[i].displayName || latestMembers[i].username) === name) return latestMembers[i];
        }
        return null;
    }

    function enhanceIdentifiers(card, member) {
        if (!member) return;
        var main = card.firstElementChild;
        if (!main) return;
        var ident = main.getElementsByClassName('member-ident')[0];
        if (!ident) {
            ident = document.createElement('div');
            ident.className = 'member-ident';
            var header = main.getElementsByClassName('member-header')[0] || main.getElementsByClassName('member-name')[0];
            if (header && header.nextSibling) main.insertBefore(ident, header.nextSibling);
            else main.appendChild(ident);
        }
        ident.textContent = '@' + (member.username || 'unknown') + ' • ID ' + String(member.id || '').slice(-8);
    }

    function enhanceBadges(card, info, member) {
        var name = card.getElementsByClassName('member-name')[0];
        if (!name) return;
        var old = card.getElementsByClassName('role-badges')[0];
        if (old) old.remove();
        var header = name.parentNode.getElementsByClassName('member-header')[0];
        if (!header) {
            header = document.createElement('div');
            header.className = 'member-header';
            name.parentNode.insertBefore(header, name);
            header.appendChild(name);
        }
        var isYou = info && norm(name.textContent) === norm(info.name);
        var isOwner = member && String(member.role || '').toLowerCase() === 'owner';
        if (!isYou && !isOwner) return;
        var badges = document.createElement('div');
        badges.className = 'role-badges';
        if (isYou) badges.appendChild(span('you', 'You'));
        if (isOwner) badges.appendChild(span('owner', 'Owner'));
        header.appendChild(badges);
    }

    function run() {
        var info = account();
        var list = document.getElementById('memberList');
        if (!list) return;
        var cards = list.getElementsByClassName('member-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var member = findMemberForCard(card);
            enhanceIdentifiers(card, member);
            enhanceBadges(card, info, member);
            if (info) {
                var name = card.getElementsByClassName('member-name')[0];
                if (name && norm(name.textContent) === norm(info.name) && list.firstElementChild !== card) list.insertBefore(card, list.firstElementChild);
            }
        }
        enhanceLocationLabels();
    }

    function refreshAll() {
        fetchMembers().then(run);
    }

    window.addEventListener('familyTrackerLocationFormatChanged', function (event) {
        if (event.detail && event.detail.clearCache) clearCityCache();
        var metas = document.getElementsByClassName('member-meta');
        for (var i = 0; i < metas.length; i++) metas[i].removeAttribute('data-location-label');
        enhanceLocationLabels();
    });
    window.addEventListener('familyTrackerMemberUiRefresh', refreshAll);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshAll);
    else refreshAll();
    window.setInterval(refreshAll, 2000);
}());
