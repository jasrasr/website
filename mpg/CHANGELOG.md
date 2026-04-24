# Changelog

All notable changes to the MPG Fuel Log Tracker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-28

### Added
- Initial release of MPG Fuel Log Tracker
- Per-vehicle fuel logs stored in JSON format
- MPG calculation (if prior odometer exists)
- Export to CSV functionality
- Admin dashboard with plate summaries and raw JSON links
- Trend chart (MPG over time) using Chart.js
- All timestamps recorded in Eastern Time (ET)
- Modern UI with reusable top-right menu
- Login/logout authentication system
- View latest log per vehicle
- View chart for MPG trends over time

### Features
- `index.php` - Entry form for fuel logging
- `fuel_form.php` - Modular HTML form used by index.php and save_log
- `save_log.php` - Processes form, saves logs, calculates MPG
- `export_csv.php` - Exports logs as CSV per plate
- `view_latest.php` - Shows most recent log for a plate
- `view_chart.php` - Displays MPG chart for plate using Chart.js
- `admin.php` - Admin dashboard listing all plates + stats
- `menu.php` - Top-right nav menu (icons + tooltips)
- `login.php` - Authentication page
- `logout.php` - Session termination

### Technical Details
- Requires PHP 8.2 or higher
- JSON-based data storage
- MIT License
