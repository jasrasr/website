# Family GPS Tracker

A small PHP + JSON backend site for consent-based family location sharing. It is intentionally designed for shared hosting and mirrors the lightweight pattern from the `gps-eta` project: browser GPS, no database, protected JSON storage, mobile-first UI, and Leaflet/OpenStreetMap maps.

## What it does

- Create a family group with one owner account.
- Join an existing family group with an invite code.
- Login/logout with PHP sessions.
- Share your current browser GPS location with your family group.
- View family members on a live map.
- Store latest location in JSON.
- Store a short per-user breadcrumb trail in JSON.
- Delete your own stored location and trail.
- Regenerate family invite codes as the owner.

## What it does not do

- It does not secretly track anyone.
- It does not run reliably when a phone is locked or the browser is backgrounded.
- It does not provide emergency-grade location accuracy.
- It does not use a road-routing, traffic, or paid map API.
- It does not replace Life360. It is more like “Life360’s bargain-bin cousin who owns a PHP manual and a soldering iron.”

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

## Stronger production setup

The included `data/.htaccess` blocks direct web access to JSON files on Apache. Better still, move `data/` outside `public_html` and update `includes/config.php`:

```php
define('DATA_DIR', '/home/youruser/private-family-tracker-data');
```

That is safer than trusting web-server rules alone.

## Privacy model

This app stores location only after a logged-in user explicitly taps **Start Sharing** or **Update Once** and grants browser location permission. It is built for consensual household/family use only. Do not use it for hidden tracking, employee surveillance, or “I wonder where someone is” shenanigans. That is both creepy and legally spicy.

## File layout

```text
family-tracker/
├── index.php
├── api.php
├── CHANGELOG.md
├── README.md
├── TASKS.md
├── .htaccess
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── includes/
│   ├── config.php
│   ├── json-store.php
│   └── security.php
└── data/
    ├── .htaccess
    ├── users/.placeholder
    ├── families/.placeholder
    ├── locations/.placeholder
    ├── trails/.placeholder
    ├── locks/.placeholder
    └── audit/.placeholder
```

## Revision

Rev 0.1.0 - Initial scaffold.
