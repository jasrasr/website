/**
 * Project: Family GPS Tracker
 * File: assets/js/owner-dashboard.js
 * Revision: 1.4.9
 * Description: Owner dashboard behavior for settings, retention, ownership, activity, audit, export, and guarded deletion.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';
    var data = null;

    function $(id) { return document.getElementById(id); }
    function status(text) { var node = $('ownerStatus'); if (node) node.textContent = text; }
    function fmtTime(value) { return value ? new Date(value).toLocaleString() : 'Unknown time'; }

    function get(query) {
        return fetch('owner-admin.php' + (query || ''), { credentials: 'same-origin' }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) throw new Error(payload.error || 'Owner request failed.');
                if (payload.csrfToken) csrfToken = payload.csrfToken;
                return payload;
            });
        });
    }

    function post(payload) {
        return fetch('owner-admin.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.ok) throw new Error(result.error || 'Owner request failed.');
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
            flag.className = 'badge';
            flag.textContent = badge;
            item.appendChild(flag);
        }
        return item;
    }

    function renderSettings() {
        var family = data.family || {};
        $('ownerGroupTitle').textContent = family.name || 'Active Group';
        $('ownerGroupName').value = family.name || '';
        $('ownerGroupDescription').value = family.description || '';
        $('ownerGroupColor').value = family.color || '#4ADE80';
        $('ownerTrailRetention').value = String(family.trailRetentionDays == null ? 30 : family.trailRetentionDays);
        $('ownerDeleteConfirmation').placeholder = family.name || 'Exact group name';
        $('ownerDeleteGroupBtn').disabled = !data.canDeleteGroup;
    }

    function renderMembers() {
        var list = $('ownerMemberList');
        var select = $('ownerTransferMember');
        list.innerHTML = '';
        select.innerHTML = '';
        (data.members || []).forEach(function (member) {
            var label = member.displayLabel || member.displayName || member.username || 'Unknown';
            var meta = '@' + (member.username || 'unknown') + ' • ' + (member.role || 'member') + ' • joined ' + (member.joinedAt ? new Date(member.joinedAt).toLocaleDateString() : 'unknown');
            list.appendChild(card(label, meta, member.role === 'owner' ? 'Owner' : 'Member'));
            if (member.id !== data.currentUserId) {
                var option = document.createElement('option');
                option.value = member.id;
                option.textContent = label;
                select.appendChild(option);
            }
        });
        if (!select.options.length) {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'No eligible member';
            select.appendChild(empty);
        }
    }

    function renderActivity() {
        var list = $('ownerActivityList');
        list.innerHTML = '';
        var items = data.activity || [];
        if (!items.length) {
            list.textContent = 'No recorded group activity yet.';
            return;
        }
        items.forEach(function (item) {
            list.appendChild(card(item.message || 'Group activity', fmtTime(item.time), item.type || 'Activity'));
        });
    }

    function renderAudit() {
        var list = $('ownerAuditList');
        var needle = (($('ownerAuditFilter') || {}).value || '').trim().toLowerCase();
        list.innerHTML = '';
        var items = (data.audit || []).filter(function (item) {
            return !needle || JSON.stringify(item).toLowerCase().indexOf(needle) >= 0;
        });
        if (!items.length) {
            list.textContent = needle ? 'No audit records match the filter.' : 'No audit records found for this group.';
            return;
        }
        items.forEach(function (item) {
            var details = Object.keys(item.data || {}).filter(function (key) { return key !== 'familyId'; }).map(function (key) { return key + ': ' + item.data[key]; }).join(' • ');
            list.appendChild(card(item.event || 'unknown', fmtTime(item.time) + (details ? ' • ' + details : ''), 'Audit'));
        });
    }

    function render() {
        renderSettings();
        renderMembers();
        renderActivity();
        renderAudit();
        status('Owner dashboard loaded for ' + (data.family.name || 'active group') + '.');
    }

    function load() {
        status('Loading owner dashboard...');
        return get('').then(function (result) {
            data = result;
            render();
        }).catch(function (error) { status(error.message || 'Could not load owner dashboard.'); });
    }

    function saveSettings(event) {
        event.preventDefault();
        status('Saving group settings...');
        post({
            action: 'update_group_settings',
            name: $('ownerGroupName').value.trim(),
            description: $('ownerGroupDescription').value.trim(),
            color: $('ownerGroupColor').value,
            trailRetentionDays: Number($('ownerTrailRetention').value)
        }).then(function (result) {
            data = result;
            render();
            status(result.message || 'Group settings updated.');
        }).catch(function (error) { status(error.message || 'Could not save group settings.'); });
    }

    function cleanupTrails() {
        var days = Number($('ownerTrailRetention').value);
        if (!window.confirm(days === 0 ? 'Retention is unlimited. Run cleanup anyway?' : 'Delete trail points older than ' + days + ' day(s) for this group?')) return;
        status('Cleaning old trail points...');
        post({ action: 'cleanup_trails' }).then(function (result) {
            data = result;
            render();
            status(result.message || 'Trail cleanup complete.');
        }).catch(function (error) { status(error.message || 'Trail cleanup failed.'); });
    }

    function transferOwnership() {
        var memberId = $('ownerTransferMember').value;
        if (!memberId) return status('No eligible member selected.');
        if (!window.confirm('Transfer ownership now? You will become a regular member of this group.')) return;
        post({ action: 'transfer_ownership', memberId: memberId }).then(function (result) {
            status(result.message || 'Ownership transferred.');
            window.setTimeout(function () { window.location.href = 'index.php'; }, 1200);
        }).catch(function (error) { status(error.message || 'Ownership transfer failed.'); });
    }

    function exportGroup() {
        get('?action=export_group').then(function (result) {
            var blob = new Blob([JSON.stringify(result.export || {}, null, 2)], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'family-tracker-group-export.json';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            status('Group export prepared.');
        }).catch(function (error) { status(error.message || 'Group export failed.'); });
    }

    function deleteGroup() {
        if (!data || !data.canDeleteGroup) return status('Create or join another group before deleting this one.');
        var confirmation = $('ownerDeleteConfirmation').value.trim();
        if (confirmation !== data.family.name) return status('Type the exact group name to confirm deletion.');
        if (!window.confirm('Permanently delete this group and its matching saved locations and trails?')) return;
        status('Deleting active group...');
        post({ action: 'delete_group', confirmation: confirmation }).then(function (result) {
            status(result.message || 'Group deleted.');
            window.setTimeout(function () { window.location.href = result.redirect || 'index.php'; }, 700);
        }).catch(function (error) { status(error.message || 'Group deletion failed.'); });
    }

    function boot() {
        $('ownerGroupSettingsForm').addEventListener('submit', saveSettings);
        $('ownerCleanupTrailsBtn').addEventListener('click', cleanupTrails);
        $('ownerTransferBtn').addEventListener('click', transferOwnership);
        $('ownerRefreshBtn').addEventListener('click', load);
        $('ownerAuditFilter').addEventListener('input', renderAudit);
        $('ownerExportBtn').addEventListener('click', exportGroup);
        $('ownerDeleteGroupBtn').addEventListener('click', deleteGroup);
        load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
