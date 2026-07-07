<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.2.0
Description: Setup, deployment, privacy, and maintenance notes for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker

Current Project Revision: **1.2.0**

A small PHP + JSON backend site for consent-based family location sharing. It is designed for shared hosting and mirrors the lightweight pattern from the `gps-eta` project: browser GPS, no database, protected JSON storage, mobile-first UI, Leaflet/OpenStreetMap maps, and shared breadcrumb history.

## What it does

- Create a family group with one owner account.
- Join an existing family group with an invite code.
- Login/logout with PHP sessions.
- Share your current browser GPS location with your family group.
- View family members on a live map.
- Show a Family Notices card when another member joins during an active session.
- Copy a visible generated invite code.
- Require two confirmations before regenerating an invite code.
- View shared family trail history on `history.php`.
- Filter trail history by all members or a single member.
- Store latest location in JSON.
- Store a short per-user breadcrumb trail in JSON.
- Delete your own stored location and trail.
- Regenerate family invite codes as the owner.

## Requirements

- PHP 8.0 or newer recommended.
- HTTPS is required by modern browsers for precise browser geolocation.
- Apache-compatible hosting if you want the included `.htaccess` protection to work.
- A browser that supports `navigator.geolocation`.

## Install

1. Upload the entire `family-tracker` folder to your web host, for example:
   - `public_html/github/family-tracker/`
   - or `public_html/family-tracker/`

2. Confirm these folders are writable by PHP:
   - `data/users/`
   - `data/families/`
   - `data/locations/`
   - `data/trails/`
   - `data/locks/`
   - `data/audit/`

3. Open the site in a browser over HTTPS.

4. Use **Create Family** to create the first owner account.

5. Copy the one-time invite code shown after setup, or use **Regenerate Invite Code** later.

6. Have each family member join with the invite code on their own device.

## Invite-code behavior

- The full invite code is visible only immediately after family creation or regeneration.
- The Copy Code button copies the full code only while the full code is visible.
- Regenerate Invite Code shows two confirmations because the current code stops working after regeneration.

## Revision numbering

This project starts at **1.0.0**, not 0.x.x.

- Rev 1.0.0 = initial scaffold
- Rev 1.1.0 = history/trails feature
- Rev 1.1.1 = header, placeholder, and revision-numbering audit
- Rev 1.2.0 = join notices and invite-code safety controls

## Live data placeholder rule

Runtime JSON data is intentionally stored under `data/`. Any folder that receives live app data must have a `.placeholder` file committed to Git so the folder exists after deployment.

Current live-data folders with placeholders:

- `data/users/.placeholder`
- `data/families/.placeholder`
- `data/locations/.placeholder`
- `data/trails/.placeholder`
- `data/locks/.placeholder`
- `data/audit/.placeholder`

No new live-data folder was added for Rev 1.2.0.

Do not commit runtime JSON files from these folders. Only the `.placeholder` files belong in Git.

## Stronger production setup

The included `data/.htaccess` blocks direct web access to JSON files on Apache. Better still, move `data/` outside `public_html` and update `includes/config.php`:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

That is safer than trusting web-server rules alone.

## Revision

Rev 1.2.0 - Join notices and invite-code safety controls.
