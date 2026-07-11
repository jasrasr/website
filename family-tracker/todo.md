<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.5.3
Project Revision Reference: 1.5.3
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.5.3**

## Completed in Rev 1.5.3

- [x] Add web app manifest and home-screen install support.
- [x] Add service-worker app-shell caching.
- [x] Add online/offline status messaging.
- [x] Add compact layout mode.
- [x] Add high-contrast appearance mode.
- [x] Add light appearance mode.
- [x] Add manual cached-app refresh control.
- [x] Keep automatic location permission request on launch.

## Completed previously

- [x] Temporary member disable/restore and safe leave-group control.
- [x] Stale/recovered-sharing notices and configurable trail retention.
- [x] Quick check-ins and manual trip/ETA sharing.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group and account deletion.
- [x] Multi-group support, owner dashboard, member management, maps, diagnostics, and account security.

## Next reasonable batches

### Member experience

- [ ] Add optional member profile pictures.
- [ ] Add member-controlled nickname/profile preferences.
- [ ] Add a clearer disabled-access landing message.

### Location features

- [ ] Add geofence zones and arrival/departure notices.
- [ ] Add calculated routing/ETA through an external routing provider.
- [ ] Add owner audit-log retention and cleanup.

### Security and privacy

- [ ] Add login throttling by username and IP hash.
- [ ] Add password reset.
- [ ] Add optional TOTP MFA.
- [ ] Move runtime data outside the public web root.
- [ ] Add consent review after major privacy-related changes.

### PWA and UI

- [ ] Add loading skeletons.
- [ ] Add clearer success/error banners instead of relying mainly on the sticky status line.
- [ ] Add a PNG icon set for broader home-screen compatibility.
- [ ] Evaluate offline location-update queuing; do not queue sensitive writes without conflict handling.

## Implementation notes

- Keep revision numbers in the 1.x+ pattern.
- Any new live-data directory must include a `.placeholder` file.
- New PHP, JavaScript, CSS, and markdown files must include revision headers.
- Avoid hardcoded administrator credentials.
- Keep the automatic location-permission request on launch unless explicitly changed later.
- Preserve the mobile map fallback because iPhone browser map behavior remains fragile.
