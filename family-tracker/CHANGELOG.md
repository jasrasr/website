<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.4.4
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.4.4**

## Rev 1.4.4 - 2026-07-09

- Added owner-only Member Management for the active group.
- Added `member-management.php` for updating active-group member metadata and removing a member from the active group.
- Added `assets/js/member-management.js` for member nickname, relationship, color, and remove controls.
- Added per-group member nicknames.
- Added per-group relationship labels: Dad, Mom, Child, Grandparent, Friend, or Other.
- Added per-group member colors shown in member cards and badges.
- Added joined-at display for active-group members.
- Added duplicate display-label warnings.
- Added active-group removal that does not delete the user's account.
- Updated new-group and join-group flows to bootstrap member metadata.
- Added no new live-data folder.

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
