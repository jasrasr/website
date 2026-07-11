<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.5.5
Project Revision Reference: 1.5.5
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.5.5**

## Completed in Rev 1.5.5

- [x] Add owner-managed geofence zones.
- [x] Add place name, coordinates, and configurable radius.
- [x] Add Use My Latest Location helper when creating a place.
- [x] Add arrival/departure group notices.
- [x] Add current inside/outside and distance status.
- [x] Keep automatic location permission request on launch.

## Completed previously

- [x] Login throttling, versioned consent review, access messaging, and security/audit cleanup.
- [x] PWA install support, service-worker app shell, offline status, and appearance modes.
- [x] Temporary member disable/restore and leave-group controls.
- [x] Stale/recovered-sharing notices and configurable trail retention.
- [x] Quick check-ins and manual trip/ETA sharing.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group and account deletion.
- [x] Multi-group support, owner dashboard, member management, maps, diagnostics, and account security.

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
