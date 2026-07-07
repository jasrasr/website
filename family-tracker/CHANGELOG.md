<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.3.3
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.3.3**

## Rev 1.3.3 - 2026-07-06

- Added a runtime Leaflet image layout fix for mobile browsers where map tiles render at the wrong size after reload.
- Forced map tile and marker images to ignore inherited responsive image constraints.
- Disabled retina tile mode to keep OpenStreetMap tile sizing predictable on iPhone reloads.
- Added longer delayed map refresh passes after paint to catch late mobile layout changes.

## Rev 1.3.2 - 2026-07-06

- Added `changelog.php` so the changelog can be opened from the app.
- Added a dismissible app-updated notice that links to the changelog.
- The app-updated notice is keyed to the current revision and is dismissed with local browser storage.
- Delayed map setup until after the app panel is visible.
- Added repeated Leaflet `invalidateSize()` calls after page paint, resize, orientation changes, and family-location rendering to reduce partial tile rendering on reload.

## Rev 1.3.1 - 2026-07-06

- Reduced mobile map height and constrained map overflow so scrolling past the map is stable.
- Disabled map dragging on coarse-pointer/mobile devices so the page scroll does not get trapped by the map.
- Made the GPS accuracy, speed, heading, and last-update cards compact two-line metric blocks.
- Clarified why only the last four invite-code characters appear after refresh.
- Clarified that Copy Code requires the full invite code, which is visible only immediately after creation or regeneration.

## Rev 1.3.0 - 2026-07-06

- Added 30-day long session cookies.
- Added optional persistent login with a Remember Me checkbox on login, create-family, and join-family forms.
- Added hashed persistent-login records in `data/persistent_logins/`.
- Added `data/persistent_logins/.placeholder` for the new live-data folder.
- Logout now revokes the current persistent login for that device.
- Kept automatic location update immediately after login or session restore.
- Kept recurring automatic location updates about every 60 seconds while logged in and the page is open.

## Rev 1.2.1 - 2026-07-06

- Added server-stored family notices in `data/notices/`.
- Added per-user notice dismissal so each notice appears until that user dismisses it.
- Added `notices.php` for listing and dismissing unread family notices.
- Added `data/notices/.placeholder` for the new live-data folder.

## Rev 1.2.0 - 2026-07-06

- Added Family Notices for newly joined members during an active session.
- Added Copy Code for visible invite codes.
- Added a two-step confirmation before invite-code regeneration.

## Rev 1.1.1 - 2026-07-06

- Renumbered project revisions to the preferred 1.x sequence.
- Replaced prior 0.x revision references with 1.x equivalents.
- Audited live-data folders and confirmed each runtime data directory has a `.placeholder` tracked in Git.
- Added document headers to README, changelog, task list, and Apache access-control files.
- Clarified project revision and live-data placeholder expectations.

## Rev 1.1.0 - 2026-07-06

- Added shared trail-history endpoint via `trails.php`.
- Added separate `history.php` view for shared family trail history.
- Added `assets/js/history.js` for all-family and individual member trail maps.
- Added history window filtering for last hour, 4 hours, 12 hours, or 24 hours.
- Added member filtering for map history.

## Rev 1.0.0 - 2026-07-06

- Initial PHP + JSON scaffold.
- Added owner-created family groups.
- Added invite-code family joining.
- Added PHP session login/logout.
- Added consent gate during account creation.
- Added browser GPS sharing using `navigator.geolocation.watchPosition()`.
- Added latest-location JSON storage under `data/locations/`.
- Added short breadcrumb trail storage under `data/trails/`.
- Added family map using Leaflet and OpenStreetMap tiles.
- Added own-location deletion.
- Added owner invite-code regeneration.
- Added Apache `.htaccess` protection for JSON data.
