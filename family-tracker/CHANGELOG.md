<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.5.5
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.5.5**

## Rev 1.5.5 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added owner-managed geofence places stored in the existing group JSON record.
- Added place name, latitude, longitude, and radius controls from 100 meters to 2 kilometers.
- Added a Use My Latest Location helper for creating places.
- Added once-per-minute browser-driven arrival/departure evaluation while a signed-in group page is open.
- Added group notices and audit events when members arrive at or leave a configured place.
- Added current inside/outside and distance status for each member with a saved active-group location.
- Updated the PWA service-worker cache for the geofence UI module.
- Added no new live-data folder.

## Rev 1.5.4 - 2026-07-11

- Added login throttling, clearer access messages, versioned consent review, and security/audit cleanup.

## Rev 1.5.3 - 2026-07-11

- Added PWA installation, offline app-shell support, appearance modes, and compact layout.

## Rev 1.5.2 - 2026-07-11

- Added temporary member disable/restore, permanent removal, and leave-group controls.

## Rev 1.5.1 - 2026-07-11

- Added configurable trail retention, owner cleanup, and stale/restored location notices.

## Rev 1.5.0 - 2026-07-11

- Added quick check-ins, manual trip/ETA sharing, group status, and presence activity.
