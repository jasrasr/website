# MPG Fuel Log Tracker

This is a lightweight PHP-based fuel tracking web application designed for quick mobile entry, per-vehicle JSON logging, fuel-cost tracking, and MPG analysis.

## Features

- ✅ Per-vehicle fuel logs stored in JSON format
- ✅ Odometer-based mileage tracking
- ✅ Full-to-full MPG calculation
- ✅ Partial fill-up tracking without misleading standalone MPG
- ✅ Historical median-based prompt for unusually small fills
- ✅ Manual fuel entry
- ✅ Multi-photo AI-assisted fuel entry
- ✅ Optional comments
- ✅ Self-learning fuel-station brands
- ✅ Reusable physical station profiles
- ✅ Saved-station selection for later/off-site entry
- ✅ Optional browser GPS capture
- ✅ Nearby-station lookup with explicit user confirmation
- ✅ JPEG photo EXIF GPS support in the scan workflow
- ✅ Up to 4 fuel-stop photos, including station signage/logo
- ✅ Interactive fuel-stop map
- ✅ Station-brand price/spend analytics
- ✅ Partial-fill markers on the MPG chart
- ✅ Entry editing/deletion with full dependency recalculation
- ✅ Expanded CSV export
- ✅ Admin dashboard, station management, and device trust features
- ✅ All timestamps recorded in Eastern Time (ET)

## Full and partial fills

Every entry can be marked **Full** or **Partial**. Full is the default.

A partial fill remains a real fuel event and is retained for gallons, cost, station, location, and history. It does not receive a standalone MPG value. When the next full fill occurs:

```text
MPG = miles since previous full fill / all gallons added since previous full fill
```

One or more partial fills can therefore occur between full fills without corrupting MPG. Legacy JSON entries that do not contain `fill_type` are treated as full fills for backward compatibility.

When at least five usable historical full-fill records exist, `fill_baseline.php` calculates the vehicle's median gallons per full fill. A fill below 45% of that median triggers a **question**, not an automatic classification.

## Station and location data

`stations.json` stores both learned station brands and reusable physical station profiles. Profiles can include:

- brand/name
- city
- street/address
- intersection
- nickname
- latitude/longitude
- source metadata

The entry forms can use GPS to request nearby fuel stations from OpenStreetMap/Overpass. The user confirms the exact station before it is saved as a reusable profile. Saved profiles can then be selected later without using GPS at all.

`manage_stations.php` provides an admin editor for saved station profiles.

## Photo workflow

The photo workflow accepts up to four images:

1. odometer
2. price per gallon
3. pump total and gallons
4. optional station sign, canopy, or logo

Image analysis can suggest the station brand. For JPEG images with GPS EXIF metadata, `process_photos.php` extracts the coordinates and `scan_photos.php` carries them into nearby-station confirmation automatically. If EXIF is missing, live GPS or a saved station can be used instead.

## Mapping and analytics

`view_map.php` uses Leaflet with OpenStreetMap tiles to plot fuel entries that have coordinates. Map popups include date, station, gallons, price, total cost, fill type, MPG when available, and comments.

`view_stats.php` provides partial-aware MPG summary data plus station-brand average/median price, gallons, fill counts, and spend.

## Data validation

When price, gallons, and total cost are all provided, the server verifies that the values agree within pump-rounding tolerance. The `.009` fuel-price convention is applied only to a user-entered two-decimal price; it is not added to a price calculated from total/gallons.

Editing, deleting, or replacing raw JSON through `manage_entries.php` rebuilds all dependent miles and full-to-full MPG values.

## Project documentation

- [`ROADMAP.md`](ROADMAP.md) — planned features, priorities, and checkboxes
- [`CHANGELOG.md`](CHANGELOG.md) — release history
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — contribution and AI-assisted development workflow
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — design decisions and guardrails

## Core files

| File | Description |
|---|---|
| `fuel_form.php` | Manual fuel entry with full/partial, station, GPS, and nearby-station support |
| `scan_photos.php` | AI/photo entry with EXIF GPS and station confirmation |
| `process_photos.php` | Extracts fuel values, station branding, and JPEG EXIF GPS |
| `auto_save.php` | Shared save endpoint, validation, and full-to-full MPG logic |
| `fill_baseline.php` | Robust median fill-volume prompt baseline |
| `station_api.php` | Saved station CRUD and nearby OpenStreetMap lookup |
| `stations.json` | Learned brands and reusable station profiles |
| `manage_stations.php` | Admin station-profile editor |
| `manage_entries.php` | Edit/delete/raw JSON with dependency recalculation |
| `view_latest.php` | Latest fuel entry |
| `view_chart.php` | MPG trend plus partial-fill events |
| `view_stats.php` | Fuel and station analytics |
| `view_map.php` | Interactive fuel-stop map |
| `export_csv.php` | Expanded CSV export |
| `admin.php` | Admin dashboard |
| `devices_admin.php` | Device trust/management |
| `menu.php` | Shared navigation |

## Setup

1. Upload the `/mpg/` files to a PHP-capable web host.
2. Ensure `logs/` is writable by PHP.
3. Ensure `stations.json` is writable if station learning/profile saving is enabled.
4. Configure required secrets such as the OpenAI API key outside source control.
5. Server-side outbound HTTPS must be allowed for nearby-station lookup through the Overpass API.
6. Use PHP 8.2 or newer where possible.

## Data and privacy

Fuel logs, device identifiers, API keys, and precise location history may contain private information. Keep production data and secrets out of public source control unless intentionally sanitized. GPS and EXIF location capture are optional and user-visible.

## License

MIT License

---

Author: Jason Lamb — jasonlamb.me
