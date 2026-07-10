/**
 * Project: Family GPS Tracker
 * File: assets/js/member-management.js
 * Revision: 1.4.4
 * Description: Owner-only active-group member nickname, relationship, color, duplicate-name warning, and remove-from-group controls.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */
(function () {
    'use strict';

    var csrfToken = '';
    var currentUserId = '';

    function status(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function api(payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('member-management.php', options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Member-management request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function refreshMainList() {
        var refresh = document.getElementById('refreshBtn');
        if (refresh) refresh.click();
        window.dispatchEvent(new CustomEvent('familyTrackerMemberUiRefresh'));
    }

    function cleanColor(value) {
        return /^#[0-9a-fA-F]{6}$/.test(value || '') ? value : '#6B7280';
    }

    function text(value) {
        return value == null ? '' : String(value);
    }

    function metaLine(member) {
        var parts = [];
        parts.push('@' + (member.username || 'unknown'));
        parts.push('Role: ' + (member.role || 'member'));
        if (member.joinedAt) parts.push('Joined: ' + new Date(member.joinedAt).toLocaleDateString());
        if (member.hasDuplicateDisplayLabel) parts.push('Duplicate name warning');
        return parts.join(' • ');
    }

    function renderMember(member) {
        var profile = member.groupProfile || {};
        var card = document.createElement('article');
        card.className = 'member-card member-management-card';
        card.dataset.memberId = member.id || '';

        var main = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'member-name';
        title.textContent = (member.displayLabel || member.displayName || member.username || 'Unknown') + (member.id === currentUserId ? ' (you)' : '');

        var meta = document.createElement('div');
        meta.className = 'member-meta';
        meta.textContent = metaLine(member);

        var grid = document.createElement('div');
        grid.className = 'member-management-grid';
        grid.innerHTML = '<label>Nickname<input class="member-nickname" maxlength="80"></label><label>Relationship<select class="member-relationship"><option value="">None</option><option>Dad</option><option>Mom</option><option>Child</option><option>Grandparent</option><option>Friend</option><option>Other</option></select></label><label>Map color<input class="member-color" type="color"></label>';
        grid.querySelector('.member-nickname').value = text(profile.nickname);
        var relation = grid.querySelector('.member-relationship');
        relation.value = text(profile.relationship);
        if (relation.value !== text(profile.relationship) && text(profile.relationship)) {
            var option = document.createElement('option');
            option.value = text(profile.relationship);
            option.textContent = text(profile.relationship);
            relation.appendChild(option);
            relation.value = text(profile.relationship);
        }
        grid.querySelector('.member-color').value = cleanColor(profile.color);

        var actions = document.createElement('div');
        actions.className = 'button-row';
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'secondary';
        save.textContent = 'Save Member Settings';
        save.addEventListener('click', function () { saveMember(card); });
        actions.appendChild(save);

        if (member.id !== currentUserId && member.role !== 'owner') {
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'danger-button';
            remove.textContent = 'Remove From Group';
            remove.addEventListener('click', function () { removeMember(member); });
            actions.appendChild(remove);
        }

        main.append(title, meta, grid, actions);
        var badge = document.createElement('span');
        badge.className = 'badge' + (member.hasDuplicateDisplayLabel ? ' stale' : '');
        badge.textContent = member.hasDuplicateDisplayLabel ? 'Duplicate' : (member.role || 'member');
        card.append(main, badge);
        return card;
    }

    function render(data) {
        var card = document.getElementById('ownerMemberManagementCard');
        var list = document.getElementById('ownerMemberManagementList');
        if (!card || !list) return;
        currentUserId = data.currentUserId || '';
        if (!data.isOwner) {
            card.classList.add('hidden');
            return;
        }
        card.classList.remove('hidden');
        list.innerHTML = '';
        var members = data.members || [];
        if (!members.length) {
            list.textContent = 'No members found.';
            return;
        }
        members.forEach(function (member) { list.appendChild(renderMember(member)); });
    }

    function load() {
        api(null).then(render).catch(function () {
            var card = document.getElementById('ownerMemberManagementCard');
            if (card) card.classList.add('hidden');
        });
    }

    function saveMember(card) {
        var memberId = card.dataset.memberId || '';
        var nickname = card.querySelector('.member-nickname').value.trim();
        var relationship = card.querySelector('.member-relationship').value.trim();
        var color = card.querySelector('.member-color').value.trim();
        status('Saving member settings...');
        api({ action: 'update_member_profile', memberId: memberId, nickname: nickname, relationship: relationship, color: color })
            .then(function (data) {
                render(data);
                refreshMainList();
                status(data.message || 'Member settings saved.');
            }).catch(function (error) { status(error.message || 'Could not save member settings.'); });
    }

    function removeMember(member) {
        var label = member.displayLabel || member.displayName || member.username || 'this member';
        if (!window.confirm('Remove ' + label + ' from the active group? Their account will not be deleted.')) return;
        status('Removing member from group...');
        api({ action: 'remove_member', memberId: member.id })
            .then(function (data) {
                render(data);
                refreshMainList();
                status(data.message || 'Member removed from group.');
            }).catch(function (error) { status(error.message || 'Could not remove member.'); });
    }

    function boot() {
        load();
        window.setInterval(load, 45000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
