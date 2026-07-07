<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.2.1
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.2.1**

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
