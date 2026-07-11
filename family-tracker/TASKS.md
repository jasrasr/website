<!--
Project: Family GPS Tracker
File: TASKS.md
Revision: 1.5.0
Description: Restart-friendly task list and maintenance backlog for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker Task List

## Completed

- [x] Rev 1.0.0 - Initial PHP + JSON family tracker scaffold.
- [x] Rev 1.1.0 - Shared history endpoint and separate history map page.
- [x] Rev 1.2.0 - Family join notices and invite-code controls.
- [x] Rev 1.3.0 - Long sessions, persistent login, and automatic location updates.
- [x] Rev 1.3.4 - Mobile map fallback and member map links.
- [x] Rev 1.4.0 - Multi-group/circle support.
- [x] Rev 1.4.2 - Account settings, diagnostics, and health check.
- [x] Rev 1.4.3 - Account security, remembered devices, data export, and group notices.
- [x] Rev 1.4.4 - Owner member management, nicknames, relationships, colors, and remove-from-group.
- [x] Rev 1.4.5 - Member detail page, quick detail, active-group trails, and history links.
- [x] Rev 1.4.6 - Map modes and center controls.
- [x] Rev 1.4.7 - Owner dashboard, group settings, ownership transfer, activity feed, audit history, and group export.
- [x] Rev 1.4.8 - Expiring/limited-use invites, invite revocation, and guarded group deletion.
- [x] Rev 1.4.9 - Privacy details, cleanup, and guarded account deletion.
- [x] Rev 1.5.0 - Quick check-ins, manual trip/ETA sharing, group status, and presence activity.

## Live data folder rule

- [x] Existing runtime folders retain `.placeholder` files.
- [x] Rev 1.5.0 added no new live-data folder.

## Next practical improvements

- Add temporary member disable/restore controls.
- Add leave-group control for non-owners.
- Add configurable trail retention and cleanup.
- Add optional geofence zones.
- Add stale/recovered-sharing notices.
- Add routing-provider ETA calculations.

## Security hardening

- Move `data/` outside the public web root.
- Add login throttling by username and IP hash.
- Add password reset flow.
- Add optional TOTP MFA for owner accounts.
- Add CSP headers after testing CDN dependencies.
- Add server-side retention cleanup for old trail and audit records.

## Known limitations

- Trip ETA is entered manually and does not calculate a route.
- Location storage is still one latest point per user, tagged with the active group at the time of update.
- Browser GPS pauses or becomes unreliable when the phone sleeps, locks, or the browser is backgrounded.
- iOS Safari and Android browsers may behave differently for long-running location watches.
- This remains a web app rather than a native background-location client.
