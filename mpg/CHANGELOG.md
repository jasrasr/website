# Changelog

All notable changes to the MPG Fuel Log Tracker should be documented in this file.

The project uses a lightweight semantic-versioning approach:

- **Major**: incompatible data-model or workflow changes
- **Minor**: new backward-compatible features
- **Patch**: fixes, validation changes, documentation, and small UI improvements

## [Unreleased]

### Added

- Full vs. partial fill-up selection on manual and photo-assisted entry flows
- Full-to-full MPG calculation that rolls intervening partial-fill gallons into the next full fill
- `mpg_miles` and `mpg_gallons` fields to make the MPG calculation span explicit
- Optional 500-character comments on fuel entries
- Self-learning station-brand dropdown backed by `stations.json`
- Case-insensitive station-brand de-duplication
- Optional browser GPS capture on manual and photo-reviewed entries
- Optional location-source metadata
- Photo workflow increased from 3 to 4 images
- Station sign/logo recognition as an optional fourth photo type
- JPEG EXIF GPS extraction support in `process_photos.php` (UI/save wiring remains on the roadmap)
- Partial-fill chart markers and full-to-full calculation details in chart tooltips
- `ROADMAP.md`
- `CHANGELOG.md`
- `CONTRIBUTING.md`
- `docs/ARCHITECTURE.md`

### Changed

- Legacy JSON entries without `fill_type` are treated as full fills for backward-compatible calculations
- The first full fill remains a baseline event rather than becoming a false `0 MPG` measurement
- Partial fills remain in history instead of being dropped merely because they cannot produce standalone MPG

### Planned

- Historical median-based fill-volume anomaly prompts after historical JSON data is supplied
- Reusable station-location profiles with city, street/intersection, and coordinates
- Nearby-station suggestions from GPS with explicit user confirmation
- End-to-end automatic use of photo EXIF GPS in the review/save flow
- GPS + station-logo cross-checking
- Interactive fill-up map
- Station/location price analytics

## [2.3] - 2026-04-21

### Added

- Mobile multi-photo fuel scanning workflow
- AI-assisted extraction of odometer, price per gallon, pump total, and gallons
- Scan-to-entry prefill support
- Entry editing and deletion
- Automatic recalculation after entry changes
- Device trust/management features
- Additional stats and entry-management pages

### Fixed

- Corrected the `.009` fuel-price adjustment so it applies only when appropriate
- Improved fuel-entry calculation behavior
- Updated chart filtering so invalid/zero MPG entries are not graphed as real MPG values

## [1.0] - 2025-10-27

### Added

- Initial MPG Fuel Consumption Tracker documentation
- Per-vehicle JSON fuel logs
- Odometer-based mileage tracking
- MPG calculation
- CSV export
- Admin views
- MPG trend chart

---

When a roadmap feature ships, move it into a dated release section here and check it off in `ROADMAP.md`.
