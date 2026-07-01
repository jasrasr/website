# GPS Speed + ETA Tracker Changelog

## Rev 1.8.12 - 2026-06-30

- Added `control-state.js` to keep the main trip-control button label aligned with actual tracking state.
- When GPS tracking is active, the main button now shows `Tracking Active` instead of stale `Resume Tracking` text.
- When the trip is paused, the main button shows `Resume Tracking`.
- When no trip is active, the main button shows `Start Trip Tracking`.
- Pause button text and enabled state are also synced with the current tracking state.
- Updated `index-secure.php` to load `control-state.js?v=1.8.12`.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.11 - 2026-06-30

- Made sticky toolbar button states explicit.
- `Drive Mode` now shows `✓ Drive Mode On` when active and `Drive Mode Off` when inactive.
- `Map Size` now shows `✓ Large Map On` when active and `Map Normal` when inactive.
- Active toolbar buttons now use stronger green highlighting; inactive toolbar buttons are visually muted.
- Large-map state is saved locally on the device.
- Updated `drive-mode.js` cache version to Rev `1.8.11`.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.10 - 2026-06-30

- Made the selected dashboard mode explicit.
- Added a `Current View` label above the Simple/Detailed buttons.
- Active dashboard button now includes a checkmark and `Active` text.
- Active dashboard button now uses stronger green highlighting; inactive dashboard button is visually muted.
- Updated `dashboard-mode.js` cache version to Rev `1.8.10`.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.9 - 2026-06-30

- Added `dashboard-mode.js` with Simple Dashboard and Detailed Dashboard profiles.
- Simple Dashboard shows only selected widgets; Detailed Dashboard restores the full app view.
- Added a `Customize Simple` panel with saved per-device widget checkboxes.
- Default Simple Dashboard widgets are Speed Summary, Time Summary, Remaining Distance, ETA, Adjusted ETA, GPS Signal, and Live Map.
- Optional Simple Dashboard widgets include Compass Heading, Trip Progress, Raw GPS Data, No-API Trip Tools, Trip Log, Device History, PHP Stored Trips, and Changelog.
- Dashboard mode and widget choices are stored locally on the device.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.8 - 2026-06-30

- Added `drive-mode.js` with a sticky bottom driving bar.
- Sticky bar mirrors Speed, Remaining Distance, ETA, Adjusted ETA, and GPS Signal so key data stays visible while scrolling.
- Added a Drive Mode toggle that hides low-priority sections such as changelog/history/tool sections while preserving live trip cards.
- Added map control buttons for Recenter Map, Map Size, and Clear Trail.
- Updated `live-map.js` to expose safe map controls for recentering, clearing breadcrumb trail, follow mode, refresh interval, and map resize.
- Updated `map-loader.js` to load the Rev `1.8.8` live map helper.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.7 - 2026-06-30

- Replaced the manual route correction multiplier with a minute-based ETA offset.
- Added an `Adjusted ETA` card directly beside/near the current ETA area so the adjusted arrival time is visible without scrolling down to the no-API tools section.
- Added quick offset buttons for `-5`, `-1`, and `+1`, and `+5` minutes.
- Preserved the original app ETA and shows the adjusted ETA separately.
- Kept the ETA drift, GPS reconnect, GPS jump filter, pause count, and GPX/KML export features.
- No maps/directions API key is required.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.6 - 2026-06-30

- Added `no-api-enhancements.js` for additional features that do not require a maps/directions API key.
- Added background resume recovery using page focus, visibility, and page show events to reconnect GPS without resetting the trip.
- Added a `Reconnect GPS` button.
- Added GPS jump filtering to reject likely bad GPS spikes while preserving trip stats.
- Added a manual route correction factor that can adjust ETA calculations without a routing provider.
- Added an ETA drift indicator comparing the current ETA against the first valid ETA in the current trip.
- Added pause counting, rejected-jump count, and last GPS step display.
- Added GPX and KML trip export from logged GPS points.
- Updated the on-page future-feature note to list these items as already included.
- Preserved existing runtime history data; no history reset or migration is required.

## Rev 1.8.5 - 2026-06-30

- Added `map-loader.js` and `live-map.js` for a visual live location map.
- Added a current-location marker, GPS accuracy circle, and breadcrumb trail from browser GPS coordinates.
- The map visually refreshes about every 10 seconds while GPS updates continue as provided by the browser.
- Uses Leaflet with OpenStreetMap map tiles; no directions/routing API key is required.
- Does not add road-route distance, turn-by-turn routing, or traffic-aware ETA.
- Preserved existing runtime history data; no history reset or migration is required.

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
- Added current speed, GPS accuracy, ETA duration, estimated arrival time display, and last location display.
- Added support for miles and kilometers.
