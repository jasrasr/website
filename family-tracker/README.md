# Family GPS Tracker

A small PHP + JSON backend site for consent-based family location sharing. It is intentionally designed for shared hosting and mirrors the lightweight pattern from the `gps-eta` project: browser GPS, no database, protected JSON storage, mobile-first UI, Leaflet/OpenStreetMap maps, and shared breadcrumb history.

## What it does

- Create a family group with one owner account.
- Join an existing family group with an invite code.
- Login/logout with PHP sessions.
- Share your current browser GPS location with your family group.
- View all family members with locations on a live map.
- View a separate history map with all connected members.
- Focus the history map on an individual member.
- View a member detail panel with latest location, speed, heading, accuracy, and age.
- Store latest location in JSON.
- Store a short per-user breadcrumb trail in JSON.
- Show shared trail history for all connected family members.
- Filter history by member and by time window.
- Delete your own stored location and trail.
- Regenerate family invite codes as the owner.

## What it does not do

- It does not secretly track anyone.
- It does not run reliably when a phone is locked or the browser is backgrounded.
- It does not provide emergency-grade location accuracy.
- It does not use a road-routing, traffic, or paid map API.
- It does not replace Life360. It is more like “Life360’s bargain-bin cousin who owns a PHP manual and a soldering iron.”

## How family linking works

1. The first person creates a family tracker and becomes the owner.
2. The owner copies the generated invite code.
3. Another family member opens the same site and chooses **Join Family**.
4. They enter the invite code, create their own username/password, and accept consent.
5. After login, they appear on the same family map once they share location.

Regenerating the invite code invalidates the prior invite code.

## GPS update behavior

Location sharing uses the browser's `navigator.geolocation.watchPosition()` call. That means the phone/browser decides when a fresh GPS position exists. The front end throttles writes so normal sharing sends no more than one server update about every 10 seconds. The family map refreshes every 15 seconds. The history map refreshes every 30 seconds. Locations older than 5 minutes are marked stale.

History points are stored each time a location is accepted by the server, up to `MAX_TRAIL_POINTS` per user.

## Map modes

- **Family Map**: fits the map around all members with current locations.
- **History Map**: shows latest markers plus breadcrumb trails.
- **Member filter**: shows all trails or one member's trail.
- **History window**: filters history to last hour, 4 hours, 12 hours, or 24 hours.
- **Show Everyone**: resets the history map back to all members.

All connected/logged-in members in the same family can view current locations and available trail history for that family.

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
├── trails.php
├── CHANGELOG.md
├── README.md
├── TASKS.md
├── .htaccess
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── js/history.js
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

Rev 0.2.0 - Shared history map, member focus, and member detail panel.
