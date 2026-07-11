<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.4.7
Description: Setup, deployment, privacy, account security, owner administration, and maintenance notes for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-11
-->

# Family GPS Tracker

Current Project Revision: **1.4.7**

A PHP + JSON consent-based family and friend-circle location-sharing app for shared hosting. It includes persistent sessions, browser GPS, multiple groups, protected JSON storage, mobile-friendly maps, account security, member management, and owner administration.

## Location permission behavior

The app intentionally keeps the existing automatic location request after login/page launch. The browser or operating system controls the native permission prompt text and buttons.

## Main capabilities

- Multiple family, friend, trip, or other circles per account.
- Active-group map, member list, invite code, notices, and location sharing.
- Automatic location request while signed in and periodic updates while the page is open.
- Display-name editing and active-group rename.
- Owner member management with nicknames, relationships, colors, joined dates, and remove-from-group.
- Password changes, remembered-device revocation, and user-data export.
- Last-known member detail, trail preview, history map, and external map links.
- Map mode preferences and center-on-member controls.
- Diagnostics and `health.php` deployment checks.

## Owner dashboard

Owners can open `owner-dashboard.php` from the active-group Member Management card.

The dashboard includes:

- centralized group name, description, and color settings;
- active-group member summary;
- ownership transfer to another active member;
- permanent recent activity feed;
- searchable owner-filtered audit history;
- owner-controlled active-group JSON export.

Ownership transfer is immediate. The previous owner becomes a regular member, and the selected member becomes owner.

## Data storage

Group records remain under `data/families/` for backward compatibility. Runtime data uses the existing folders:

- `data/users/`
- `data/families/`
- `data/locations/`
- `data/trails/`
- `data/notices/`
- `data/persistent_logins/`
- `data/locks/`
- `data/audit/`

Rev 1.4.7 adds no new live-data folder.

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

- Rev 1.4.7 = owner dashboard, group settings, ownership transfer, activity, audit, and group export
- Rev 1.4.6 = map modes and center controls
- Rev 1.4.5 = member detail and active-group trail tools
- Rev 1.4.4 = owner member management and member metadata
- Rev 1.4.3 = account security, devices, export, notices, and status sections
- Rev 1.4.2 = account settings, diagnostics, and health check
- Rev 1.4.0 = multi-group/circle support

## Revision

Rev 1.4.7 - Owner administration dashboard release.
