<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.5.3
Description: Setup, deployment, privacy, security, membership, PWA, and appearance notes.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker

Current Project Revision: **1.5.3**

A PHP + JSON consent-based family and friend-circle location-sharing app for shared hosting. It includes browser GPS, multiple groups, persistent sessions, mobile map fallbacks, account security, member management, owner administration, check-ins, trip sharing, trail retention, and installable PWA support.

## Location permission behavior

The app intentionally keeps the automatic location request after login/page launch. The browser or operating system controls the native permission prompt text and buttons.

## Current capabilities

- Multiple family, friend, trip, or other circles per account.
- Active-group map, members, managed invites, notices, and location sharing.
- Owner dashboard, membership disable/restore, ownership transfer, and safe leave-group controls.
- Quick check-ins and manual destination/ETA sharing.
- Password changes, remembered-device revocation, privacy details, and guarded account deletion.
- Last-known member details, trail preview, map preferences, location health, and trail retention.
- Diagnostics and `health.php` deployment checks.

## Installable web app and appearance

Rev 1.5.3 adds:

- `manifest.webmanifest`;
- service-worker app-shell caching;
- online/offline status messaging;
- home-screen install guidance;
- Dark, Light, and High Contrast appearance choices;
- Comfortable and Compact layout density choices;
- a manual cached-app update check.

Appearance and density choices are stored in browser local storage for that device.

The service worker intentionally does not cache authenticated API responses or queue offline location/account writes. When offline, cached pages may open, but updates require a network connection.

On iPhone or iPad, use **Share → Add to Home Screen**. Android and desktop browsers may expose an **Install App** option. The current icon is SVG; adding PNG icons later may improve compatibility with older iOS versions.

## Trail retention and location health

Owners can choose active-group trail retention of 24 hours, 7 days, 30 days, or 90 days and run cleanup immediately. The open app periodically checks live, stale, and missing location states and can create group notices for stale/restored transitions.

This monitoring is browser-driven; it does not run when nobody has the app open.

## Data storage

Runtime data uses the existing folders:

- `data/users/`
- `data/families/`
- `data/locations/`
- `data/trails/`
- `data/notices/`
- `data/persistent_logins/`
- `data/locks/`
- `data/audit/`

Rev 1.5.3 adds no new live-data folder.

## Requirements

- PHP 8.0 or newer recommended.
- HTTPS for browser geolocation, secure cookies, service workers, and installation behavior.
- Apache-compatible hosting for the included `.htaccess` protections.
- A browser supporting `navigator.geolocation`.

## Install

1. Upload the complete `family-tracker` directory.
2. Confirm all runtime data folders are writable by PHP.
3. Open the site over HTTPS.
4. Create the first account and group.
5. Use Groups / Circles to create or join additional groups.
6. Open `health.php` while signed in to check permissions and deployment protection.

## Stronger production setup

Move `data/` outside the public web root and update `includes/config.php` when hosting permits it:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

## Revision history summary

- Rev 1.5.3 = PWA install, offline messaging, caching, and appearance modes
- Rev 1.5.2 = membership disable/restore and leave-group controls
- Rev 1.5.1 = trail retention, cleanup, and stale/restored notices
- Rev 1.5.0 = check-ins and manual trip/ETA sharing
- Rev 1.4.9 = privacy and account lifecycle
- Rev 1.4.8 = managed invites and guarded group deletion
- Rev 1.4.7 = owner dashboard and group administration
- Rev 1.4.0 = multi-group/circle support

## Revision

Rev 1.5.3 - Installable PWA and saved appearance release.
