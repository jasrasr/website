# Changelog

All notable changes to the MPG Fuel Log Tracker should be documented in this file.

The project uses a lightweight semantic-versioning approach:

- **Major**: incompatible data-model or workflow changes
- **Minor**: new backward-compatible features
- **Patch**: fixes, validation changes, documentation, and small UI improvements

## [Unreleased]

### Planned

- Full vs. partial fill-up tracking
- Full-to-full MPG calculation across intervening partial fills
- Optional comments
- Self-learning station-brand list
- Reusable station-location profiles
- Optional GPS-assisted station selection
- Photo EXIF location support
- Optional station-logo photo recognition
- Fill-up map and station/location price analytics

### Documentation

- Added `ROADMAP.md`
- Added `CHANGELOG.md`
- Added `CONTRIBUTING.md`
- Added `docs/ARCHITECTURE.md`

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
