<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.5.4
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.5.4**

## Rev 1.5.4 - 2026-07-11

- Kept the automatic location-permission request on launch.
- Added file-backed login throttling by normalized username and privacy-preserving IP hash.
- Blocks login for 15 minutes after five failed attempts within 15 minutes.
- Added clearer inactive-account and lost-group-access messages.
- Added versioned privacy/location-sharing consent review for existing users.
- Added signed-in cleanup for expired remembered-device records, stale throttle records, and audit files older than 90 days.
- Added `security-maintenance.php`, `includes/login-throttle.php`, and security-maintenance UI assets.
- Updated the service-worker cache for the new static assets.
- Added no new live-data folder.

## Rev 1.5.3 - 2026-07-11

- Added PWA installation, offline app-shell support, appearance modes, and compact layout.

## Rev 1.5.2 - 2026-07-11

- Added temporary member disable/restore, permanent removal, and leave-group controls.

## Rev 1.5.1 - 2026-07-11

- Added configurable trail retention, owner cleanup, and stale/restored location notices.

## Rev 1.5.0 - 2026-07-11

- Added quick check-ins, manual trip/ETA sharing, group status, and presence activity.

## Rev 1.4.9 - 2026-07-11

- Added privacy details, remembered-device cleanup, and guarded permanent account deletion.

## Rev 1.4.8 - 2026-07-11

- Added expiring/limited-use managed invites, invite revocation, and guarded active-group deletion.

## Rev 1.4.7 - 2026-07-11

- Added the owner dashboard, group settings, ownership transfer, activity, audit history, and group export.
