/**
 * Project: Family GPS Tracker
 * File: assets/js/groups.js
 * Revision: 1.4.0
 * Description: Multi-circle/group UI for creating, joining, and switching active groups.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */
(function () {
    'use strict';

    var csrfToken = '';
    var activeGroups = [];

    function css() {
        if (document.getElementById('family-tracker-groups-style')) return;
        var style = document.createElement('style');
        style.id = 'family-tracker-groups-style';
        style.textContent = [
            '.groups-card{display:grid;gap:.75rem}',
            '.groups-card h2{margin-bottom:.25rem}',
            '.groups-row{display:grid;grid-template-columns:1fr auto;gap:.6rem;align-items:end}',
            '.groups-card button{width:auto}',
            '.groups-mini{color:var(--muted);font-size:.9rem}',
            '.groups-code{display:none;margin-top:.35rem}',
            '.groups-code.visible{display:block}',
            '.groups-code code{width:100%;overflow-wrap:anywhere;text-align:center}',
            '@media(max-width:850px){.groups-row{grid-template-columns:1fr}.groups-card button{width:100%}}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function setStatus(text) {
        var status = document.getElementById('statusText');
        if (status) status.textContent = text;
    }

    function api(payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('groups.php', options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Group request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function ensureCard() {
        var existing = document.getElementById('groupsCard');
        if (existing) return existing;
        var account = document.querySelector('.account-card');
        if (!account) return null;

        var card = document.createElement('section');
        card.id = 'groupsCard';
        card.className = 'card groups-card';
        card.innerHTML = '<div><p class="eyebrow">Groups / Circles</p><h2>Active Group</h2><p class="groups-mini">Create separate circles for family, friends, trips, or other groups. The selected group controls the map, invite code, and member list.</p></div><label>Current group<select id="activeGroupSelect"></select></label><div class="groups-row"><label>Create new group<input id="newGroupName" maxlength="80" placeholder="Friends, Road Trip, Youth Group"></label><button id="createGroupBtn" type="button" class="secondary">Create</button></div><div class="groups-row"><label>Join another group<input id="joinGroupCode" maxlength="40" placeholder="ABCDE-12345"></label><button id="joinGroupBtn" type="button" class="secondary">Join</button></div><div id="newGroupCodeBox" class="groups-code"><p class="groups-mini">Save this new group invite code now:</p><code id="newGroupInviteCode"></code></div>';
        account.insertAdjacentElement('afterend', card);

        document.getElementById('activeGroupSelect').addEventListener('change', function (event) {
            switchGroup(event.target.value);
        });
        document.getElementById('createGroupBtn').addEventListener('click', createGroup);
        document.getElementById('joinGroupBtn').addEventListener('click', joinGroup);
        return card;
    }

    function activeGroup(data) {
        var groups = data.groups || activeGroups || [];
        for (var i = 0; i < groups.length; i++) {
            if (groups[i].isActive) return groups[i];
        }
        return groups[0] || null;
    }

    function syncMainUi(data) {
        var group = activeGroup(data);
        if (!group) return;
        var familyTitle = document.getElementById('familyTitle');
        if (familyTitle) familyTitle.textContent = group.name + ' • ' + group.role;

        var inviteCard = document.getElementById('inviteCard');
        var inviteDisplay = document.getElementById('inviteCodeDisplay');
        if (inviteCard) {
            if (group.role === 'owner') inviteCard.classList.remove('hidden');
            else inviteCard.classList.add('hidden');
        }
        if (inviteDisplay && group.role === 'owner') {
            inviteDisplay.textContent = group.inviteCodeLast4 ? 'Last code ended in ' + group.inviteCodeLast4 : 'No invite metadata yet';
        }

        var refresh = document.getElementById('refreshBtn');
        if (refresh) refresh.click();
    }

    function render(data) {
        var card = ensureCard();
        if (!card) return;
        activeGroups = data.groups || [];
        var select = document.getElementById('activeGroupSelect');
        select.innerHTML = '';
        activeGroups.forEach(function (group) {
            var option = document.createElement('option');
            option.value = group.id;
            option.textContent = group.name + ' (' + group.role + ')';
            option.selected = !!group.isActive;
            select.appendChild(option);
        });
        syncMainUi(data);
    }

    function loadGroups() {
        return api(null).then(render).catch(function () {
            // The user may not be signed in yet. The main app handles auth display.
        });
    }

    function switchGroup(groupId) {
        if (!groupId) return;
        setStatus('Switching group...');
        api({ action: 'switch_group', groupId: groupId }).then(function (data) {
            render(data);
            setStatus('Active group switched.');
        }).catch(function (error) {
            setStatus(error.message || 'Could not switch group.');
            loadGroups();
        });
    }

    function createGroup() {
        var input = document.getElementById('newGroupName');
        var name = input ? input.value.trim() : '';
        if (!name) return setStatus('Group name is required.');
        setStatus('Creating group...');
        api({ action: 'create_group', groupName: name }).then(function (data) {
            if (input) input.value = '';
            render(data);
            if (data.oneTimeInviteCode) {
                var box = document.getElementById('newGroupCodeBox');
                var code = document.getElementById('newGroupInviteCode');
                if (box && code) {
                    code.textContent = data.oneTimeInviteCode;
                    box.classList.add('visible');
                }
                var inviteDisplay = document.getElementById('inviteCodeDisplay');
                if (inviteDisplay) inviteDisplay.textContent = data.oneTimeInviteCode;
                setStatus('Group created. Save the invite code: ' + data.oneTimeInviteCode);
            } else {
                setStatus('Group created.');
            }
        }).catch(function (error) {
            setStatus(error.message || 'Could not create group.');
        });
    }

    function joinGroup() {
        var input = document.getElementById('joinGroupCode');
        var code = input ? input.value.trim() : '';
        if (!code) return setStatus('Invite code is required.');
        setStatus('Joining group...');
        api({ action: 'join_group', inviteCode: code }).then(function (data) {
            if (input) input.value = '';
            render(data);
            setStatus('Joined and switched to group.');
        }).catch(function (error) {
            setStatus(error.message || 'Could not join group.');
        });
    }

    function boot() {
        css();
        loadGroups();
        window.setInterval(loadGroups, 30000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
