<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.4.3
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.4.3**

## Rev 1.4.3 - 2026-07-09

- Added `account.php` for signed-in account security actions and data export.
- Added password-change support with remembered-device revocation.
- Added remembered-device listing, single-device revoke, and revoke-all controls.
- Added Download My Data export for the signed-in user.
- Added shared group-notice helpers in `includes/notice-store.php`.
- Made notices active-group aware.
- Added server-stored notices for group creation, group join, group rename, display-name change, and invite-code regeneration.
- Added `assets/js/account-security.js` for the account security UI.
- Added `assets/js/member-sections.js` to separate members into Live / Recent, Stale, and No Location Yet sections.
- Added no new live-data folder.

## Rev 1.4.2 - 2026-07-09

- Added an inline Account & Group settings card.
- Moved display-name editing out of injected UI and into the main layout.
- Added owner-only active group-name editing.
- Added member username and short ID display for duplicate-name troubleshooting.
- Added member-location format choices: closest city, rounded GPS, or both.
- Added a manual Refresh Location Labels button.
- Added rounded GPS fallback when closest-city lookup fails.
- Added a diagnostics panel for GPS permission, online status, session API timing, signed-in user, active group, and build revision.
- Added `health.php`, a signed-in health-check page for runtime folder permissions and deployment checks.
- Marked completed items in `todo.md`.
- Added no new live-data folder.

## Rev 1.4.1 - 2026-07-06

- Removed the duplicate temporary group invite-code display from the Groups / Circles card.
- The full invite code now appears only in the main Invite Code card.
- Creating a new group still switches to that group and places the one-time code in the Invite Code card for copying.
- Added no new live-data folder.

## Rev 1.4.0 - 2026-07-06

- Added multi-group/circle support using the existing family JSON storage as group records.
- Added `groups.php` for listing groups, creating a new group, joining another group by invite code, and switching the active group.
- Added `assets/js/groups.js` for the Groups / Circles UI.
- Updated the main app so the active group controls the map, member list, location updates, and invite-code regeneration.
- Added per-group owner/member role handling through group `memberRoles` and `memberIds` metadata.
- Existing users are treated as members of their current family/group and can create or join additional groups.
- Added no new live-data folder.
