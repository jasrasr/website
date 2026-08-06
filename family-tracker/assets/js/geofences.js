/**
 * Project: Family GPS Tracker
 * File: assets/js/geofences.js
 * Revision: 1.6.9
 * Description: Address-first geofence place creation and editing with hidden coordinate storage, reverse lookup, notification controls, and periodic evaluation.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-08-06
 */
(function () {
    'use strict';

    var csrfToken = '';
    var role = 'member';
    var latestMembers = [];
    var editingZoneId = '';
    var lastAddressLookup = '';

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText'); if (node) node.textContent = text; }

    function request(payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('geofences.php', options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Place request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                role = data.role || role;
                return data;
            });
        });
    }

    function loadMembers() {
        return fetch('api.php?action=family_locations', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { if (data && data.ok) latestMembers = data.members || []; });
    }

    function createUi() {
        if ($('geofenceCard')) return;
        var main = $('trackerApp');
        if (!main) return;
        var card = document.createElement('section');
        card.id = 'geofenceCard';
        card.className = 'card profile-edit';
        card.innerHTML = '<div class="section-header"><div><p class="eyebrow">Places</p><h2>Arrival & Departure Zones</h2><p class="muted">Owners can create or edit places and choose which transitions produce group notices.</p></div><button id="refreshGeofencesBtn" type="button" class="secondary">Refresh</button></div>' +
            '<form id="geofenceForm" class="profile-edit hidden">' +
            '<input id="geofenceZoneId" type="hidden">' +
            '<input id="geofenceLatitude" type="hidden">' +
            '<input id="geofenceLongitude" type="hidden">' +
            '<div class="settings-grid">' +
            '<label>Place name<input id="geofenceName" maxlength="80" placeholder="Home, School, Work" required></label>' +
            '<label>Radius<select id="geofenceRadius"><option value="100">100 meters</option><option value="250" selected>250 meters</option><option value="500">500 meters</option><option value="1000">1 kilometer</option><option value="2000">2 kilometers</option></select></label>' +
            '</div>' +
            '<label>Address or place<input id="geofenceAddress" maxlength="180" placeholder="123 Main St, Parma Heights, OH or Cleveland Hopkins Airport" autocomplete="street-address" required></label>' +
            '<div class="button-row"><button id="searchGeofenceAddressBtn" type="button" class="secondary">Find Address</button><button id="useMyLocationForZoneBtn" type="button" class="secondary">Use My Latest Location</button></div>' +
            '<div id="geofenceResolvedAddress" class="profile-edit-note">Search for an address or use your latest saved location.</div>' +
            '<details id="geofenceAdvanced"><summary>Advanced coordinates</summary><div class="settings-grid"><label>Latitude<input id="geofenceLatitudeAdvanced" type="number" step="any" inputmode="decimal" min="-90" max="90"></label><label>Longitude<input id="geofenceLongitudeAdvanced" type="number" step="any" inputmode="decimal" min="-180" max="180"></label></div><button id="applyAdvancedCoordinatesBtn" type="button" class="secondary">Use These Coordinates</button></details>' +
            '<div class="settings-grid"><label class="check-row"><input id="geofenceNotifyArrival" type="checkbox" checked><span>Create arrival notices</span></label><label class="check-row"><input id="geofenceNotifyDeparture" type="checkbox" checked><span>Create departure notices</span></label></div>' +
            '<div class="button-row"><button id="saveGeofenceBtn" type="submit">Create Place</button><button id="cancelGeofenceEditBtn" type="button" class="secondary hidden">Cancel Edit</button></div>' +
            '</form><div id="geofenceList" class="member-list">Loading places…</div>';
        var mapTools = $('mapToolsCard');
        if (mapTools && mapTools.nextSibling) mapTools.parentNode.insertBefore(card, mapTools.nextSibling);
        else main.appendChild(card);
        $('refreshGeofencesBtn').addEventListener('click', refresh);
        $('geofenceForm').addEventListener('submit', saveZone);
        $('cancelGeofenceEditBtn').addEventListener('click', resetForm);
        $('useMyLocationForZoneBtn').addEventListener('click', useMyLocation);
        $('searchGeofenceAddressBtn').addEventListener('click', searchAddress);
        $('applyAdvancedCoordinatesBtn').addEventListener('click', applyAdvancedCoordinates);
    }

    function formatDistance(meters) {
        var value = Number(meters || 0);
        if (value >= 1000) return (value / 1000).toFixed(1) + ' km';
        return Math.round(value) + ' m';
    }

    function notificationText(zone) {
        var arrival = zone.notifyArrival !== false;
        var departure = zone.notifyDeparture !== false;
        if (arrival && departure) return 'Arrival and departure notices on';
        if (arrival) return 'Arrival notices only';
        if (departure) return 'Departure notices only';
        return 'Notices off';
    }

    function setCoordinates(lat, lon, label) {
        var latitude = Number(lat);
        var longitude = Number(lon);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
        $('geofenceLatitude').value = latitude.toFixed(6);
        $('geofenceLongitude').value = longitude.toFixed(6);
        $('geofenceLatitudeAdvanced').value = latitude.toFixed(6);
        $('geofenceLongitudeAdvanced').value = longitude.toFixed(6);
        if (label) {
            lastAddressLookup = label;
            $('geofenceAddress').value = label;
            $('geofenceResolvedAddress').textContent = 'Resolved to: ' + label;
        }
        return true;
    }

    function geocodeAddress(query) {
        return fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&addressdetails=1&q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Address lookup failed.');
            return response.json();
        }).then(function (results) {
            if (!Array.isArray(results) || !results.length) throw new Error('Address or place was not found.');
            return results[0];
        });
    }

    function reverseGeocode(lat, lon) {
        return fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon), {
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Address lookup failed.');
            return response.json();
        }).then(function (result) {
            return result && result.display_name ? result.display_name : Number(lat).toFixed(6) + ', ' + Number(lon).toFixed(6);
        });
    }

    function searchAddress() {
        var query = ($('geofenceAddress').value || '').trim();
        if (!query) return status('Enter an address or place to search.');
        status('Searching for address…');
        geocodeAddress(query).then(function (result) {
            setCoordinates(result.lat, result.lon, result.display_name || query);
            status('Address found. Review the radius and save the place.');
        }).catch(function (error) { status(error.message || 'Could not find that address.'); });
    }

    function applyAdvancedCoordinates() {
        var lat = Number($('geofenceLatitudeAdvanced').value);
        var lon = Number($('geofenceLongitudeAdvanced').value);
        if (!Number.isFinite(lat) || lat < -90 || lat > 90 || !Number.isFinite(lon) || lon < -180 || lon > 180) {
            return status('Enter valid latitude and longitude values.');
        }
        setCoordinates(lat, lon, Number(lat).toFixed(6) + ', ' + Number(lon).toFixed(6));
        reverseGeocode(lat, lon).then(function (label) {
            setCoordinates(lat, lon, label);
            status('Coordinates resolved to an address.');
        }).catch(function () { status('Coordinates accepted.'); });
    }

    function render(data) {
        createUi();
        var form = $('geofenceForm');
        if (form) form.classList.toggle('hidden', role !== 'owner');
        var list = $('geofenceList');
        if (!list) return;
        list.innerHTML = '';
        var zones = data.zones || [];
        var statuses = data.statuses || [];
        if (!zones.length) {
            list.textContent = role === 'owner' ? 'No places yet. Create Home, School, Work, or another zone.' : 'The group owner has not created any places.';
            return;
        }
        zones.forEach(function (zone) {
            var card = document.createElement('article');
            card.className = 'member-card';
            var main = document.createElement('div');
            var title = document.createElement('div');
            title.className = 'member-name';
            title.textContent = zone.name;
            var meta = document.createElement('div');
            meta.className = 'member-meta';
            meta.textContent = (zone.address || (Number(zone.latitude).toFixed(5) + ', ' + Number(zone.longitude).toFixed(5))) + ' • Radius ' + formatDistance(zone.radiusMeters);
            var notices = document.createElement('div');
            notices.className = 'member-ident';
            notices.textContent = notificationText(zone);
            main.append(title, meta, notices);
            var zoneRows = statuses.filter(function (row) { return row.zoneId === zone.id; });
            zoneRows.forEach(function (row) {
                var line = document.createElement('div');
                line.className = 'member-ident';
                line.textContent = row.displayName + ': ' + (row.inside ? 'Inside' : 'Outside') + ' • ' + formatDistance(row.distanceMeters) + ' away';
                main.appendChild(line);
            });
            card.appendChild(main);
            if (role === 'owner') {
                var actions = document.createElement('div');
                actions.className = 'button-row';
                var edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'secondary';
                edit.textContent = 'Edit';
                edit.addEventListener('click', function () { beginEdit(zone); });
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'danger-button';
                remove.textContent = 'Delete';
                remove.addEventListener('click', function () { deleteZone(zone.id, zone.name); });
                actions.append(edit, remove);
                card.appendChild(actions);
            }
            list.appendChild(card);
        });
    }

    function refresh() {
        return Promise.all([request(null), loadMembers()]).then(function (results) { render(results[0]); })
            .catch(function (error) { status(error.message || 'Could not load places.'); });
    }

    function useMyLocation() {
        var accountText = ($('accountTitle') || {}).textContent || '';
        var name = accountText.replace(/\s*\([^)]*\)\s*$/, '').trim().toLowerCase();
        var member = latestMembers.find(function (item) {
            return String(item.displayName || item.displayLabel || '').trim().toLowerCase() === name;
        });
        if (!member || !member.location) return status('No saved location is available for this account yet.');
        var lat = Number(member.location.latitude);
        var lon = Number(member.location.longitude);
        setCoordinates(lat, lon, 'Looking up address…');
        status('Looking up the address for your latest location…');
        reverseGeocode(lat, lon).then(function (label) {
            setCoordinates(lat, lon, label);
            status('Latest saved location added as an address.');
        }).catch(function () {
            setCoordinates(lat, lon, lat.toFixed(6) + ', ' + lon.toFixed(6));
            status('Latest saved location copied. Address lookup was unavailable.');
        });
    }

    function beginEdit(zone) {
        editingZoneId = zone.id;
        $('geofenceZoneId').value = zone.id;
        $('geofenceName').value = zone.name || '';
        $('geofenceRadius').value = String(zone.radiusMeters || 250);
        setCoordinates(zone.latitude, zone.longitude, zone.address || 'Looking up address…');
        $('geofenceNotifyArrival').checked = zone.notifyArrival !== false;
        $('geofenceNotifyDeparture').checked = zone.notifyDeparture !== false;
        $('saveGeofenceBtn').textContent = 'Save Place';
        $('cancelGeofenceEditBtn').classList.remove('hidden');
        $('geofenceForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (!zone.address) {
            reverseGeocode(zone.latitude, zone.longitude).then(function (label) { setCoordinates(zone.latitude, zone.longitude, label); }).catch(function () { });
        }
        status('Editing ' + zone.name + '.');
    }

    function resetForm() {
        editingZoneId = '';
        lastAddressLookup = '';
        $('geofenceZoneId').value = '';
        $('geofenceName').value = '';
        $('geofenceAddress').value = '';
        $('geofenceLatitude').value = '';
        $('geofenceLongitude').value = '';
        $('geofenceLatitudeAdvanced').value = '';
        $('geofenceLongitudeAdvanced').value = '';
        $('geofenceResolvedAddress').textContent = 'Search for an address or use your latest saved location.';
        $('geofenceRadius').value = '250';
        $('geofenceNotifyArrival').checked = true;
        $('geofenceNotifyDeparture').checked = true;
        $('saveGeofenceBtn').textContent = 'Create Place';
        $('cancelGeofenceEditBtn').classList.add('hidden');
    }

    function saveZone(event) {
        event.preventDefault();
        var form = $('geofenceForm');
        if (!form || !form.reportValidity()) return;
        var latitude = Number($('geofenceLatitude').value);
        var longitude = Number($('geofenceLongitude').value);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return status('Find the address or use your latest location before saving.');
        }
        var payload = {
            action: editingZoneId ? 'update_zone' : 'create_zone',
            zoneId: editingZoneId,
            name: $('geofenceName').value.trim(),
            address: ($('geofenceAddress').value || lastAddressLookup || '').trim(),
            latitude: latitude,
            longitude: longitude,
            radiusMeters: Number($('geofenceRadius').value),
            notifyArrival: $('geofenceNotifyArrival').checked,
            notifyDeparture: $('geofenceNotifyDeparture').checked
        };
        request(payload).then(function (data) {
            resetForm();
            render(data);
            status(data.message || 'Place saved.');
        }).catch(function (error) { status(error.message || 'Could not save place.'); });
    }

    function deleteZone(zoneId, name) {
        if (!window.confirm('Delete the place "' + name + '"?')) return;
        request({ action: 'delete_zone', zoneId: zoneId }).then(function (data) {
            if (editingZoneId === zoneId) resetForm();
            render(data);
            status(data.message || 'Place deleted.');
        }).catch(function (error) { status(error.message || 'Could not delete place.'); });
    }

    function boot() {
        createUi();
        refresh();
        window.setInterval(refresh, 60000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
