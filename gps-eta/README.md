# GPS Speed + ETA Tracker

Mobile-friendly GPS speed, ETA, compass heading, trip logging, trip sessions, GPS quality estimate, and per-device history tracker for `jasr.me`.

This project is stored in GitHub as source, then synced to Jason's web host where PHP is processed. It is intended to run from the hosted site, not from GitHub Pages.

```text
https://jasr.me/github/gps-eta/
```

## Runtime model

- GitHub stores source files.
- The web host processes PHP.
- Runtime history JSON files are created on the web host.
- Runtime history files are not meant to be committed back to GitHub.

## Files

```text
gps-eta/
├─ index-secure.php
├─ index.php
├─ ui-render.js
├─ trip-sessions.js
├─ gps-quality.js
├─ CHANGELOG.md
├─ README.md
├─ history.sample.txt
├─ .htaccess
└─ data/
   └─ .htaccess
```

The PHP runtime creates this folder on the host when needed:

```text
gps-eta/data/device-history/
```

## Features

- Browser GPS location tracking.
- Current speed and smoothed speed.
- ETA based on entered distance and current speed.
- Automatic distance remaining countdown using GPS breadcrumb distance.
- Compass heading with degrees and cardinal/intercardinal direction.
- GPS quality estimate based on accuracy and update age.
- GPS update age display.
- Speed and heading source labels.
- Trip progress, moving time, stopped time, average speed, max speed, and pace.
- Manual and automatic trip log snapshots.
- End Trip button.
- Trip Sessions view grouped from existing saved snapshots.
- CSV export.
- Per-device server history using a browser-generated local device ID.
- 365-day automatic server-side history retention.
- Front-end delete option for the current device history.
- Changelog rendered dynamically from `CHANGELOG.md`.

## Entry point

`/gps-eta/` loads `index-secure.php` first by `.htaccess`. The wrapper loads `index.php`, appends `ui-render.js`, appends `trip-sessions.js`, and appends `gps-quality.js`.

## GPS quality

A normal browser page cannot read satellite count, satellite IDs, or raw signal data. GPS quality is estimated from browser-exposed values:

- horizontal accuracy
- last GPS update age
- whether native speed is available
- whether native heading is available

## Trip sessions

Trip Sessions are derived from existing history snapshots. This avoids changing the server-side history file format.

- A `start` snapshot begins a trip.
- An `end` snapshot closes a trip.
- `auto`, `manual`, and `pause` snapshots remain inside the active trip.
- Existing runtime history does not need to be reset or migrated.

## Device history behavior

The app creates a local browser device ID and stores it in `localStorage`. That ID is sent to the PHP endpoint when saving, reading, or deleting history. PHP hashes the device ID before using it as a filename.

Runtime history location on the host:

```text
gps-eta/data/device-history/<sha256-device-id>.json
```

## Retention policy

Server-side history is pruned on every history read/write.

```text
Retention: 365 days
```

Entries older than 365 days are automatically removed. The app also caps retained entries to avoid unbounded file growth.

## Validation notes

- Device IDs are sanitized and then hashed before being used in filenames.
- History files are written as JSON files, not PHP files.
- API writes require POST.
- History entry strings are length-limited before being saved.
- Numeric fields are accepted only when numeric.
- `CHANGELOG.md` rendering escapes HTML before rendering limited Markdown.
- `ui-render.js` renders Trip Log and Device History cells with `textContent`.
- `trip-sessions.js` renders Trip Sessions cells with `textContent`.
- `gps-quality.js` only reads browser GPS fields and does not write history.
- `data/.htaccess` blocks direct browser access to raw saved history files.

## Development rules

- Keep `CHANGELOG.md` as the source of truth for visible changelog content.
- Increment the revision number when functionality changes.
- Add new features and functions when useful; do not avoid feature work only because history exists.
- Preserve existing runtime history by default.
- Do not reset or wipe runtime history unless a feature/function requires a breaking history reload.
- If a feature change affects history format, document whether migration or wipe is required in `CHANGELOG.md`.
- Keep sample files updated when the history format changes.

## History sample

Reference file:

```text
gps-eta/history.sample.txt
```
