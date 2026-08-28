/**
 * Project: Family GPS Tracker
 * File: assets/js/trail-status.js
 * Revision: 1.5.1
 * Description: Trail-retention controls and live/stale location transition monitoring.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';
    var latest = null;

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('statusText'); if (node) node.textContent = text; }

    function getData() {
        return fetch('trail-status.php', { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Trail status request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function postData(payload) {
        return fetch('trail-status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Trail status request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function countCard(label, value, badgeClass) {
        var card = document.createElement('article');
        card.className = 'member-card';
        var main = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'member-name';
        name.textContent = label;
        var meta = document.createElement('div');
        meta.className = 'member-meta';
        meta.textContent = String(value);
        main.append(name, meta);
        var badge = document.createElement('span');
        badge.className = 'badge' + (badgeClass ? ' ' + badgeClass : '');
        badge.textContent = String(value);
        card.append(main, badge);
        return card;
    }

    function render(data) {
        latest = data;
        var list = $('trailStatusCounts');
        if (list) {
            list.innerHTML = '';
            list.append(
                countCard('Live / Recent', data.counts.live || 0, ''),
                countCard('Stale', data.counts.stale || 0, 'stale'),
                countCard('No Location', data.counts.missing || 0, 'missing'),
                countCard('Stored Trail Points', data.trailPointCount || 0, '')
            );
        }

        var select = $('trailRetentionSelect');
        var ownerPanel = $('trailOwnerControls');
        if (select) select.value = String(data.retentionHours || 168);
        if (ownerPanel) {
            if (data.role === 'owner') ownerPanel.classList.remove('hidden');
            else ownerPanel.classList.add('hidden');
        }

        var text = $('trailRetentionSummary');
        if (text) {
            text.textContent = 'Retention: ' + retentionLabel(data.retentionHours) + ' • ' + (data.trailPointCount || 0) + ' stored points in the active group.';
        }
    }

    function retentionLabel(hours) {
        var map = { 24: '24 hours', 168: '7 days', 720: '30 days', 2160: '90 days' };
        return map[Number(hours)] || String(hours) + ' hours';
    }

    function load() {
        return getData().then(render).catch(function (error) {
            status(error.message || 'Could not load trail settings.');
        });
    }

    function monitor() {
        if (!csrfToken) return load().then(monitor);
        return postData({ action: 'monitor_status' }).then(function (data) {
            render(data);
        }).catch(function () { });
    }

    function saveRetention() {
        var select = $('trailRetentionSelect');
        if (!select) return;
        status('Saving trail retention...');
        postData({ action: 'save_retention', retentionHours: Number(select.value) }).then(function (data) {
            render(data);
            status(data.message || 'Trail retention updated.');
        }).catch(function (error) { status(error.message || 'Could not update retention.'); });
    }

    function cleanup() {
        if (!window.confirm('Delete trail points older than the selected retention period for the active group?')) return;
        status('Cleaning old trail points...');
        postData({ action: 'cleanup_group_trails' }).then(function (data) {
            render(data);
            status((data.message || 'Trail cleanup complete.') + ' Removed ' + (data.removedTrailPoints || 0) + ' points.');
        }).catch(function (error) { status(error.message || 'Trail cleanup failed.'); });
    }

    function boot() {
        var save = $('saveTrailRetentionBtn');
        var clean = $('cleanupGroupTrailsBtn');
        var refresh = $('refreshTrailStatusBtn');
        if (save) save.addEventListener('click', saveRetention);
        if (clean) clean.addEventListener('click', cleanup);
        if (refresh) refresh.addEventListener('click', function () { load().then(monitor); });
        load().then(monitor);
        window.setInterval(monitor, 60000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
