<!--
Project: Family GPS Tracker
File: README.md
Revision: 1.3.2
Description: Setup, deployment, privacy, and maintenance notes for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker

Current Project Revision: **1.3.2**

A small PHP + JSON backend site for consent-based family location sharing. It is designed for shared hosting and mirrors the lightweight pattern from the `gps-eta` project: browser GPS, persistent login cookies, no database, protected JSON storage, mobile-first UI, Leaflet/OpenStreetMap maps, and shared breadcrumb history.

## What it does

- Create a family group with one owner account.
- Join an existing family group with an invite code.
- Login/logout with 30-day long PHP session cookies.
- Supports optional Remember Me persistent login for this device.
- Request and save your current browser GPS location after login or session restore.
- Update your location about every 60 seconds while logged in and the page is open.
- Update your location when you return to the page after it was hidden.
- Start a higher-frequency GPS watch with **Start Sharing**.
- View family members on a live map with a constrained mobile map height.
- Use delayed and repeated Leaflet map-size refreshes to reduce partial map tiles after reload.
- Show compact GPS accuracy, speed, heading, and last-update cards.
- Show server-stored Family Notices when another member joins after your account was created.
- Let each user dismiss notices once so they do not reappear for that user.
- Show a dismissible app-updated notice with a changelog link when the app revision changes.
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
- HTTPS is required by modern browsers for precise browser geolocation and secure cookies.
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
   - `data/notices/`
   - `data/persistent_logins/`
   - `data/locks/`
   - `data/audit/`

3. Open the site in a browser over HTTPS.

4. Use **Create Family** to create the first owner account.

5. Copy the one-time invite code shown after setup, or use **Regenerate Invite Code** later.

6. Have each family member join with the invite code on their own device.

## Login and location behavior

- Session cookies last 30 days.
- The Remember Me checkbox creates a separate persistent login for that browser/device.
- Persistent login records are stored under `data/persistent_logins/` as hashed records.
- The persistent login cookie is HTTP-only, same-site Lax, and secure when the site is served over HTTPS.
- Logout revokes the current persistent login for that device.
- After login or session restore, the app requests GPS once and sends the current location if permission is granted.
- While logged in with the page open, the app requests GPS about every 60 seconds.
- Browser and phone power rules still apply: locked phones, closed tabs, backgrounded browsers, and battery saver may pause updates.
- The **Start Sharing** button still exists for a higher-frequency browser GPS watch while the page remains active.

## Map and metric behavior

- The mobile map height is capped so page scrolling is stable.
- Map dragging is disabled on coarse-pointer/mobile devices to keep the page from getting trapped while scrolling.
- Map setup is delayed until after the app panel is visible.
- The map is resized again after browser paint, resize, orientation changes, and family-location rendering.
- GPS accuracy, speed, heading, and last-update values are shown as compact metric cards.

## App update notice behavior

- When the app revision changes, the browser shows a small app-updated notice near the top of the page.
- The notice links to `changelog.php`.
- Dismissal is stored in the local browser for that revision only.

## Notice behavior

- Join notices are generated from server-side family membership records.
- A user sees member-joined notices for family members who joined after that user's account was created.
- Dismissing a notice stores that notice ID under `data/notices/` for that user and family.
- A dismissed notice should not appear again for that same user after refresh, page navigation, or login.

## Invite-code behavior

- The app stores the invite code as a hash plus the last four characters only.
- The full invite code is visible only immediately after family creation or regeneration.
- The Copy Code button copies the full code only while the full code is visible.
- Regenerate Invite Code shows two confirmations because the current code stops working after regeneration.

## Revision numbering

This project starts at **1.0.0**, not 0.x.x.

- Rev 1.0.0 = initial scaffold
- Rev 1.1.0 = history/trails feature
- Rev 1.1.1 = header, placeholder, and revision-numbering audit
- Rev 1.2.0 = join notices and invite-code safety controls
- Rev 1.2.1 = server-stored dismissible notices
- Rev 1.3.0 = long session cookies, persistent login, and automatic logged-in location updates
- Rev 1.3.1 = mobile map, compact metric, and invite-code explanation fixes
- Rev 1.3.2 = app-updated notice and map reload rendering fix

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

Do not commit runtime JSON files from these folders. Only the `.placeholder` files belong in Git.

## Stronger production setup

The included `data/.htaccess` blocks direct web access to JSON files on Apache. Better still, move `data/` outside `public_html` and update `includes/config.php`:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

That is safer than trusting web-server rules alone.

## Revision

Rev 1.3.2 - App-updated notice and map reload rendering fix.
