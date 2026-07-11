<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.5.2
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.5.2**

## Rev 1.5.2 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added owner-controlled temporary member disable and restore for the active group.
- Temporarily disabled members lose active-group membership until restored, while their account remains intact.
- Added a distinct Disabled state in owner member-management cards.
- Added permanent remove-from-group as a separate action from temporary disable.
- Added a safe Leave Active Group control for non-owner members.
- Leaving removes active-group membership, profile, check-in, trip, and location-state metadata while preserving the account and other groups.
- Added audit and group-notice events for disable, restore, removal, and voluntary leave actions.
- Added no new live-data folder.

## Rev 1.5.1 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added configurable active-group trail retention: 24 hours, 7 days, 30 days, or 90 days.
- Added owner-controlled cleanup of trail points older than the selected retention period.
- Added per-user automatic trail trimming when location status is checked.
- Added once-per-minute active-group live, stale, and no-location monitoring while the page is open.
- Added group notices when a previously live location becomes stale.
- Added group notices when a stale or missing member starts sharing a current location again.
- Added a main-page Location Health panel with live/stale/missing counts and stored trail-point totals.
- Added `trail-status.php` and `assets/js/trail-status.js`.
- Added no new live-data folder.

## Rev 1.5.0 - 2026-07-11

- Added quick check-ins, manual trip/ETA sharing, group status, and presence activity.

## Rev 1.4.9 - 2026-07-11

- Added privacy details, remembered-device cleanup, and guarded permanent account deletion.

## Rev 1.4.8 - 2026-07-11

- Added expiring/limited-use managed invites, invite revocation, and guarded active-group deletion.

## Rev 1.4.7 - 2026-07-11

- Added the owner dashboard, group settings, ownership transfer, activity, audit history, and group export.
