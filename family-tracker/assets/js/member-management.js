/**
 * Project: Family GPS Tracker
 * File: assets/js/member-management.js
 * Revision: 1.5.2
 * Description: Owner member settings, temporary disable/restore, remove-from-group, and member leave-group controls.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-11
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

    function cleanColor(value) { return /^#[0-9a-fA-F]{6}$/.test(value || '') ? value : '#6B7280'; }
    function text(value) { return value == null ? '' : String(value); }

    function metaLine(member) {
        var parts = [];
        parts.push('@' + (member.username || 'unknown'));
        parts.push('Role: ' + (member.role || 'member'));
        if (member.joinedAt) parts.push('Joined: ' + new Date(member.joinedAt).toLocaleDateString());
        if (member.isDisabledInGroup) parts.push('Temporarily disabled');
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
            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = member.isDisabledInGroup ? 'secondary' : 'danger-button';
            toggle.textContent = member.isDisabledInGroup ? 'Restore To Group' : 'Temporarily Disable';
            toggle.addEventListener('click', function () { toggleMember(member); });
            actions.appendChild(toggle);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'danger-button';
            remove.textContent = 'Remove Permanently';
            remove.addEventListener('click', function () { removeMember(member); });
            actions.appendChild(remove);
        }

        main.append(title, meta, grid, actions);
        var badge = document.createElement('span');
        badge.className = 'badge' + (member.isDisabledInGroup || member.hasDuplicateDisplayLabel ? ' stale' : '');
        badge.textContent = member.isDisabledInGroup ? 'Disabled' : (member.hasDuplicateDisplayLabel ? 'Duplicate' : (member.role || 'member'));
        card.append(main, badge);
        return card;
    }

    function render(data) {
        var card = document.getElementById('ownerMemberManagementCard');
        var list = document.getElementById('ownerMemberManagementList');
        var leaveCard = document.getElementById('leaveGroupCard');
        currentUserId = data.currentUserId || '';
        if (leaveCard) {
            if (data.canLeave) leaveCard.classList.remove('hidden');
            else leaveCard.classList.add('hidden');
        }
        if (!card || !list) return;
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
        status('Saving member settings...');
        api({
            action: 'update_member_profile',
            memberId: card.dataset.memberId || '',
            nickname: card.querySelector('.member-nickname').value.trim(),
            relationship: card.querySelector('.member-relationship').value.trim(),
            color: card.querySelector('.member-color').value.trim()
        }).then(function (data) {
            render(data);
            refreshMainList();
            status(data.message || 'Member settings saved.');
        }).catch(function (error) { status(error.message || 'Could not save member settings.'); });
    }

    function toggleMember(member) {
        var label = member.displayLabel || member.displayName || member.username || 'this member';
        var action = member.isDisabledInGroup ? 'restore_member' : 'disable_member';
        var prompt = member.isDisabledInGroup ? 'Restore ' + label + ' to the active group?' : 'Temporarily disable ' + label + '? They will lose access until restored.';
        if (!window.confirm(prompt)) return;
        status(member.isDisabledInGroup ? 'Restoring member...' : 'Disabling member...');
        api({ action: action, memberId: member.id }).then(function (data) {
            render(data);
            refreshMainList();
            status(data.message || 'Member status updated.');
        }).catch(function (error) { status(error.message || 'Could not update member status.'); });
    }

    function removeMember(member) {
        var label = member.displayLabel || member.displayName || member.username || 'this member';
        if (!window.confirm('Permanently remove ' + label + ' from the active group? Their account will not be deleted.')) return;
        status('Removing member from group...');
        api({ action: 'remove_member', memberId: member.id }).then(function (data) {
            render(data);
            refreshMainList();
            status(data.message || 'Member removed from group.');
        }).catch(function (error) { status(error.message || 'Could not remove member.'); });
    }

    function leaveGroup() {
        if (!window.confirm('Leave the active group? You will need a new invite to rejoin.')) return;
        status('Leaving group...');
        api({ action: 'leave_group' }).then(function (data) {
            status(data.message || 'You left the group.');
            window.setTimeout(function () { window.location.reload(); }, 500);
        }).catch(function (error) { status(error.message || 'Could not leave group.'); });
    }

    function boot() {
        var leave = document.getElementById('leaveGroupBtn');
        if (leave) leave.addEventListener('click', leaveGroup);
        load();
        window.setInterval(load, 45000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
