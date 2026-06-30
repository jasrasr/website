# GPS Speed + ETA Tracker Changelog

## Rev 1.3.0 - 2026-06-30

- Converted the app from static `index.html` to PHP-driven `index.php`.
- Made `CHANGELOG.md` the source of truth for the visible changelog rendered on the page.
- Added a small built-in Markdown renderer for headings, bullets, inline code, and bold text.
- Added `.htaccess` with `DirectoryIndex index.php index.html` so `/gps-eta/` prefers the PHP page.
- Removed the old static `index.html` entrypoint to avoid stale duplicate content.
- Incremented the visible app revision from `1.2.1` to `1.3.0`.

## Rev 1.2.1 - 2026-06-30

- Added a proper project/header block to `index.html`.
- Added JavaScript script metadata header inside the page script block.
- Incremented the visible page revision from `1.2.0` to `1.2.1`.
- Added an in-page changelog section so the current revision and recent changes are visible from the front end.
- Added this `CHANGELOG.md` file for repo history.

## Rev 1.2.0 - 2026-06-30

- Added Trip Mode.
- Added automatic distance remaining countdown based on GPS distance traveled.
- Added distance traveled, elapsed tracking time, moving time, average speed, and max speed.
- Added raw GPS data display for altitude, heading, and native GPS speed when available.
- Added trip log snapshots.
- Added manual log snapshot button.
- Added CSV export for trip logs.
- Added local browser trip restore after refresh.
- Kept GPS coordinates visible in the interface.

## Rev 1.0.0 - 2026-06-30

- Initial single-page GPS speed and ETA tracker.
- Added current speed, GPS accuracy, ETA duration, estimated arrival time, and last location display.
- Added support for miles and kilometers.
