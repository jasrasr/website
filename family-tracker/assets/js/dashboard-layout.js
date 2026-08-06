/**
 * Project: Family GPS Tracker
 * File: assets/js/dashboard-layout.js
 * Revision: 1.6.10
 * Description: Reorders the tracker around the map, groups secondary tools into collapsed sections, and keeps compact navigation items consistently sized.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-13
 * Modified: 2026-08-06
 */
(function () {
    'use strict';

    var organizing = false;
    var queued = false;

    function $(id) { return document.getElementById(id); }

    function installStyles() {
        if ($('family-tracker-dashboard-layout-style')) return;
        var style = document.createElement('style');
        style.id = 'family-tracker-dashboard-layout-style';
        style.textContent = [
            '#trackerApp{display:flex;flex-direction:column}',
            '#trackerPrimaryNav{position:sticky;top:5.4rem;z-index:420;padding:.65rem;margin:.65rem 0;display:flex;align-items:center;gap:.5rem;overflow-x:auto;background:rgba(11,16,32,.94);backdrop-filter:blur(14px)}',
            '#trackerPrimaryNav a,#trackerPrimaryNav button{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto!important;width:auto!important;min-width:max-content;max-width:none;white-space:nowrap;border:1px solid var(--border);border-radius:999px;padding:.55rem .8rem;background:var(--panel);color:var(--text);text-decoration:none;font-weight:800;box-shadow:none}',
            '.tracker-section-group{padding:0;overflow:hidden}',
            '.tracker-section-group>summary{cursor:pointer;list-style:none;padding:1rem;font-size:1.1rem;font-weight:900;display:flex;align-items:center;justify-content:space-between;gap:.75rem}',
            '.tracker-section-group>summary::-webkit-details-marker{display:none}',
            '.tracker-section-group>summary::after{content:"Show";font-size:.78rem;color:var(--accent);text-transform:uppercase;letter-spacing:.06em}',
            '.tracker-section-group[open]>summary::after{content:"Hide"}',
            '.tracker-section-group-content{padding:0 .75rem .75rem}',
            '.tracker-section-group-content>.card{margin:.65rem 0;box-shadow:none}',
            '#trackerMapSection{order:10}',
            '#trackerMetricsSection{order:11}',
            '#trackerSharingSection{order:12}',
            '#trackerMembersSection{order:13}',
            '#trackerQuickDetailSection{order:14}',
            '#trackerGroupsPanel{order:30}',
            '#trackerAccountPanel{order:31}',
            '#trackerAdvancedPanel{order:32}',
            '@media(max-width:850px){.hero-copy{display:none}.hero{padding-bottom:.3rem}.hero h1{font-size:2.15rem}#trackerPrimaryNav{top:4.75rem;margin:.4rem 0}.map-card{margin-top:.5rem}#map{height:22rem;min-height:22rem}.dashboard-grid{margin:.45rem 0}.tracker-section-group>summary{padding:.85rem}}',
            'html[data-appearance="light"] #trackerPrimaryNav{background:rgba(238,242,247,.95)}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function directSectionFor(node) {
        if (!node) return null;
        return node.closest('section');
    }

    function createNav(main, account) {
        var nav = $('trackerPrimaryNav');
        if (!nav) {
            nav = document.createElement('nav');
            nav.id = 'trackerPrimaryNav';
            nav.setAttribute('aria-label', 'Tracker navigation');
            nav.innerHTML = '<a href="#trackerMapSection">Map</a><a href="#trackerMembersSection">Members</a><a href="#trackerSharingSection">Sharing</a><button type="button" data-open-panel="trackerGroupsPanel">Groups</button><button type="button" data-open-panel="trackerAccountPanel">Account</button><button type="button" data-open-panel="trackerAdvancedPanel">More</button><a href="history.php">History</a><a href="owner-dashboard.php" id="ownerDashboardNavLink">Owner</a>';
            nav.addEventListener('click', function (event) {
                var button = event.target.closest('[data-open-panel]');
                if (!button) return;
                var panel = $(button.getAttribute('data-open-panel'));
                if (panel) {
                    panel.open = true;
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
        if (nav.parentNode !== main || nav.previousElementSibling !== account) {
            account.insertAdjacentElement('afterend', nav);
        }
        return nav;
    }

    function ensurePanel(main, id, title) {
        var details = $(id);
        if (!details) {
            details = document.createElement('details');
            details.id = id;
            details.className = 'card tracker-section-group';
            var summary = document.createElement('summary');
            summary.textContent = title;
            var content = document.createElement('div');
            content.className = 'tracker-section-group-content';
            details.append(summary, content);
            main.appendChild(details);
        }
        return details;
    }

    function movePrimary(main, section, id, afterNode) {
        if (!section) return afterNode;
        section.id = id;
        if (section.parentNode !== main || section.previousElementSibling !== afterNode) {
            afterNode.insertAdjacentElement('afterend', section);
        }
        return section;
    }

    function moveToPanel(panel, element) {
        if (!element || element === panel) return;
        var content = panel.querySelector('.tracker-section-group-content');
        if (element.parentNode !== content) content.appendChild(element);
    }

    function findMemberSection() {
        return directSectionFor($('memberList'));
    }

    function findRealityCard(main) {
        var cards = Array.from(main.querySelectorAll(':scope > section.warning-card'));
        return cards.find(function (section) { return /reality check/i.test(section.textContent || ''); }) || null;
    }

    function organize() {
        if (organizing) return;
        organizing = true;
        try {
            installStyles();
            var main = $('trackerApp');
            if (!main) return;
            var account = main.querySelector(':scope > .account-card') || main.querySelector('.account-card');
            if (!account) return;

            var nav = createNav(main, account);
            var mapSection = main.querySelector(':scope > .map-card') || main.querySelector('.map-card');
            var metrics = main.querySelector(':scope > .dashboard-grid') || main.querySelector('.dashboard-grid');
            var sharing = main.querySelector(':scope > .controls-card') || main.querySelector('.controls-card');
            var members = findMemberSection();
            var quickDetail = $('memberQuickDetailCard');

            var cursor = nav;
            cursor = movePrimary(main, mapSection, 'trackerMapSection', cursor);
            cursor = movePrimary(main, metrics, 'trackerMetricsSection', cursor);
            cursor = movePrimary(main, sharing, 'trackerSharingSection', cursor);
            cursor = movePrimary(main, members, 'trackerMembersSection', cursor);
            if (quickDetail) cursor = movePrimary(main, quickDetail, 'trackerQuickDetailSection', cursor);

            var groupsPanel = ensurePanel(main, 'trackerGroupsPanel', 'Groups, Check-ins & Trips');
            var accountPanel = ensurePanel(main, 'trackerAccountPanel', 'Account, Privacy & App Settings');
            var advancedPanel = ensurePanel(main, 'trackerAdvancedPanel', 'Owner & Advanced Tools');

            ['groupsCard', 'inviteCard', 'familyNoticeCard', 'checkInCard', 'tripShareCard', 'presenceSummaryCard', 'presenceActivityCard', 'leaveGroupCard'].forEach(function (id) { moveToPanel(groupsPanel, $(id)); });
            ['accountSettingsCard', 'appearancePwaCard', 'accountSecurityCard', 'privacyLifecycleCard'].forEach(function (id) { moveToPanel(accountPanel, $(id)); });
            ['ownerMemberManagementCard', 'trailStatusCard', 'mapToolsCard', 'geofenceCard', 'diagnosticsCard'].forEach(function (id) { moveToPanel(advancedPanel, $(id)); });
            moveToPanel(advancedPanel, findRealityCard(main));

            var ownerLink = $('ownerDashboardNavLink');
            var title = $('accountTitle');
            if (ownerLink && title) ownerLink.classList.toggle('hidden', !/\(owner\)\s*$/i.test(title.textContent || ''));
        } finally {
            organizing = false;
        }
    }

    function queueOrganize() {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(function () {
            queued = false;
            organize();
        });
    }

    function boot() {
        organize();
        var main = $('trackerApp');
        if (main) new MutationObserver(queueOrganize).observe(main, { childList: true, subtree: false });
        window.setTimeout(organize, 500);
        window.setTimeout(organize, 1800);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
