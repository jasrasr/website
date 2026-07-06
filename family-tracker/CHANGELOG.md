# Family GPS Tracker Changelog

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
