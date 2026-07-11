<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.5.5
Description: Setup, deployment, privacy, security, membership, PWA, appearance, and geofence notes.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker

Current Project Revision: **1.5.5**

A PHP + JSON consent-based family and friend-circle location-sharing app for shared hosting. It includes browser GPS, multiple groups, persistent sessions, mobile map fallbacks, account security, member management, owner administration, check-ins, trip sharing, trail retention, geofence places, and installable PWA support.

## Location permission behavior

The app intentionally keeps the automatic location request after login/page launch. The browser or operating system controls the native permission prompt text and buttons.

## Geofence places

Rev 1.5.5 adds owner-managed places such as Home, School, Work, Church, or Grandma's House.

Each place stores:

- name;
- latitude and longitude;
- radius from 100 meters to 2 kilometers;
- creation metadata;
- per-member inside/outside state.

Owners can enter coordinates manually or copy their latest saved active-group location into the place form. The open app evaluates the latest saved locations approximately once per minute and creates group notices and audit events when a member arrives at or leaves a place.

Geofence evaluation is browser-driven. It runs while at least one signed-in page for the active group is open; it is not a server background service.

## Security hardening

The app includes file-backed login throttling, versioned consent review, clearer disabled-access messaging, remembered-device cleanup, and 90-day audit cleanup.

## Current capabilities

- Multiple family, friend, trip, or other circles per account.
- Active-group map, members, managed invites, notices, and location sharing.
- Owner dashboard, membership disable/restore, ownership transfer, and safe leave-group controls.
- Owner-managed geofence places with arrival/departure notices.
- Quick check-ins and manual destination/ETA sharing.
- Password changes, remembered-device revocation, privacy details, consent review, login throttling, and guarded account deletion.
- Last-known member details, trail preview, map preferences, location health, and trail retention.
- Diagnostics and `health.php` deployment checks.

## Installable web app and appearance

The app includes a web app manifest, service-worker app-shell caching, online/offline status, home-screen guidance, Dark/Light/High Contrast appearance choices, and Comfortable/Compact layout density choices.

Authenticated API responses and offline location/account writes are not cached or queued.

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

Rev 1.5.5 adds no new live-data folder. Geofence definitions and state are stored in the existing active-group JSON record under `data/families/`.

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

- Rev 1.5.5 = geofence places and arrival/departure notices
- Rev 1.5.4 = login throttling, consent review, access messaging, and security/audit cleanup
- Rev 1.5.3 = PWA install, offline messaging, caching, and appearance modes
- Rev 1.5.2 = membership disable/restore and leave-group controls
- Rev 1.5.1 = trail retention, cleanup, and stale/restored notices
- Rev 1.5.0 = check-ins and manual trip/ETA sharing
- Rev 1.4.0 = multi-group/circle support

## Revision

Rev 1.5.5 - Geofence places and arrival/departure release.
