<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.6.4
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-14
-->

# Family GPS Tracker Changelog

## Rev 1.6.4 - 2026-07-14

- Added OSRM-powered route ETA calculation from the signed-in user's latest point to a selected member.
- Added clearer background/offline location guidance and live online/offline guidance text.
- Added Leaflet geofence circle overlays for configured places on the group map.
- Added shared location feature helpers with Node coverage for route and geofence normalization.


## Rev 1.6.3 - 2026-07-14

- Added member-controlled profile preferences for nickname, generated avatar, and optional profile picture URL.
- Added a dedicated profile page linked from the tracker account card.
- Added profile avatar metadata to public member payloads and rendered avatars in group member cards.
- Bumped the service-worker cache name for the updated app shell.


Current Project Revision: **1.6.4**

## Rev 1.6.2 - 2026-07-13

- Removed the manually entered build clock from application configuration.
- Added automatic latest-update timestamp detection from deployed revision files.
- Latest-update time is now formatted with the `America/New_York` time zone and always labeled ET.
- Daylight-saving changes are handled automatically by the time-zone database.
- The header, diagnostics, and health page continue using the shared `APP_BUILD_LABEL`, now generated dynamically.
- Updated the PWA cache revision.
- Kept launch-time location permission unchanged.
- Added no new live-data folder.

## Rev 1.6.1 - 2026-07-13

- Added editing for existing geofence places instead of requiring delete and recreate.
- Owners can update place name, coordinates, radius, and notification preferences.
- Added per-place arrival-notice and departure-notice toggles.
- Existing places default to both notification types enabled for backward compatibility.
- Place cards now show their current notification mode.
- Geofence transitions remain audited even when the corresponding group notice is disabled.
- Added group notices and audit records when a place is edited.
- Updated the PWA cache for the revised geofence module.
- Kept launch-time location permission unchanged.
- Added no new live-data folder.

## Rev 1.6.0 - 2026-07-13

- Reorganized the main tracker around the map and live member data.
- Moved the group map directly below the signed-in account and primary navigation.
- Placed GPS metrics, sharing controls, and group members immediately after the map.
- Added a sticky compact navigation bar for Map, Members, Sharing, Groups, Account, More, History, and Owner tools.
- Grouped secondary features into collapsed sections: Groups / Check-ins / Trips, Account / Privacy / App Settings, and Owner / Advanced Tools.
- Preserved all existing element IDs and behaviors so existing JavaScript features continue to work inside the collapsed panels.
- Reduced mobile hero height and increased the primary mobile map height.
- Added the layout module to revision-aware loading and the PWA app-shell cache.
- Kept launch-time location permission unchanged.
- Added no new live-data folder.

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


