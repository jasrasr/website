# GPS Speed + ETA Tracker

Mobile-friendly GPS speed, ETA, compass heading, trip logging, and per-device history tracker for `jasr.me`.

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
├─ index.php
├─ CHANGELOG.md
├─ README.md
├─ .htaccess
└─ data/
   ├─ .htaccess
   ├─ device-history.sample.json
   └─ device-history/
      └─ .gitkeep
```

## Features

- Browser GPS location tracking.
- Current speed and smoothed speed.
- ETA based on entered distance and current speed.
- Automatic distance remaining countdown using GPS breadcrumb distance.
- Compass heading with degrees and cardinal/intercardinal direction.
- Trip progress, moving time, stopped time, average speed, max speed, and pace.
- Manual and automatic trip log snapshots.
- CSV export.
- Per-device server history using a browser-generated local device ID.
- 365-day automatic server-side history retention.
- Front-end delete option for the current device history.
- Changelog rendered dynamically from `CHANGELOG.md`.

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

## Security and validation

The PHP code treats all browser data as plain data, not executable code.

- No user-provided value is executed or included as PHP.
- Device IDs are sanitized and then hashed before being used in filenames.
- History files are written as JSON files.
- API writes require POST.
- History entry strings are length-limited.
- Numeric fields are accepted only when numeric.
- `CHANGELOG.md` rendering escapes HTML before rendering limited Markdown.
- `data/.htaccess` blocks direct browser access to raw saved history files.

## Development rules

- Keep `CHANGELOG.md` as the source of truth for visible changelog content.
- Increment the revision number when functionality changes.
- Do not change the history schema for cosmetic-only updates.
- Do not reset or overwrite existing runtime history unless a functional change requires it.
- If a feature change affects history format, document the compatibility behavior in `CHANGELOG.md`.
- Keep sample files updated when the history format changes.

## History sample

Reference file:

```text
gps-eta/data/device-history.sample.json
```
