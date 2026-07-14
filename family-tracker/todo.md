<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.6.3
Project Revision Reference: 1.6.3
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-14
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.6.3**

## Completed in Rev 1.6.3

- [x] Add optional generated avatars or profile pictures.
- [x] Add member-controlled nickname/profile preferences.
- [x] Add a dedicated account/profile page.

## Completed in Rev 1.6.1

- [x] Add geofence editing rather than delete/recreate only.
- [x] Add per-place arrival-notification toggles.
- [x] Add per-place departure-notification toggles.
- [x] Keep geofence transitions in the audit trail even when notices are disabled.
- [x] Show the notification mode on each place card.

## Completed in Rev 1.6.0

- [x] Move the map and live member information to the top of the tracker.
- [x] Add compact navigation for Map, Members, Sharing, Groups, Account, More, History, and Owner tools.
- [x] Move secondary tools into collapsed sections.

## Completed previously

- [x] Delete-my-account safeguards, active-group export, privacy summary, and status banners.
- [x] Multi-group support, owner dashboard, member management, maps, diagnostics, and account security.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group deletion.
- [x] PWA install support, offline status, appearance modes, and service-worker caching.
- [x] Login throttling, consent review, and security/audit cleanup.
- [x] Geofence zones and arrival/departure notices.
- [x] Member-list flicker fix and reduced background UI polling.

## Next reasonable batches

### Location features

- [ ] Add calculated routing/ETA through an external routing provider.
- [ ] Add richer background/offline location guidance.
- [ ] Add a map overlay showing configured geofence circles.

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

