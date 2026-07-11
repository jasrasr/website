<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.4.9
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.4.9**

## Rev 1.4.9 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added a Privacy & Account Lifecycle card to the main app.
- Added `privacy.php` with plain-language data-storage, location-sharing, and background-browser disclosures.
- Added `lifecycle.php` for account-deletion eligibility checks, expired remembered-device cleanup, and permanent account deletion.
- Added password plus exact-text confirmation for account deletion.
- Blocked account deletion when the user owns a group that still contains other members.
- Allowed deletion of single-member owned groups together with the account.
- Account deletion removes the user record, username index entry, location, trail, remembered-device tokens, and group memberships.
- Added no new live-data folder.

## Rev 1.4.8 - 2026-07-11

- Kept the existing automatic location-permission request on launch.
- Added managed invite codes with optional 1-hour, 24-hour, 7-day, or no expiration.
- Added invite-use limits of 1, 5, or unlimited.
- Added owner invite listing, usage counts, expiration state, and revoke controls.
- Added one-time full-code display for newly created managed invites.
- Added invite-aware joining for new accounts and existing signed-in accounts.
- Preserved compatibility with the original legacy group invite code.
- Added guarded active-group deletion from the Owner Dashboard.
- Group deletion requires the exact group name and requires the owner to belong to another group first.
- Group deletion removes the group from all members and deletes matching latest-location and trail records.
- Added no new live-data folder.

## Rev 1.4.7 - 2026-07-11

- Kept the existing automatic location-permission request on launch.
- Added `owner-dashboard.php`, an owner-only active-group administration page.
- Added `owner-admin.php` for group settings, ownership transfer, activity, audit history, and group export.
- Added group description and group color settings.
- Added transfer-ownership support with explicit confirmation.
- Added an active-group member summary in the owner dashboard.
- Added a permanent recent-activity feed sourced from group notices.
- Added owner-filtered audit history sourced from the existing audit files.
- Added owner-controlled active-group JSON export.
- Added an Owner Dashboard link to the main active-group member-management card.
- Added no new live-data folder.
