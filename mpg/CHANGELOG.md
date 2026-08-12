# Changelog

All notable changes to the MPG Fuel Log Tracker should be documented in this file.

The project uses a lightweight semantic-versioning approach:

- **Major**: incompatible data-model or workflow changes
- **Minor**: new backward-compatible features
- **Patch**: fixes, validation changes, documentation, and small UI improvements

## [Unreleased]

### Planned

- Search/recent ordering for saved station profiles
- Explicit duplicate-profile merge tooling
- Explicit photo-brand vs. nearby-station mismatch warning
- Formal station-identification confidence indicator

## [2.4.0] - 2026-08-12

### Added

- Full vs. partial fill-up selection on manual and photo-assisted entry flows
- Full-to-full MPG calculation that rolls intervening partial-fill gallons into the next full fill
- `mpg_miles` and `mpg_gallons` fields to expose the MPG calculation span
- Optional 500-character comments
- Self-learning station-brand list backed by `stations.json`
- Reusable station-location profiles with brand/name, city, street/intersection, nickname, and coordinates
- Saved-station selection for later/off-site data entry
- GPS-powered nearby fuel-station lookup with explicit station confirmation
- Confirmed nearby stations automatically saved as reusable profiles
- Station profile admin editor (`manage_stations.php`)
- Browser GPS location capture
- JPEG EXIF GPS extraction and end-to-end use in the photo review flow
- Optional station sign/logo recognition in the fourth scan photo
- Per-vehicle median fill-volume baseline and low-volume partial-fill prompt
- Interactive Leaflet/OpenStreetMap fuel-stop map
- Map filters for date range, station brand, and fill type
- Station-brand, specific-station, and city/area price analytics
- Expanded CSV export with fill type, MPG span, station, location, source, and comments

### Changed

- Legacy JSON entries without `fill_type` are treated as full fills for backward compatibility
- Partial fills remain visible in history and charts but do not generate standalone MPG
- Entry edit/delete/raw-JSON operations rebuild all dependent full-to-full MPG calculations
- Stats and menu summary use completed full-to-full intervals
- Cost-per-mile excludes baseline and trailing incomplete fuel intervals

### Fixed

- Corrected `.009` handling when price per gallon is calculated from total/gallons; the adjustment now applies only to a user-entered two-decimal pump price
- Added contradictory fuel-value validation when price, gallons, and total are all supplied

## [2.3] - 2026-04-21

### Added

- Mobile multi-photo fuel scanning workflow
- AI-assisted extraction of odometer, price per gallon, pump total, and gallons
- Scan-to-entry prefill support
- Entry editing and deletion
- Device trust/management features
- Additional stats and entry-management pages

### Fixed

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
