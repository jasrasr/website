/**
 * Project: Family GPS Tracker
 * File: assets/js/presence.js
 * Revision: 1.5.0
 * Description: Check-in, trip/ETA sharing, and recent presence activity UI behavior.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';
    var data = null;

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText'); if (node) node.textContent = text; }
    function fmt(value) { return value ? new Date(value).toLocaleString() : 'Unknown time'; }

    function get() {
        return fetch('presence.php', { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) throw new Error(payload.error || 'Presence request failed.');
                if (payload.csrfToken) csrfToken = payload.csrfToken;
                return payload;
            });
        });
    }

    function post(payload) {
        return fetch('presence.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.ok) throw new Error(result.error || 'Presence request failed.');
                if (result.csrfToken) csrfToken = result.csrfToken;
                return result;
            });
        });
    }

    function card(title, meta, badge) {
        var item = document.createElement('article');
        item.className = 'member-card';
        var main = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'member-name';
        name.textContent = title;
        var details = document.createElement('div');
        details.className = 'member-meta';
        details.textContent = meta;
        main.append(name, details);
        item.appendChild(main);
        if (badge) {
            var flag = document.createElement('span');
            flag.className = badge === 'Need Help' ? 'badge stale' : 'badge';
            flag.textContent = badge;
            item.appendChild(flag);
        }
        return item;
    }

    function renderMembers() {
        var list = $('presenceMemberList');
        if (!list) return;
        list.innerHTML = '';
        var members = data.members || [];
        if (!members.length) {
            list.textContent = 'No members available.';
            return;
        }
        members.forEach(function (member) {
            var pieces = [];
            var badge = '';
            if (member.checkIn) {
                pieces.push((member.checkIn.label || 'Checked In') + ' at ' + fmt(member.checkIn.updatedAt));
                if (member.checkIn.note) pieces.push(member.checkIn.note);
                badge = member.checkIn.label || 'Check In';
            }
            if (member.trip && member.trip.active) {
                pieces.push('Trip to ' + member.trip.destination + ' • ETA ' + fmt(member.trip.estimatedArrivalAt));
                if (member.trip.note) pieces.push(member.trip.note);
                badge = 'Trip Active';
            }
            if (!pieces.length) pieces.push('No recent check-in or active trip.');
            list.appendChild(card(member.displayName || member.username || 'Member', pieces.join(' • '), badge));
        });
    }

    function renderActivity() {
        var list = $('presenceActivityList');
        if (!list) return;
        list.innerHTML = '';
        var items = data.activity || [];
        if (!items.length) {
            list.textContent = 'No recent check-in or trip activity.';
            return;
        }
        items.forEach(function (item) {
            list.appendChild(card(item.message || 'Activity', fmt(item.createdAt), item.type === 'trip_started' ? 'Trip' : 'Check In'));
        });
    }

    function render() {
        renderMembers();
        renderActivity();
    }

    function load() {
        return get().then(function (result) {
            data = result;
            render();
        }).catch(function () { });
    }

    function sendCheckIn(checkInStatus) {
        var note = (($('checkInNote') || {}).value || '').trim();
        status('Sharing check-in...');
        post({ action: 'check_in', status: checkInStatus, note: note }).then(function (result) {
            data = result;
            if ($('checkInNote')) $('checkInNote').value = '';
            render();
            status(result.message || 'Check-in shared.');
        }).catch(function (error) { status(error.message || 'Check-in failed.'); });
    }

    function startTrip(event) {
        event.preventDefault();
        var destination = $('tripDestination').value.trim();
        var etaMinutes = Number($('tripEtaMinutes').value || 0);
        var note = $('tripNote').value.trim();
        status('Starting trip sharing...');
        post({ action: 'start_trip', destination: destination, etaMinutes: etaMinutes, note: note }).then(function (result) {
            data = result;
            render();
            status(result.message || 'Trip sharing started.');
        }).catch(function (error) { status(error.message || 'Could not start trip sharing.'); });
    }

    function endTrip() {
        if (!window.confirm('End your active trip sharing?')) return;
        post({ action: 'end_trip' }).then(function (result) {
            data = result;
            render();
            status(result.message || 'Trip sharing ended.');
        }).catch(function (error) { status(error.message || 'Could not end trip sharing.'); });
    }

    function boot() {
        document.querySelectorAll('[data-check-in]').forEach(function (button) {
            button.addEventListener('click', function () { sendCheckIn(button.getAttribute('data-check-in')); });
        });
        $('tripShareForm').addEventListener('submit', startTrip);
        $('endTripBtn').addEventListener('click', endTrip);
        $('refreshPresenceBtn').addEventListener('click', load);
        load();
        window.setInterval(load, 30000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
