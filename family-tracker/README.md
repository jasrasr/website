<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.5.1
Description: Setup, deployment, privacy, security, owner administration, presence, and trail-retention notes.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker

Current Project Revision: **1.5.1**

A PHP + JSON consent-based family and friend-circle location-sharing app for shared hosting. It includes browser GPS, multiple groups, persistent sessions, mobile map fallbacks, account security, member management, owner administration, check-ins, trip sharing, and trail retention.

## Location permission behavior

The app intentionally keeps the automatic location request after login/page launch. The browser or operating system controls the native permission prompt text and buttons.

## Current capabilities

- Multiple family, friend, trip, or other circles per account.
- Active-group map, members, managed invites, notices, and location sharing.
- Quick check-ins: I'm OK, On My Way, Arrived, and Need Help.
- Manual destination and ETA sharing with end-trip control.
- Owner dashboard, ownership transfer, group export, audit history, and activity.
- Owner member management with nicknames, relationships, colors, joined dates, and remove-from-group.
- Password changes, remembered-device revocation, privacy details, and guarded account deletion.
- Last-known member details, trail preview, history map, map preferences, and external map links.
- Diagnostics and `health.php` deployment checks.

## Trail retention and location health

Rev 1.5.1 adds a main-page Location Health panel.

Owners can choose active-group trail retention of:

- 24 hours;
- 7 days;
- 30 days;
- 90 days.

Owners can run cleanup immediately. Signed-in users also trim their own matching active-group trail during periodic status monitoring.

The page checks active-group member location state approximately once per minute while open. It can create group notices when:

- a previously live location becomes stale;
- a stale or missing member starts sharing a current location again.

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

Rev 1.5.1 adds no new live-data folder.

## Requirements

- PHP 8.0 or newer recommended.
- HTTPS for browser geolocation and secure cookies.
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

- Rev 1.5.1 = trail retention, cleanup, and stale/restored location notices
- Rev 1.5.0 = check-ins and manual trip/ETA sharing
- Rev 1.4.9 = privacy and account lifecycle
- Rev 1.4.8 = managed invites and guarded group deletion
- Rev 1.4.7 = owner dashboard and group administration
- Rev 1.4.6 = map modes and center controls
- Rev 1.4.5 = member detail and active-group trail tools
- Rev 1.4.4 = owner member management and member metadata
- Rev 1.4.0 = multi-group/circle support

## Revision

Rev 1.5.1 - Trail retention and location-health release.
