# MPG Fuel Log Tracker Roadmap

This roadmap tracks planned features and design work for the `/mpg` project. Completed items remain checked so the file also shows how the project has evolved.

## Current priorities

### Fuel entry and MPG integrity

- [ ] Add a **Fill Type** radio selector to every fuel-entry path.
  - Default: **Full fill-up**.
  - Alternate: **Partial fill-up**.
  - The user must always be able to override the default.
- [ ] Store `fill_type` in each JSON fuel-log entry.
- [ ] Keep partial fill-ups in fuel history, cost totals, gallons totals, and location history.
- [ ] Do **not** calculate a standalone MPG value for a partial fill-up.
- [ ] When the next full fill-up occurs, calculate MPG across the complete full-to-full interval, including gallons from all intervening partial fill-ups.
- [ ] Preserve the per-entry odometer delta while also storing or exposing the full-to-full MPG calculation span.
- [ ] Update chart behavior so partial fill-ups remain visible as fuel events without displaying a misleading MPG value.
- [ ] Add chart/tool-tip wording such as `Partial fill — MPG pending next full fill-up`.
- [ ] After historical JSON data is supplied, calculate a typical gallons-per-fill baseline using the **median** and distribution of prior fills.
- [ ] Use historical fill volume only as a prompt signal, never as an automatic classification.
- [ ] If a fill is unusually small compared with the user's normal fill volume, ask whether it was partial while keeping **Full** as the default selection.

### Notes and context

- [ ] Add an optional text comment field to each fuel entry.
- [ ] Store comments in the JSON log without requiring them for calculations.
- [ ] Keep the field short enough for quick mobile entry while allowing useful context such as towing, winter fuel, road trip, unusual driving, or pump issues.

### Fuel station brand

- [ ] Add an optional fuel-station brand selector.
- [ ] Keep station brands in a separate data file rather than hard-coding them into the form.
- [ ] Include an **Other** option that allows a new brand to be entered.
- [ ] When a new brand is entered, save it to the station-brand list so it appears in future dropdowns.
- [ ] De-duplicate station brands case-insensitively so `BP`, `bp`, and `Bp` do not become separate values.
- [ ] Keep brand and physical station location as separate concepts.

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

- [ ] Add optional browser GPS capture during fuel entry.
- [ ] Use GPS as a **suggestion source**, not as unquestioned truth.
- [ ] Find nearby fuel stations and present the closest likely matches for user confirmation.
- [ ] If several stations are near the same intersection, require the user to select the correct one.
- [ ] Save a confirmed station as a reusable station-location profile.
- [ ] Allow manual station selection when GPS is unavailable, denied, inaccurate, or the entry is being added later.
- [ ] Record the source of the location when useful, such as `gps`, `photo_exif`, `saved_station`, or `manual`.

### Photo-assisted station identification

- [ ] Increase the photo workflow from up to 3 photos to up to 4 optional photos.
- [ ] Allow the fourth photo to be a station sign, canopy, or logo.
- [ ] Let image analysis suggest the station brand from signage.
- [ ] Cross-check a photo-derived brand with nearby GPS station candidates when both are available.
- [ ] Ask the user to confirm when image and GPS signals disagree.
- [ ] Attempt to read GPS EXIF metadata from uploaded photos before requesting live browser location.
- [ ] Fall back to live GPS or manual station selection when EXIF location is unavailable.

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
- [ ] Never let AI, GPS, OCR, or heuristics silently overwrite explicit user input.
- [ ] Preserve backward compatibility with existing JSON log entries that do not yet contain the new fields.
- [ ] Add validation for contradictory fuel values while allowing legitimate partial fill-ups.

## Documentation and project hygiene

- [x] Add `ROADMAP.md` with checkboxes for planned and completed work.
- [x] Add `CHANGELOG.md` to document released changes.
- [x] Add `CONTRIBUTING.md` so contributors and AI-assisted sessions follow the same workflow.
- [x] Add `docs/ARCHITECTURE.md` to capture design decisions, principles, and explicit non-goals.
- [ ] Keep README feature documentation synchronized with implemented functionality.
- [ ] Move roadmap items to completed status and reference the release/version when implemented.

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
