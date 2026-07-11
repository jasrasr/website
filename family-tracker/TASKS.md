<!--
Project: Family GPS Tracker
File: TASKS.md
Revision: 1.4.6
Description: Restart-friendly task list and maintenance backlog for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker Task List

## Completed

- [x] Rev 1.0.0 - Initial PHP + JSON family tracker scaffold.
- [x] Rev 1.1.0 - Shared history endpoint and separate history map page.
- [x] Rev 1.1.1 - Header audit, changelog project revision, live-data placeholder documentation, and 1.x revision-numbering correction.
- [x] Rev 1.2.0 - Family join notices, invite-code copy button, and two-step regenerate warning.
- [x] Rev 1.2.1 - Server-stored family notices with per-user dismissal.
- [x] Rev 1.3.0 - Long session cookies, optional persistent login, and automatic logged-in location updates.
- [x] Rev 1.3.1 - Mobile map height/scroll fix, compact metric cards, and invite-code explanation cleanup.
- [x] Rev 1.3.2 - Browser-readable changelog, dismissible app-updated notice, and map reload rendering fix.
- [x] Rev 1.3.4 - Mobile map fallback and member map links.
- [x] Rev 1.3.5 - You/Owner member badges.
- [x] Rev 1.3.6 - Display-name update option.
- [x] Rev 1.3.7 - Closest-city latest-location labels.
- [x] Rev 1.4.0 - Multi-group/circle support.
- [x] Rev 1.4.1 - Invite-code UI cleanup.
- [x] Rev 1.4.2 - Account settings, group rename, location-label options, diagnostics, and health check.
- [x] Rev 1.4.3 - Account security tools, remembered-device management, data export, group notices, and member status sections.
- [x] Rev 1.4.4 - Owner member management, nicknames, relationship labels, member colors, joined-at display, duplicate warnings, and remove-from-group.
- [x] Rev 1.4.5 - Member detail page, quick-detail panel, detail links, active-group trail filtering, and history map link.
- [x] Rev 1.4.6 - Map tools, map mode preference, center-on-user/member controls, static preview, and external map links.

## Live data folder rule

- [x] `data/users/.placeholder` exists for account JSON.
- [x] `data/families/.placeholder` exists for family/group JSON.
- [x] `data/locations/.placeholder` exists for latest-location JSON.
- [x] `data/trails/.placeholder` exists for breadcrumb trail JSON.
- [x] `data/notices/.placeholder` exists for server-stored notice dismissals.
- [x] `data/persistent_logins/.placeholder` exists for persistent login records.
- [x] `data/locks/.placeholder` exists for lock files.
- [x] `data/audit/.placeholder` exists for audit logs.
- [x] Rev 1.4.6 added no new live-data folder.

## Next practical improvements

- Add owner/member role transfer.
- Add delete-my-account controls.
- Add owner-controlled export for active-group data.
- Add optional geofence zones, such as Home, School, Work, Church, or Grandma’s House.
- Add trip mode with ETA sharing.
- Add PWA install support.

## Security hardening

- Move `data/` outside the public web root.
- Add login throttling by username and IP hash.
- Add password reset flow.
- Add optional TOTP MFA for owner accounts.
- Add CSP headers after testing CDN dependencies.
- Add server-side retention cleanup for old trail points.

## Known limitations

- Location storage is still one latest point per user, tagged with the active group at the time of update.
- Browser GPS pauses or becomes unreliable when the phone sleeps, locks, or the browser is backgrounded.
- iOS Safari and Android browsers may behave differently for long-running location watches.
- Accuracy depends on device, OS, permissions, signal, and battery mode.
- The Map Tools panel uses static preview/external links as a stable fallback; the embedded app map still uses the existing Leaflet/mobile fallback behavior.
- This is not a true native Life360 replacement unless paired with a native app or PWA/background-location strategy.
