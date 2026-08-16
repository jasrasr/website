<!--
Project: Family GPS Tracker
File: TASKS.md
Revision: 1.6.12
Description: Restart-friendly task list and maintenance backlog for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-08-16
-->

# Family GPS Tracker Task List

## Completed

- [x] Rev 1.0.0 - Initial PHP + JSON tracker scaffold.
- [x] Rev 1.3.0 - Persistent login and automatic location updates.
- [x] Rev 1.4.0 - Multi-group/circle support.
- [x] Rev 1.4.7 - Owner dashboard and group administration.
- [x] Rev 1.4.8 - Managed invites and guarded group deletion.
- [x] Rev 1.5.0 - Quick check-ins and manual trip/ETA sharing.
- [x] Rev 1.5.3 - PWA install support, offline messaging, caching, and appearance modes.
- [x] Rev 1.5.4 - Login throttling, consent review, and security/audit cleanup.
- [x] Rev 1.5.5 - Owner-managed geofence places with arrival/departure notices.
- [x] Rev 1.5.8 - Member-list flicker and repeated DOM rebuild fix.
- [x] Rev 1.5.9 - Privacy summary, guarded account deletion, direct owner export, and status banners.
- [x] Rev 1.6.12 - Owner-only permanent member account deletion and owner-assisted member password reset.

## Live data folder rule

- [x] Existing runtime folders retain `.placeholder` files.
- [x] Rev 1.5.9 added no new live-data folder.

## Next practical improvements

- Add loading skeletons.
- Add a PNG icon set for broader home-screen compatibility.

## Security hardening

- Add CSP headers after testing CDN and PWA dependencies.

## Known limitations

- Geofence evaluation requires at least one signed-in active-group page to remain open.
- Service-worker caching covers the static app shell, not authenticated writes.
- Trip ETA remains manually entered.
- Browser GPS may pause when the phone sleeps, locks, or the browser is backgrounded.
