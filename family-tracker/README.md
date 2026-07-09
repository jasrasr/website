<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.4.2
Description: Setup, deployment, privacy, and maintenance notes for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker

Current Project Revision: **1.4.2**

A small PHP + JSON backend site for consent-based family and friend-circle location sharing. It is designed for shared hosting: browser GPS, persistent login cookies, no database, protected JSON storage, mobile-friendly maps, and multiple share groups per user account.

## What it does

- Create the first group when creating a new account.
- Create additional groups/circles from the signed-in app.
- Join additional groups/circles with invite codes without creating another login.
- Switch the active group; the active group controls the map, member list, invite code, and location updates.
- Rename the active group when signed in as that group owner.
- Edit display name from the Account & Group settings card.
- Show member username and short ID for troubleshooting duplicate names.
- Choose member location text format: closest city, rounded GPS, or both.
- Refresh closest-city labels manually.
- Use diagnostics to check GPS permission, session/API timing, online status, active group, and build revision.
- Use `health.php` to verify folder permissions and deployment protection checks.
- Track per-group owner/member roles.
- Login/logout with 30-day session cookies and optional Remember Me persistent login.
- Request and save browser GPS location while the page is open.
- View active-group members on a map.
- Show server-stored notices and per-user dismissals.
- View shared trail history on `history.php`.

## Multi-group behavior

The app still stores group records in `data/families/` for backward compatibility, but the UI now treats them as groups/circles.

- One user account can belong to multiple groups.
- A user can own one group and be a member of another.
- The **Groups / Circles** card lets the user create, join, or switch groups.
- Group owners can regenerate the invite code for the active group.
- Group owners can rename the active group.
- Existing users are automatically treated as members of their current original family/group.

## Health check

Open `health.php` while signed in to check:

- writable runtime folders;
- expected `.placeholder` files;
- presence of `.htaccess` protection files;
- HTTPS detection;
- current session username;
- PHP version and resolved `DATA_DIR`.

## Requirements

- PHP 8.0 or newer recommended.
- HTTPS is required by modern browsers for precise browser geolocation and secure cookies.
- Apache-compatible hosting if you want the included `.htaccess` protection to work.
- A browser that supports `navigator.geolocation`.

## Install

1. Upload the entire `family-tracker` folder to your web host.
2. Confirm these folders are writable by PHP:
   - `data/users/`
   - `data/families/`
   - `data/locations/`
   - `data/trails/`
   - `data/notices/`
   - `data/persistent_logins/`
   - `data/locks/`
   - `data/audit/`
3. Open the site in a browser over HTTPS.
4. Use **Create First Group** to create the first owner account and group.
5. Use **Groups / Circles** to create friend groups, trip groups, or other separate circles.

## Revision numbering

This project starts at **1.0.0**, not 0.x.x.

- Rev 1.4.2 = account settings, group rename, diagnostics, health check, and location display options
- Rev 1.4.1 = invite-code UI cleanup
- Rev 1.4.0 = multi-group/circle support
- Rev 1.3.7 = closest-city latest-location labels
- Rev 1.3.6 = display-name editing
- Rev 1.3.5 = You/Owner member badges
- Rev 1.3.4 = mobile map fallback
- Rev 1.3.3 = mobile map layout repair attempt
- Rev 1.3.2 = app-updated notice and changelog page
- Rev 1.3.1 = mobile map and compact metrics
- Rev 1.3.0 = long session cookies and persistent login

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

Rev 1.4.2 adds no new live-data folder.

## Stronger production setup

The included `data/.htaccess` blocks direct web access to JSON files on Apache. Better still, move `data/` outside `public_html` and update `includes/config.php`:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

That is safer than trusting web-server rules alone.

## Revision

Rev 1.4.2 - Account settings, group rename, diagnostics, health check, and location display options.
