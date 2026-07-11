<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.5.0
Project Revision Reference: 1.5.0
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.5.0**

## Completed in Rev 1.5.0

- [x] Keep automatic location permission request on launch.
- [x] Add quick check-ins: I'm OK, On My Way, Arrived, and Need Help.
- [x] Add optional check-in notes.
- [x] Add manual trip mode with destination and ETA sharing.
- [x] Add end-trip control.
- [x] Add active-group check-in and trip summary.
- [x] Add a lightweight recent check-in/trip activity panel on the main page.

## Completed in Rev 1.4.8–1.4.9

- [x] Add expiring and maximum-use managed invites.
- [x] Add invite listing and revoke controls.
- [x] Add guarded active-group deletion.
- [x] Add privacy details and background-browser disclosure.
- [x] Add expired remembered-device cleanup.
- [x] Add guarded permanent account deletion.

## Completed previously

- [x] Multi-group/circle support.
- [x] Owner dashboard, ownership transfer, audit history, activity, and group export.
- [x] Owner member management and remove-from-group.
- [x] Member nicknames, relationship labels, colors, joined dates, and duplicate warnings.
- [x] Password change, remembered-device controls, and user export.
- [x] Member detail, trail preview, last-known location, and map links.
- [x] Map mode preference and center controls.
- [x] Diagnostics and signed-in health check.

## Next reasonable batches

### Membership lifecycle

- [ ] Add temporary member disable/restore for an active group.
- [ ] Add optional member profile pictures.
- [ ] Add leave-group control for non-owners.

### Location features

- [ ] Add geofence zones and arrival/departure notices.
- [ ] Add notices when a member becomes stale or starts sharing again.
- [ ] Add configurable trail retention and owner trail cleanup.
- [ ] Add calculated routing/ETA through an external routing provider.

### Security and privacy

- [ ] Add login throttling by username and IP hash.
- [ ] Add password reset.
- [ ] Add optional TOTP MFA.
- [ ] Move runtime data outside the public web root.
- [ ] Add consent review after major privacy-related changes.
- [ ] Add old audit-log cleanup.

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
