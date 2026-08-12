# MPG Fuel Log Tracker

This is a lightweight PHP-based fuel tracking web application designed for quick mobile entry, per-vehicle JSON logging, fuel-cost tracking, and MPG analysis.

## Features

- ✅ Per-vehicle fuel logs stored in JSON format
- ✅ Odometer-based mileage tracking
- ✅ MPG calculation when a valid prior measurement exists
- ✅ Manual fuel entry
- ✅ Multi-photo AI-assisted fuel entry
- ✅ Entry editing and deletion
- ✅ Export to CSV
- ✅ Admin dashboard with plate summaries
- ✅ MPG trend chart using Chart.js
- ✅ Device/IP trust features
- ✅ All timestamps recorded in Eastern Time (ET)
- ✅ Reusable navigation and mobile-friendly pages

## Project documentation

- [`ROADMAP.md`](ROADMAP.md) — planned features, active priorities, and completion checkboxes
- [`CHANGELOG.md`](CHANGELOG.md) — released and unreleased changes
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — contribution workflow, compatibility rules, testing expectations, and AI-assisted development guidance
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — design principles, data-model decisions, fuel-calculation rules, location strategy, and guardrails

## Current roadmap themes

The next major areas of work include:

- explicit full vs. partial fill-up tracking
- correct full-to-full MPG calculation across partial fills
- optional comments
- self-learning fuel-station brands
- reusable station-location profiles
- optional GPS and photo-EXIF location assistance
- station-logo/photo identification
- fill-up map visualization and location-based price analytics

See [`ROADMAP.md`](ROADMAP.md) for the detailed checklist.

## Core files

| File | Description |
|---|---|
| `index.php` | MPG application landing page |
| `fuel_form.php` | Manual fuel-entry form |
| `scan_photos.php` | Multi-photo AI-assisted entry workflow |
| `process_photos.php` | Extracts fuel values from uploaded photos |
| `auto_save.php` | Shared JSON save/calculation endpoint used by current entry flows |
| `manage_entries.php` | Edit/delete fuel entries |
| `view_latest.php` | Shows the latest log entry for a plate |
| `view_chart.php` | Displays MPG trend chart using Chart.js |
| `view_stats.php` | Fuel statistics view |
| `export_csv.php` | Exports logs as CSV per plate |
| `admin.php` | Admin dashboard |
| `devices_admin.php` | Device trust/management page |
| `menu.php` | Shared navigation |
| `ROADMAP.md` | Planned feature checklist |
| `CHANGELOG.md` | Project change history |
| `CONTRIBUTING.md` | Contribution and development workflow |
| `docs/ARCHITECTURE.md` | Architecture and design decisions |

## Setup

1. Upload the `/mpg/` files to a PHP-capable web host.
2. Ensure the `logs/` directory exists and is writable by PHP.
3. Configure any required local secrets such as the OpenAI API key outside source control.
4. Use PHP 8.2 or newer where possible.
5. Visit the MPG application in a browser and create the first vehicle entry.

## Data and privacy

Fuel logs, device identifiers, API keys, and precise location history may contain private information. Keep production data and secrets out of public source control unless intentionally sanitized.

## License

MIT License

---

Author: Jason Lamb — jasonlamb.me
