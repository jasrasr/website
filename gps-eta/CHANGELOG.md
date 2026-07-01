# GPS Speed + ETA Tracker Changelog

## Rev 1.8.4 - 2026-06-30

- Added `metric-combiner.js`.
- Combined Current Speed, Average Speed, and Max Speed into one compact `Speed Summary` row/card.
- Combined Elapsed Tracking Time, Moving Time, and Stopped Time into one compact `Time Summary` row/card.
- Hid the older standalone speed and time cards to reduce vertical scrolling on mobile.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.3 - 2026-06-30

- Updated the on-page future-feature note so it no longer lists already-added items as future work.
- Clarified that GPS update age and trip/session start time are already included.
- Clarified that manual route correction factor, pause count, distance per GPS ping, live moving-average speed, and background resume recovery remain useful non-map improvements.
- Clarified that only true road-route distance and traffic-aware ETA require a maps/directions API.
- Hardened `.htaccess` so `/gps-eta/` prefers only `index-secure.php`, direct `index.php` requests are routed to the wrapper, and page caching is reduced.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.2 - 2026-06-30

- Combined GPS Accuracy, GPS Quality, and GPS Update Age into one compact `GPS Signal` row/card.
- Hid the older standalone GPS Accuracy card to reduce vertical scrolling on mobile.
- Kept GPS signal rendering client-side only; no history schema change, reset, or migration is required.

## Rev 1.8.1 - 2026-06-30

- Tightened the mobile setup card by keeping the starting distance input and unit selector on the same row.
- Moved the unit selector, such as `Miles`, up to the right of the distance entry box on narrow screens.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.0 - 2026-06-30

- Added `trip-store.php`, a real PHP-backed trip-session storage schema separate from the existing snapshot history store.
- Added `session-store.js` to dual-write trip snapshots and end-trip summaries into the PHP trip store.
- Added a PHP Stored Trips section to show trips saved in the new trip-session schema.
- New trip-session data is stored under `gps-eta/data/trip-sessions/` on the web host.
- Existing snapshot history under `gps-eta/data/device-history/` is preserved and not wiped.
- No history reset or migration is required; this is additive storage going forward.
- Updated `index-secure.php` to load Rev `1.8.0` helper files.

## Rev 1.7.0 - 2026-06-30

- Added `gps-quality.js` to show GPS quality from browser-exposed accuracy and update timing.
- Added a GPS Quality card with labels such as `Excellent`, `Good`, `Usable`, `Weak`, `Poor`, `Stale`, or `Unknown`.
- Added a GPS Update Age card to show how fresh the latest browser GPS update is.
- Added speed and heading source labels showing whether the browser supplied native values or the app calculated them from movement.
- Updated `index-secure.php` to load the GPS quality helper and display Rev `1.7.0` from the wrapper.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.6.0 - 2026-06-30

- Added `trip-sessions.js` to group existing saved snapshots into trip sessions without changing the server history schema.
- Added an `End Trip` button that logs an `end` snapshot and builds a trip summary.
- Added a Trip Sessions section showing recent grouped trips with start time, end time, status, starting distance, tracked distance, remaining distance, elapsed time, max speed, and snapshot count.
- Updated `index-secure.php` to load the trip-session helper and display Rev `1.6.0` from the wrapper.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.5.1 - 2026-06-30

- Added a wrapper entrypoint, `index-secure.php`, which loads `index.php` and appends `ui-render.js`.
- Added `ui-render.js` to render Trip Log and Device History rows using DOM text nodes instead of raw HTML strings.
- Updated `.htaccess` so `/gps-eta/` prefers `index-secure.php` before `index.php`.
- Preserved the existing server history format; no history reset or migration is required.

## Rev 1.5.0 - 2026-06-30

- Added server-side per-device history logging using a browser-generated local device ID.
- Added automatic retention pruning so history older than 365 days is removed on history read/write.
- Added a front-end Device History section showing retained history for the current browser/device.
- Added a `Delete My History` button so the current browser/device can delete its saved server history.
- Added PHP JSON API actions for logging, reading, and deleting device history.
- Added protected `gps-eta/data/.htaccess` rules to block direct browser access to raw history files.
- Incremented the visible app revision from `1.4.0` to `1.5.0`.

## Rev 1.4.0 - 2026-06-30

- Moved `Log Snapshot` and `Export CSV` from the top control area to the bottom Trip Log section.
- Moved live dynamic trip data higher on the page, directly after the trip setup controls.
- Added a compass heading card with a visual needle, degree value, and cardinal/intercardinal direction such as `N`, `NE`, `SSW`, or `W`.
- Added fallback heading calculation from GPS movement when native device heading is unavailable.
- Added `Trip Progress`, `Stopped Time`, and `Current Pace` metrics.
- Added heading to the Trip Log table and CSV export.
- Added a short on-page note listing useful future calculations.

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
