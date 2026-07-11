<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.5.2
Project Revision Reference: 1.5.2
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.5.2**

## Completed in Rev 1.5.2

- [x] Keep automatic location permission request on launch.
- [x] Add temporary member disable/restore for an active group.
- [x] Add a separate permanent remove-from-group action.
- [x] Add leave-group control for non-owner members.
- [x] Add visible disabled-member state in owner member management.
- [x] Add audit and group-notice events for membership lifecycle changes.

## Completed previously

- [x] Stale/recovered-sharing notices and configurable trail retention.
- [x] Quick check-ins and manual trip/ETA sharing.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group and account deletion.
- [x] Privacy details and remembered-device cleanup.
- [x] Multi-group/circle support.
- [x] Owner dashboard, ownership transfer, audit history, activity, and group export.
- [x] Member nicknames, relationship labels, colors, joined dates, and duplicate warnings.
- [x] Password change, remembered-device controls, and user export.
- [x] Member detail, trail preview, last-known location, and map links.
- [x] Map mode preference and center controls.
- [x] Diagnostics and signed-in health check.

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

- [ ] Add web app manifest and home-screen install support.
- [ ] Add service-worker app-shell caching and offline status.
- [ ] Add compact, high-contrast, and light appearance modes.
- [ ] Add loading skeletons and clearer success/error banners.

## Implementation notes

- Keep revision numbers in the 1.x+ pattern.
- Any new live-data directory must include a `.placeholder` file.
- New PHP, JavaScript, CSS, and markdown files must include revision headers.
- Avoid hardcoded administrator credentials.
- Keep the automatic location-permission request on launch unless explicitly changed later.
- Preserve the mobile map fallback because iPhone browser map behavior remains fragile.
