<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.4.7
Project Revision Reference: 1.4.7
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.4.7**

## Completed in Rev 1.4.7

- [x] Keep automatic location permission request on launch.
- [x] Add an owner dashboard for the active group.
- [x] Add centralized group settings for name, color, and description.
- [x] Let owners transfer ownership to another active-group member.
- [x] Add a permanent recent activity feed.
- [x] Add owner-filtered audit history.
- [x] Add owner-controlled active-group data export.
- [x] Add a member summary to the owner dashboard.

## Completed previously

- [x] Multi-group/circle support.
- [x] Owner member management and remove-from-group.
- [x] Member nicknames, relationship labels, colors, joined dates, and duplicate warnings.
- [x] Password change, remembered-device controls, and user export.
- [x] Member detail, trail preview, last-known location, and map links.
- [x] Map mode preference and center controls.
- [x] Diagnostics and signed-in health check.

## Next reasonable batches

### Invite management

- [ ] Add invite expiration: 1 hour, 24 hours, 7 days, or never.
- [ ] Add invite maximum-use limits.
- [ ] Add a list of active and disabled invites.
- [ ] Allow owners to disable an invite without generating a replacement.

### Membership lifecycle

- [ ] Add temporary member disable/restore for an active group.
- [ ] Add safe delete-group workflow.
- [ ] Add delete-my-account with ownership-transfer safeguards.
- [ ] Add optional member profile pictures.

### Location features

- [ ] Add geofence zones and arrival/departure notices.
- [ ] Add notices when a member becomes stale or starts sharing again.
- [ ] Add configurable trail retention and owner trail cleanup.
- [ ] Add trip mode with ETA sharing.

### Security and privacy

- [ ] Add login throttling by username and IP hash.
- [ ] Add password reset.
- [ ] Add optional TOTP MFA.
- [ ] Move runtime data outside the public web root.
- [ ] Add privacy and consent review pages.
- [ ] Add cleanup for expired persistent-login records and old audit logs.

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
