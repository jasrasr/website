<!--
Project: Family GPS Tracker
File: TASKS.md
Revision: 1.5.4
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
- [x] Rev 1.4.4 - Owner member management and member metadata.
- [x] Rev 1.4.5 - Member detail, active-group trails, and history links.
- [x] Rev 1.4.6 - Map modes and center controls.
- [x] Rev 1.4.7 - Owner dashboard and group administration.
- [x] Rev 1.4.8 - Managed invites and guarded group deletion.
- [x] Rev 1.4.9 - Privacy details and guarded account deletion.
- [x] Rev 1.5.0 - Quick check-ins and manual trip/ETA sharing.
- [x] Rev 1.5.1 - Trail retention, cleanup, and stale/restored location notices.
- [x] Rev 1.5.2 - Temporary member disable/restore and safe leave-group control.
- [x] Rev 1.5.3 - PWA install support, offline messaging, app-shell caching, and appearance modes.
- [x] Rev 1.5.4 - Login throttling, consent review, access messaging, and security/audit cleanup.

## Live data folder rule

- [x] Existing runtime folders retain `.placeholder` files.
- [x] Rev 1.5.4 added no new live-data folder.

## Next practical improvements

- Add optional geofence zones.
- Add routing-provider ETA calculations.
- Add loading skeletons and clearer success/error banners.
- Add optional profile pictures or avatars.
- Add a dedicated account/profile page.

## Security hardening

- Move `data/` outside the public web root.
- Add password reset flow.
- Add optional TOTP MFA for owner accounts.
- Add CSP headers after testing CDN and PWA dependencies.

## Known limitations

- Login throttling uses shared-hosting JSON/lock files rather than a centralized cache.
- Audit cleanup runs only when a signed-in user explicitly starts cleanup.
- Service-worker caching covers the app shell, not authenticated API responses or offline writes.
- SVG home-screen icons may not be honored by every iOS version; a PNG icon set remains useful.
- Stale/restored monitoring runs while at least one signed-in group page is open.
- Trip ETA remains manually entered.
- Browser GPS may pause when the phone sleeps, locks, or the browser is backgrounded.
