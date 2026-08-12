# MPG Fuel Log Tracker

This is a lightweight PHP-based fuel tracking web application designed for quick mobile entry, per-vehicle JSON logging, fuel-cost tracking, and MPG analysis.

## Features

- ✅ Per-vehicle fuel logs stored in JSON format
- ✅ Odometer-based mileage tracking
- ✅ Full-to-full MPG calculation
- ✅ Partial fill-up tracking without misleading standalone MPG
- ✅ Manual fuel entry
- ✅ Multi-photo AI-assisted fuel entry
- ✅ Optional comments
- ✅ Self-learning fuel-station brand list
- ✅ Optional browser GPS capture
- ✅ Up to 4 fuel-stop photos, including an optional station sign/logo photo
- ✅ Station-brand suggestion from uploaded signage
- ✅ Partial-fill markers on the MPG chart
- ✅ Entry editing and deletion
- ✅ Export to CSV
- ✅ Admin dashboard with plate summaries
- ✅ Device/IP trust features
- ✅ All timestamps recorded in Eastern Time (ET)
- ✅ Reusable navigation and mobile-friendly pages

## Full and partial fills

Every entry can be marked as either **Full** or **Partial**. Full is the default.

A partial fill remains a real fuel event and is retained for gallons, cost, station, location, and history. It does not receive a standalone MPG value.

When the next full fill occurs, MPG is calculated over the complete full-to-full interval:

```text
MPG = miles since previous full fill / all gallons added since previous full fill
```

This means one or more partial fills can occur between full fills without corrupting the MPG calculation.

Legacy JSON entries that do not contain `fill_type` are treated as full fills for backward compatibility.

## Station and location data

Fuel entries may optionally include a station brand and GPS coordinates.

Station brands are stored in `stations.json`. If **Other / Add new** is selected and a new brand is entered, the application adds it to the saved brand list for future use. Duplicate brand detection is case-insensitive.

GPS capture is optional. Coordinates are supporting metadata only; GPS does not automatically decide which physical station was used.

The roadmap includes reusable physical station profiles, nearby-station suggestions, intersection/city information, and an interactive fill-up map.

## Photo workflow

The photo workflow accepts up to four images per entry. Images can include:

1. odometer
2. price per gallon
3. pump total and gallons
4. optional station sign, canopy, or logo

Image analysis can suggest the station brand when signage is clear. JPEG EXIF GPS extraction support also exists in `process_photos.php`; end-to-end automatic use of that EXIF location in the review/save workflow remains on the roadmap.

## Project documentation

- [`ROADMAP.md`](ROADMAP.md) — planned features, active priorities, and completion checkboxes
- [`CHANGELOG.md`](CHANGELOG.md) — released and unreleased changes
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — contribution workflow, compatibility rules, testing expectations, and AI-assisted development guidance
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — design principles, data-model decisions, fuel-calculation rules, location strategy, and guardrails

## Next major roadmap items

- historical median-based fill-volume prompts for likely partial fills
- reusable station-location profiles with city and street/intersection
- nearby station suggestions based on GPS with explicit user confirmation
- complete photo-EXIF GPS wiring into the save workflow
- GPS + station-logo cross-checking
- interactive fill-up map
- location and station price analytics

See [`ROADMAP.md`](ROADMAP.md) for the detailed checklist.

## Core files

| File | Description |
|---|---|
| `index.php` | MPG application landing page |
| `fuel_form.php` | Manual fuel-entry form |
| `scan_photos.php` | Multi-photo AI-assisted entry workflow |
| `process_photos.php` | Extracts fuel values, station branding, and available JPEG EXIF GPS |
| `auto_save.php` | Shared JSON save endpoint and full/partial MPG calculation logic |
| `stations.json` | Learned station-brand data and future station-location profiles |
| `manage_entries.php` | Edit/delete fuel entries |
| `view_latest.php` | Shows the latest log entry for a plate |
| `view_chart.php` | Displays MPG trend and partial-fill events using Chart.js |
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
3. Ensure `stations.json` is writable if you want new station brands to be learned automatically.
4. Configure required local secrets such as the OpenAI API key outside source control.
5. Use PHP 8.2 or newer where possible.
6. Visit the MPG application in a browser and create the first vehicle entry.

## Data and privacy

Fuel logs, device identifiers, API keys, and precise location history may contain private information. Keep production data and secrets out of public source control unless intentionally sanitized.

## License

MIT License

---

Author: Jason Lamb — jasonlamb.me
