<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.5.9
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-12
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.5.9**

## Rev 1.5.9 - 2026-07-12

- Added a Stored Data & Account Lifecycle card to the main app.
- Added privacy summary counts for groups, latest location, trail points, remembered devices, and consent version.
- Added guarded account deletion requiring the current password and exact username.
- Account deletion is blocked while the user still owns a group; ownership must be transferred or the group deleted first.
- Account deletion removes the user from all groups and removes their account, latest location, trail, username index entry, and remembered-device tokens.
- Added a direct active-group export button for active-group owners.
- Added visible success, error, and progress styling to the shared status card.
- Updated revision-aware PWA loading and the app-shell cache.
- Kept launch-time location permission unchanged.
- Added no new live-data folder.

## Rev 1.5.8 - 2026-07-12

- Fixed repeated screen flicker caused by member-section polling that cleared and rebuilt the member list every three seconds.
- Replaced member-section polling with mutation-driven updates that only reorder cards when member status actually changes.
- Reduced member-card enhancement polling from every two seconds to every thirty seconds.
- Prevented repeated badge teardown/recreation when badge content has not changed.
- Removed repeated current-user card movement from the enhancement loop.
- Updated the PWA cache to include the corrected member UI scripts.
- Added no new live-data folder.

## Rev 1.5.7 - 2026-07-12

- Fixed geofence module cache busting and improved Refresh Cached App.

## Rev 1.5.6 - 2026-07-11

- Fixed geofence coordinate input validation and rounded copied coordinates.

## Rev 1.5.5 - 2026-07-11

- Added owner-managed geofence places and arrival/departure notices.
