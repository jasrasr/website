<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.4.7
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.4.7**

## Rev 1.4.7 - 2026-07-11

- Kept the existing automatic location-permission request on launch.
- Added `owner-dashboard.php`, an owner-only active-group administration page.
- Added `owner-admin.php` for group settings, ownership transfer, activity, audit history, and group export.
- Added group description and group color settings.
- Added transfer-ownership support with explicit confirmation.
- Added an active-group member summary in the owner dashboard.
- Added a permanent recent-activity feed sourced from group notices.
- Added owner-filtered audit history sourced from the existing audit files.
- Added owner-controlled active-group JSON export.
- Added an Owner Dashboard link to the main active-group member-management card.
- Added no new live-data folder.

## Rev 1.4.6 - 2026-07-09

- Added map mode preference: embedded app map, static OpenStreetMap preview, or external map links.
- Added Center on Me and Center on Member controls.
- Added active-group member selector for map tools.
- Added static member map preview and group-area external link.
- Added `assets/js/map-tools.js`.
- Added no new live-data folder.

## Rev 1.4.5 - 2026-07-09

- Added a main-page Last Known Location quick-detail card.
- Added member selector for quick detail.
- Added per-member Details links in the member list.
- Added `member-detail.php`, a signed-in member detail page.
- Added `assets/js/member-detail.js` for last-known metrics, static OpenStreetMap preview, external map links, and trail preview.
- Updated `trails.php` to use the active group.
- Added an Open History Map link near the group map.
- Added no new live-data folder.
