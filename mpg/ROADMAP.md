# MPG Fuel Log Tracker Roadmap

This roadmap tracks planned features and design work for the `/mpg` project. Completed items remain checked so the file also shows how the project has evolved.

## Current priorities

### Fuel entry and MPG integrity

- [x] Add a **Fill Type** radio selector to every fuel-entry path.
  - Default: **Full fill-up**.
  - Alternate: **Partial fill-up**.
  - The user can always override the default.
- [x] Store `fill_type` in each JSON fuel-log entry.
- [x] Keep partial fill-ups in fuel history, cost totals, gallons totals, and location history.
- [x] Do **not** calculate a standalone MPG value for a partial fill-up.
- [x] When the next full fill-up occurs, calculate MPG across the complete full-to-full interval, including gallons from all intervening partial fill-ups.
- [x] Preserve the per-entry odometer delta while also storing the full-to-full MPG calculation span.
- [x] Update chart behavior so partial fill-ups remain visible as fuel events without displaying a misleading MPG value.
- [x] Add chart/tool-tip wording such as `Partial fill — MPG pending next full fill-up`.
- [ ] After historical JSON data is supplied, calculate a typical gallons-per-fill baseline using the **median** and distribution of prior fills.
- [ ] Use historical fill volume only as a prompt signal, never as an automatic classification.
- [ ] If a fill is unusually small compared with the user's normal fill volume, ask whether it was partial while keeping **Full** as the default selection.

### Notes and context

- [x] Add an optional text comment field to each fuel entry.
- [x] Store comments in the JSON log without requiring them for calculations.
- [x] Keep the field short enough for quick mobile entry while allowing useful context such as towing, winter fuel, road trip, unusual driving, or pump issues.

### Fuel station brand

- [x] Add an optional fuel-station brand selector.
- [x] Keep station brands in a separate data file rather than hard-coding them into the form.
- [x] Include an **Other** option that allows a new brand to be entered.
- [x] When a new brand is entered, save it to the station-brand list so it appears in future dropdowns.
- [x] De-duplicate station brands case-insensitively so `BP`, `bp`, and `Bp` do not become separate values.
- [x] Keep brand and physical station location as separate concepts in the data model.

### Station locations

- [ ] Add reusable station-location profiles.
- [ ] Store, when available:
  - station brand/name
  - city
  - street/address or nearby intersection
  - latitude
  - longitude
  - optional user-friendly nickname
- [ ] Allow a saved station location to be selected when entering a fill-up later from somewhere else.
- [ ] Show recently used stations near the top of the selector.
- [ ] Make station/location selection searchable as the list grows.

### GPS-assisted station selection

- [x] Add optional browser GPS capture during manual and photo-reviewed fuel entry.
- [x] Store GPS as supporting metadata rather than automatically deciding which station was used.
- [ ] Find nearby fuel stations and present the closest likely matches for user confirmation.
- [ ] If several stations are near the same intersection, require the user to select the correct one.
- [ ] Save a confirmed station as a reusable station-location profile.
- [ ] Allow manual saved-station selection when GPS is unavailable, denied, inaccurate, or the entry is being added later.
- [x] Record the source of captured location data when available, such as `gps`.

### Photo-assisted station identification

- [x] Increase the photo workflow from up to 3 photos to up to 4 optional photos.
- [x] Allow the fourth photo to be a station sign, canopy, or logo.
- [x] Let image analysis suggest the station brand from signage.
- [ ] Cross-check a photo-derived brand with nearby GPS station candidates when both are available.
- [ ] Ask the user to confirm when image and GPS signals disagree.
- [ ] Complete end-to-end photo EXIF GPS use in the review/save workflow. (`process_photos.php` can extract JPEG EXIF GPS; the UI still needs to consume it automatically.)
- [x] Fall back to live browser GPS or manual/no-location entry when EXIF location is unavailable.

## Analytics and visualization

- [ ] Add an interactive map showing fill-up locations.
- [ ] Make map pins clickable with date, station, gallons, price per gallon, total cost, fill type, MPG when applicable, and comments.
- [ ] Support filtering the map by vehicle, date range, station brand, and station location.
- [ ] Compare average and median price per gallon by station brand.
- [ ] Compare price per gallon by specific station/location.
- [ ] Compare price by city/area.
- [ ] Show fill-up counts and total spend by station.
- [ ] Consider trip or context tags such as work, vacation, towing, or road trip.

## Data quality and confidence

- [ ] Add a station-identification confidence/source indicator.
  - High confidence example: GPS candidate and photo/receipt brand agree, then user confirms.
  - Medium confidence example: GPS candidate confirmed by user.
  - Manual example: user directly selects or enters the station.
- [x] Never let AI, GPS, OCR, or heuristics silently overwrite explicit user input in the new entry flow.
- [x] Preserve backward compatibility with existing JSON log entries that do not yet contain `fill_type`; legacy entries are treated as full fills when calculating future intervals.
- [ ] Add stronger validation for contradictory fuel values while allowing legitimate partial fill-ups.

## Documentation and project hygiene

- [x] Add `ROADMAP.md` with checkboxes for planned and completed work.
- [x] Add `CHANGELOG.md` to document released changes.
- [x] Add `CONTRIBUTING.md` so contributors and AI-assisted sessions follow the same workflow.
- [x] Add `docs/ARCHITECTURE.md` to capture design decisions, principles, and explicit non-goals.
- [x] Keep README feature documentation synchronized with implemented functionality.
- [x] Move implemented roadmap items to completed status and document them in the changelog.

## Later / exploratory

- [ ] Consider per-vehicle tank-capacity metadata as another anomaly signal, not as the sole full/partial decision rule.
- [ ] Consider learning each vehicle's normal fill-volume distribution as enough clean history accumulates.
- [ ] Consider a station-management page for merging duplicates, editing locations, and correcting old entries.
- [ ] Consider import/export support for station profiles in addition to fuel logs.

---

## Product principles

1. **Assist, never assume.** Automation should reduce typing without silently making a consequential choice for the user.
2. **Accuracy over automation.** A missing MPG value is better than a mathematically precise but physically misleading value.
3. **Keep every real fuel event.** Partial fills are valid data and should not disappear merely because MPG cannot yet be calculated.
4. **Human confirmation wins.** GPS, OCR, EXIF, and heuristics provide suggestions; explicit user confirmation is authoritative.
5. **Fast entry matters.** Advanced features should not turn a fuel stop into paperwork.
