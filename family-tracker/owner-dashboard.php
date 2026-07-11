<?php
/**
 * Project: Family GPS Tracker
 * File: owner-dashboard.php
 * Revision: 1.4.7
 * Description: Owner-only active-group dashboard for settings, ownership, activity, audit, and export.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';

init_app_storage();
$user = require_user();
$family = current_family_for_user($user);
if (!$family || family_member_role($family, $user) !== 'owner') {
    http_response_code(403);
    exit('Owner permission required for the active group.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101827">
    <title>Owner Dashboard - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero"><div><p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Owner Tools</p><h1>Owner Dashboard</h1><p class="hero-copy"><a href="index.php">Back to tracker</a></p></div></header>
    <section class="card status-card"><strong>Status:</strong> <span id="ownerStatus">Loading owner dashboard…</span></section>

    <section class="card profile-edit">
        <div><p class="eyebrow">Group Settings</p><h2 id="ownerGroupTitle">Active Group</h2></div>
        <form id="ownerGroupSettingsForm" class="profile-edit">
            <div class="settings-grid">
                <label>Group name<input id="ownerGroupName" maxlength="80" required></label>
                <label>Group color<input id="ownerGroupColor" type="color" value="#4ADE80"></label>
            </div>
            <label>Description<input id="ownerGroupDescription" maxlength="240" placeholder="Optional description"></label>
            <button type="submit">Save Group Settings</button>
        </form>
    </section>

    <section class="card profile-edit">
        <div><p class="eyebrow">Ownership</p><h2>Transfer Ownership</h2><p class="muted">This immediately makes the selected member the owner and changes your role to member.</p></div>
        <div class="settings-grid"><label>New owner<select id="ownerTransferMember"></select></label><div class="settings-row"><button id="ownerTransferBtn" type="button" class="danger-button">Transfer Ownership</button></div></div>
    </section>

    <section class="card">
        <div class="section-header"><div><p class="eyebrow">Members</p><h2>Active Group Members</h2></div><a class="secondary-link" href="index.php#ownerMemberManagementCard">Open Member Management</a></div>
        <div id="ownerMemberList" class="member-list">Loading members…</div>
    </section>

    <section class="card">
        <div class="section-header"><div><p class="eyebrow">Recent Activity</p><h2>Activity Feed</h2></div><button id="ownerRefreshBtn" type="button" class="secondary">Refresh</button></div>
        <div id="ownerActivityList" class="member-list">Loading activity…</div>
    </section>

    <section class="card">
        <div class="section-header"><div><p class="eyebrow">Audit</p><h2>Audit History</h2></div><label class="compact-label">Filter<input id="ownerAuditFilter" placeholder="invite, member, group"></label></div>
        <div id="ownerAuditList" class="member-list">Loading audit history…</div>
    </section>

    <section class="card"><div class="section-header"><div><p class="eyebrow">Data</p><h2>Export Active Group</h2><p class="muted">Downloads group settings, members, matching locations, trails, activity, and audit records.</p></div><button id="ownerExportBtn" type="button" class="secondary">Download Group Export</button></div></section>
</div>
<script src="assets/js/owner-dashboard.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>
