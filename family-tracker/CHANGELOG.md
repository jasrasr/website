<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.6.9
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-08-06
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.6.9**

## Rev 1.6.9 - 2026-08-06

- Replaced visible latitude and longitude entry with an address-or-place search field.
- Added Find Address using OpenStreetMap Nominatim geocoding.
- Changed Use My Latest Location to reverse-geocode the saved GPS point and fill a readable address.
- Retained latitude and longitude internally for geofence calculations.
- Moved manual coordinate entry into a collapsed Advanced coordinates section.
- Stored the resolved address with each geofence record and displayed it on place cards.
- Existing coordinate-only places are reverse-geocoded when edited when lookup is available.
- Updated the PWA app-shell cache.
- Added no new live-data folder.

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
