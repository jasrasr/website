# MPG Fuel Log Tracker Roadmap

This roadmap tracks planned features and design work for the `/mpg` project. Completed items remain checked so the file also shows how the project has evolved.

## Current priorities

### Fuel entry and MPG integrity

- [x] Add a **Fill Type** radio selector to every fuel-entry path; default to **Full fill-up** with **Partial fill-up** as an explicit alternative.
- [x] Store `fill_type` in each JSON fuel-log entry.
- [x] Keep partial fill-ups in fuel history, cost totals, gallons totals, and location history.
- [x] Do **not** calculate a standalone MPG value for a partial fill-up.
- [x] Calculate the next full fill across the complete full-to-full interval, including gallons from intervening partial fills.
- [x] Preserve per-entry odometer delta plus `mpg_miles` and `mpg_gallons` for the completed MPG interval.
- [x] Keep partial fills visible on the MPG chart without creating a false MPG value.
- [x] Show partial-fill / pending-MPG wording in chart tooltips.
- [x] Calculate a per-vehicle historical **median gallons per full fill** when at least five usable full fills exist.
- [x] Use historical fill volume only as a prompt signal, never as automatic classification.
- [x] Prompt when a fill is unusually small while leaving the final Full/Partial choice to the user.

### Notes and context

- [x] Add an optional 500-character comment field to each fuel entry.
- [x] Store comments without affecting fuel calculations.

### Fuel station brand

- [x] Add an optional fuel-station brand selector.
- [x] Keep station brands in `stations.json` rather than hard-coding them.
- [x] Add an **Other** option that learns newly entered brands for future dropdowns.
- [x] De-duplicate station brands case-insensitively.
- [x] Keep station brand and physical station location as separate concepts.

### Station locations

- [x] Add reusable station-location profiles.
- [x] Support station brand/name, city, street/address, intersection, latitude, longitude, and optional nickname in the profile schema.
- [x] Allow a saved station to be selected when entering a fill later from another location.
- [ ] Show recently used stations near the top of the selector.
- [ ] Make saved-station selection searchable as the list grows.
- [ ] Add a station-management page for editing, merging, or manually creating profiles.

### GPS-assisted station selection

- [x] Add optional browser GPS capture during manual and photo-reviewed fuel entry.
- [x] Store GPS as supporting metadata rather than silently deciding the station.
- [x] Find nearby fuel stations and present likely matches for user confirmation.
- [x] Require explicit selection when several stations are nearby.
- [x] Save a confirmed nearby station as a reusable profile.
- [x] Allow saved-station selection when GPS is unavailable or the entry is added later.
- [x] Record location source such as `gps`, `gps_confirmed`, `photo_exif`, or `saved_station`.

### Photo-assisted station identification

- [x] Support up to 4 photos per fuel stop.
- [x] Allow the fourth photo to be station signage, canopy, or logo.
- [x] Let image analysis suggest the station brand.
- [x] Extract JPEG EXIF GPS when available.
- [x] Carry EXIF GPS through the photo review/save flow and use it for nearby-station suggestions.
- [ ] Explicitly compare photo-derived brand with nearby-station brand and surface a mismatch warning.
- [x] Require the user to confirm the exact station before a nearby candidate becomes authoritative.
- [x] Fall back to live GPS or manual/saved station selection when EXIF is unavailable.

## Analytics and visualization

- [x] Add an interactive map showing fill-up locations.
- [x] Make map pins clickable with date, station, gallons, price, total cost, fill type, MPG when applicable, and comments.
- [ ] Add map filtering by date range, station brand, and station location.
- [x] Compare average and median price per gallon by station brand.
- [ ] Compare price per gallon by specific station profile.
- [ ] Compare price by city/area.
- [x] Show fill counts and total spend by station brand.
- [ ] Consider trip/context tags such as work, vacation, towing, or road trip.

## Data quality and confidence

- [ ] Add a formal station-identification confidence indicator.
- [x] Never let AI, GPS, OCR, EXIF, or heuristics silently overwrite explicit user input.
- [x] Preserve backward compatibility with JSON entries that predate `fill_type`.
- [x] Validate contradictory price/gallons/total values while allowing legitimate partial fills.
- [x] Rebuild all dependent full-to-full MPG values after edit, delete, or raw-JSON changes.
- [x] Export new fill, station, location, comment, and MPG-span fields to CSV.

## Documentation and project hygiene

- [x] Add `ROADMAP.md`.
- [x] Add `CHANGELOG.md`.
- [x] Add `CONTRIBUTING.md`.
- [x] Add `docs/ARCHITECTURE.md`.
- [x] Keep README feature documentation synchronized with implemented functionality.
- [x] Move implemented roadmap items to completed status and document them in the changelog.

## Later / exploratory

- [ ] Consider per-vehicle tank-capacity metadata as another anomaly signal, not as the sole full/partial rule.
- [ ] Consider richer robust statistics once more clean fill history accumulates.
- [ ] Consider import/export support for station profiles.

---

## Product principles

1. **Assist, never assume.** Automation should reduce typing without silently making a consequential choice for the user.
2. **Accuracy over automation.** A missing MPG value is better than a mathematically precise but physically misleading value.
3. **Keep every real fuel event.** Partial fills are valid data and should not disappear because MPG cannot yet be calculated.
4. **Human confirmation wins.** GPS, OCR, EXIF, AI, and heuristics provide suggestions; explicit user confirmation is authoritative.
5. **Fast entry matters.** Advanced features should not turn a fuel stop into paperwork.
