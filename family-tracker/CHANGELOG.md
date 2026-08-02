<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.6.8
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-08-02
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.6.8**

## Rev 1.6.8 - 2026-08-02

- Added a per-device Location Update Mode control near the Sharing section.
- The first location request still runs immediately on page load for every mode.
- Added Live, Frequent, Balanced, Battery Saver, Maximum Saver, and Manual modes.
- Balanced is the recommended default and requests location about every five minutes.
- Battery Saver requests about every fifteen minutes with standard accuracy and reusable cached positions.
- Maximum Saver requests about every thirty minutes with lower-power settings.
- Manual captures the initial page-load location and then waits for Update Once.
- Saved the selected mode in browser local storage so each device can use a different setting.
- Paused scheduled GPS requests while the page is hidden and requested an overdue update when it becomes visible again.
- Prevented the scheduled timer from making additional requests while Live continuous sharing is active.
- Starting continuous sharing temporarily selects Live mode; stopping restores the previous scheduled mode.
- Added a visible description and next-update estimate for the selected mode.
- Added the GPS controller to the PWA app-shell cache.
- Added no new live-data folder.

## Rev 1.6.7 - 2026-08-02

- Replaced large hour counts with readable elapsed time such as `20d 2h ago`.
- Added minutes to recent hour-based ages when available.
- Added month/year formatting for very old location records.
- Kept closest city and state as the default member-card location text.
- Removed accuracy and full coordinates from the default member-card summary; technical details remain available through Details and map links.
- Added straight-line distance from the signed-in user's latest location to each member.
- Changed stale badges to show readable `Last seen` time.
- Updated the PWA app-shell cache.
- Added no new live-data folder.

## Rev 1.6.6 - 2026-08-02

- Changed the active group share code so the group owner can continue viewing and copying it after creation.
- Added explicit Show/Hide behavior for the owner-facing code display.
- Added Reset Share Code behavior that immediately invalidates the previous code.
- New and reset share codes are retained in the protected group JSON record while hash-based validation remains in place.
- Existing groups created before this revision require one reset because their previous full code was never stored.
- Added `assets/js/share-code.js` and included it in the PWA app-shell cache.
- Added no new live-data folder.

## Rev 1.6.5 - 2026-07-14

- Added the missing release note for the app title rename to `Friends & Family GPS Tracker`.
- Bumped the app revision and service-worker cache after the title metadata change was missed.

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

## Rev 1.6.2 - 2026-07-13

- Removed the manually entered build clock from application configuration.
- Added automatic latest-update timestamp detection from deployed revision files.
- Latest-update time is now formatted with the `America/New_York` time zone and always labeled ET.
- Daylight-saving changes are handled automatically by the time-zone database.
- The header, diagnostics, and health page continue using the shared `APP_BUILD_LABEL`, now generated dynamically.
- Updated the PWA cache revision.
- Kept launch-time location permission unchanged.
- Added no new live-data folder.
