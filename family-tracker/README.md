<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.4.4
Description: Setup, deployment, privacy, account security, member management, and maintenance notes for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker

Current Project Revision: **1.4.4**

A small PHP + JSON backend site for consent-based family and friend-circle location sharing. It is designed for shared hosting: browser GPS, persistent login cookies, no database, protected JSON storage, mobile-friendly maps, and multiple share groups per user account.

## What it does

- Create the first group when creating a new account.
- Create additional groups/circles from the signed-in app.
- Join additional groups/circles with invite codes without creating another login.
- Switch the active group; the active group controls the map, member list, invite code, and location updates.
- Rename the active group when signed in as that group owner.
- Owner-only member management for the active group.
- Set per-group member nicknames, relationship labels, and colors.
- Remove a member from the active group without deleting that user's account.
- Show joined-at dates and duplicate display-label warnings.
- Edit display name from the Account & Group settings card.
- Change password and revoke remembered devices.
- List remembered devices and revoke one or all of them.
- Download a signed-in user's own data export.
- Show member username and short ID for troubleshooting duplicate names.
- Separate group members into Live / Recent, Stale, and No Location Yet sections.
- Choose member location text format: closest city, rounded GPS, or both.
- Refresh closest-city labels manually.
- Use diagnostics to check GPS permission, session/API timing, online status, active group, and build revision.
- Use `health.php` to verify folder permissions and deployment protection checks.
- Track per-group owner/member roles.
- Login/logout with 30-day session cookies and optional Remember Me persistent login.
- Request and save browser GPS location while the page is open.
- View active-group members on a map.
- Show server-stored group notices and per-user dismissals.
- View shared trail history on `history.php`.

## Member management

Owners see a **Member Management** card for the active group. It supports:

- group nickname;
- relationship label;
- color selection;
- joined-at visibility;
- duplicate display-label warning;
- remove from active group.

Removing a member from a group updates that user's group membership but does not delete the account or other group memberships.

## Multi-group behavior

The app still stores group records in `data/families/` for backward compatibility, but the UI now treats them as groups/circles.

- One user account can belong to multiple groups.
- A user can own one group and be a member of another.
- The **Groups / Circles** card lets the user create, join, or switch groups.
- Group owners can regenerate the invite code for the active group.
- Group owners can rename the active group.
- Existing users are automatically treated as members of their current original family/group.

## Account security

The Account Security & Data card supports password change, remembered-device list, revoke one remembered device, revoke all remembered devices, and download-my-data export.

Changing the password revokes remembered-device tokens. The current browser session remains active until logout.

## Health check

Open `health.php` while signed in to check writable runtime folders, expected `.placeholder` files, `.htaccess` protection files, HTTPS detection, session username, PHP version, and resolved `DATA_DIR`.

## Requirements

- PHP 8.0 or newer recommended.
- HTTPS is required by modern browsers for precise browser geolocation and secure cookies.
- Apache-compatible hosting if you want the included `.htaccess` protection to work.
- A browser that supports `navigator.geolocation`.

## Install

1. Upload the entire `family-tracker` folder to your web host.
2. Confirm these folders are writable by PHP: `data/users/`, `data/families/`, `data/locations/`, `data/trails/`, `data/notices/`, `data/persistent_logins/`, `data/locks/`, and `data/audit/`.
3. Open the site in a browser over HTTPS.
4. Use **Create First Group** to create the first owner account and group.
5. Use **Groups / Circles** to create friend groups, trip groups, or other separate circles.

## Revision numbering

This project starts at **1.0.0**, not 0.x.x.

- Rev 1.4.4 = owner member management, nicknames, relationship labels, member colors, joined-at, duplicate warnings, and remove-from-group
- Rev 1.4.3 = account security, remembered devices, data export, group notices, and member status sections
- Rev 1.4.2 = account settings, group rename, diagnostics, health check, and location display options
- Rev 1.4.1 = invite-code UI cleanup
- Rev 1.4.0 = multi-group/circle support

## Live data placeholder rule

Runtime JSON data is intentionally stored under `data/`. Any folder that receives live app data must have a `.placeholder` file committed to Git so the folder exists after deployment.

Current live-data folders with placeholders:

- `data/users/.placeholder`
- `data/families/.placeholder`
- `data/locations/.placeholder`
- `data/trails/.placeholder`
- `data/notices/.placeholder`
- `data/persistent_logins/.placeholder`
- `data/locks/.placeholder`
- `data/audit/.placeholder`

Rev 1.4.4 adds no new live-data folder.

## Stronger production setup

The included `data/.htaccess` blocks direct web access to JSON files on Apache. Better still, move `data/` outside `public_html` and update `includes/config.php`:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

That is safer than trusting web-server rules alone.

## Revision

Rev 1.4.4 - Owner member management, nicknames, relationship labels, member colors, joined-at display, duplicate warnings, and remove-from-group.
