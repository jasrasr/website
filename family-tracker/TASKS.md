<!--
Project: Family GPS Tracker
File: TASKS.md
Revision: 1.1.1
Description: Restart-friendly task list and maintenance backlog for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker Task List

## Completed

- [x] Rev 1.0.0 - Initial PHP + JSON family tracker scaffold.
- [x] Rev 1.1.0 - Shared history endpoint and separate history map page.
- [x] Rev 1.1.1 - Header audit, changelog project revision, live-data placeholder documentation, and 1.x revision-numbering correction.

## Live data folder rule

- [x] `data/users/.placeholder` exists for account JSON.
- [x] `data/families/.placeholder` exists for family JSON.
- [x] `data/locations/.placeholder` exists for latest-location JSON.
- [x] `data/trails/.placeholder` exists for breadcrumb trail JSON.
- [x] `data/locks/.placeholder` exists for lock files.
- [x] `data/audit/.placeholder` exists for audit logs.

## Next practical improvements

- Add per-member display colors.
- Add member nickname editing.
- Add stale/offline notification badges.
- Add optional geofence zones, such as Home, School, Work, Church, or Grandma’s House.
- Add owner ability to deactivate a member.
- Add push notification support through a proper provider if this moves beyond a toy/site project.
- Add emergency contact card per member.
- Add export/delete-all account data controls.
- Add a one-time setup health check page that verifies folder write permissions and `.htaccess` protection.

## Security hardening

- Move `data/` outside the public web root.
- Add login throttling by username and IP hash.
- Add password reset flow.
- Add optional TOTP MFA for owner accounts.
- Add CSP headers after testing CDN dependencies.
- Add server-side retention cleanup for old trail points.

## Known limitations

- Browser GPS pauses or becomes unreliable when the phone sleeps, locks, or the browser is backgrounded.
- iOS Safari and Android browsers may behave differently for long-running location watches.
- Accuracy depends on device, OS, permissions, signal, and battery mode.
- This is not a true native Life360 replacement unless paired with a native app or PWA/background-location strategy.
