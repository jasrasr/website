# Time Clock

A full-screen kiosk display showing the current time, date, weather, and scrolling alerts. Deployed at [jasr.me/time-clock](https://jasr.me/time-clock) and running on a Raspberry Pi CM4 kiosk.

## Features

- **Live clock** — 12-hour format with AM/PM, synced to `worldtimeapi.org` every 30 minutes
- **Date display** — full weekday, month, day, year
- **Weather** — current temperature (°F), conditions, and precipitation chance via [Open-Meteo](https://open-meteo.com/) (no API key required), refreshed every 10 minutes
- **Scrolling alerts bar** — full-width marquee at the bottom, refreshed every 60 seconds from `alerts.json`
- **Admin panel** — PIN-protected page to add, remove, reorder, and publish alerts
- **Responsive** — works on desktop, mobile portrait, and mobile landscape

## Files

| File | Purpose |
|---|---|
| `index.html` | Main display page |
| `admin.html` | Alerts admin panel (PIN protected) |
| `alerts.json` | Current alerts data (read by display, written by admin) |
| `save-alerts.php` | Backend API — handles load and save of alerts.json |
| `.htaccess` | Sets `index.html` as default page; blocks access to config files |
| `logo.png` | Company logo shown in top-left |

## Alert Types

| Type | Color | Use |
|---|---|---|
| `info` | Blue | General notices |
| `warning` | Orange | Caution messages |
| `urgent` | Red | Urgent notices |
| `watch` | Large yellow | Safety watches |
| `severe` | Large red | Severe warnings |

## Admin Panel

Visit `jasr.me/time-clock/admin.html`

- Enter PIN to unlock (demo PIN: `changeme`)
- Add alerts with a message and type
- Reorder with up/down arrows
- Click **Save & Publish** — changes appear on the clock within 60 seconds

## Weather

- Location: Brookfield Township, OH (44420)
- Source: [Open-Meteo API](https://open-meteo.com/) — free, no API key
- Shows: temperature, conditions, rain/snow chance
- Emoji glyphs (🌧 ❄) rendered via [Noto Color Emoji](https://fonts.google.com/noto/specimen/Noto+Color+Emoji) CDN

## Raspberry Pi Kiosk Setup

Runs on a Raspberry Pi CM4 with HDMI display, Raspberry Pi OS Trixie (Debian 13), Wayland/Wayfire.

See [`rpi/NOTES.md`](rpi/NOTES.md) for the full setup guide including:
- Chromium kiosk mode flags for Wayland
- Autostart via `wayfire.ini`
- Network-wait boot script
- Screen blanking disable
- Custom boot splash (Plymouth) — not yet implemented

## Refresh Intervals

| Item | Interval |
|---|---|
| Clock tick | 1 second |
| Time sync (worldtimeapi.org) | 30 minutes |
| Weather (open-meteo.com) | 10 minutes |
| Alerts (alerts.json) | 1 minute |
