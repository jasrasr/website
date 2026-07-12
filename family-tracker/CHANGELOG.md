<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.5.7
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-12
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.5.7**

## Rev 1.5.7 - 2026-07-12

- Fixed geofence module cache busting so the current app revision is used instead of a hardcoded older revision.
- Fixed the Refresh Cached App action so it clears Family Tracker app-shell caches before reloading.
- Added revision query strings to the service worker, manifest, icon, and dynamically loaded geofence script.
- Preserved the Rev 1.5.6 coordinate fix: decimal inputs use `step="any"` and copied coordinates are rounded to six decimals.
- Added no new live-data folder.

## Rev 1.5.6 - 2026-07-11

- Fixed iPhone/browser validation errors after using **Use My Latest Location** in the geofence form.
- Changed latitude and longitude inputs to accept any valid decimal step.
- Rounded copied GPS coordinates to six decimal places before inserting them into the form.
- Added explicit form validation before creating a place.
- Updated the PWA cache revision so the corrected geofence script is fetched.
- Added no new live-data folder.

## Rev 1.5.5 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added owner-managed geofence places stored in the existing group JSON record.
- Added place name, latitude, longitude, and radius controls from 100 meters to 2 kilometers.
- Added a Use My Latest Location helper for creating places.
- Added once-per-minute browser-driven arrival/departure evaluation while a signed-in group page is open.
- Added group notices and audit events when members arrive at or leave a configured place.
- Added current inside/outside and distance status for each member with a saved active-group location.
- Added no new live-data folder.

## Rev 1.5.4 - 2026-07-11

- Added login throttling, clearer access messages, versioned consent review, and security/audit cleanup.

## Rev 1.5.3 - 2026-07-11

- Added PWA installation, offline app-shell support, appearance modes, and compact layout.
