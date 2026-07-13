<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.5.9
Project Revision Reference: 1.5.9
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-12
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.5.9**

## Completed in Rev 1.5.9

- [x] Add delete-my-account with password and exact-username confirmation.
- [x] Block account deletion while the account still owns a group.
- [x] Remove account membership, location, trail, username index, and remembered-device records during deletion.
- [x] Add owner-controlled active-group export from the main app.
- [x] Add privacy text explaining stored account, location, trail, device, and consent data.
- [x] Add a clear indication that browser background location is not guaranteed.
- [x] Add clearer success/error/progress status banners.

## Completed previously

- [x] Multi-group support, owner dashboard, member management, maps, diagnostics, and account security.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group deletion.
- [x] PWA install support, offline status, appearance modes, and service-worker caching.
- [x] Login throttling, consent review, and security/audit cleanup.
- [x] Geofence zones and arrival/departure notices.
- [x] Member-list flicker fix and reduced background UI polling.

## Next reasonable batches

### Member experience

- [ ] Add optional member profile pictures or generated avatars.
- [ ] Add member-controlled nickname/profile preferences.
- [ ] Add a dedicated account/profile page.

### Location features

- [ ] Add calculated routing/ETA through an external routing provider.
- [ ] Add richer background/offline location guidance.
- [ ] Add geofence editing rather than delete/recreate only.
- [ ] Add per-place notification toggles.

### Security and privacy

- [ ] Add password reset.
- [ ] Add optional TOTP MFA.
- [ ] Move runtime data outside the public web root.
- [ ] Add Content Security Policy after CDN and map testing.

### PWA and UI

- [ ] Add loading skeletons.
- [ ] Add a PNG icon set for broader home-screen compatibility.
- [ ] Evaluate offline location-update queuing; do not queue sensitive writes without conflict handling.

## Implementation notes

- Keep revision numbers in the 1.x+ pattern.
- Any new live-data directory must include a `.placeholder` file.
- New PHP, JavaScript, CSS, and markdown files must include revision headers.
- Avoid hardcoded administrator credentials.
- Keep the automatic location-permission request on launch unless explicitly changed later.
- Preserve the mobile map fallback because iPhone browser map behavior remains fragile.
