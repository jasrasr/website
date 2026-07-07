# Family GPS Tracker Changelog

## Rev 0.2.0 - 2026-07-06

- Added shared trail-history endpoint via `trails.php`.
- Added history window filtering: last hour, 4 hours, 12 hours, or 24 hours.
- Added separate History Map that draws breadcrumb routes from stored trail points.
- Added member filtering for map history.
- Added Show Everyone control to reset from individual focus back to all-family history map.
- Added member detail panel with latest status, update age, accuracy, speed, heading, and history point count.
- Bumped app revision to 0.2.0.

## Rev 0.1.0 - 2026-07-06

- Initial PHP + JSON scaffold.
- Added owner-created family groups.
- Added invite-code family joining.
- Added PHP session login/logout.
- Added consent gate during account creation.
- Added browser GPS sharing using `navigator.geolocation.watchPosition()`.
- Added latest-location JSON storage under `data/locations/`.
- Added short breadcrumb trail storage under `data/trails/`.
- Added family map using Leaflet and OpenStreetMap tiles.
- Added own-location deletion.
- Added owner invite-code regeneration.
- Added Apache `.htaccess` protection for JSON data.
