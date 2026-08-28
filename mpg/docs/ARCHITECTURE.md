# MPG Fuel Log Tracker Architecture

This document records the project's design principles and important architectural decisions so future maintainers — human or AI-assisted — do not have to reverse-engineer intent from the code.

## Core principles

### Assist, never assume

Automation may suggest values, but explicit user confirmation is authoritative. This applies to fill type, station identity, GPS-derived location, OCR results, photo recognition, and anomaly detection.

### Accuracy over automation

A missing MPG value is preferable to a mathematically valid but physically misleading one. Fuel calculations should represent the actual measurement interval.

### Preserve real fuel events

Partial fills are legitimate events. They affect fuel purchased, cost, gallons, location history, and later MPG calculations. They should remain visible rather than being discarded merely because they cannot produce a standalone MPG result.

### Fast entry matters

The application is often used during or immediately after refueling. Advanced features should reduce typing rather than create a long form.

### Backward compatibility matters

Historical JSON entries may lack newer fields. Readers should apply safe defaults and avoid forcing migrations unless a migration is explicitly requested.

## Fuel event model

Each JSON log record represents a **fuel event**, not necessarily an MPG measurement.

A fuel event may include:

- license plate
- date
- odometer
- miles since prior fuel event
- gallons
- price per gallon
- total cost
- fill type (`full` or `partial`)
- MPG, when a valid full-to-full interval is complete
- optional comment
- station brand
- station/location identifier or metadata
- latitude/longitude when available
- location source
- source (`manual` or `scan`)
- submission metadata

### Legacy fill-type behavior

Historical records without `fill_type` should default to `full` when read. This maintains compatibility with the original application behavior.

## Full vs. partial fill calculation

The standard MPG measurement interval is **full tank to full tank**.

### Full followed by full

If two consecutive relevant fuel events are both full fills:

`MPG = odometer difference / gallons added at the second full fill`

### Full followed by one or more partial fills, then full

Partial fills do not close the MPG measurement interval.

Example:

1. Full fill at odometer A
2. Partial fill: G1 gallons
3. Partial fill: G2 gallons
4. Full fill at odometer B: G3 gallons

The MPG reported at step 4 is:

`MPG = (B - A) / (G1 + G2 + G3)`

The individual partial entries remain in history, but their MPG field should be absent/null rather than a misleading per-segment calculation.

### First entry

The first fuel event cannot produce a valid full-to-full MPG because there is no previous full-tank baseline. It should remain visible without manufacturing a zero-MPG measurement.

## Chart behavior

Charts must distinguish **fuel events** from **MPG measurements**.

- Valid full-to-full MPG results are plotted as MPG points.
- Partial fills remain visible as event markers.
- Partial markers must not imply a numeric MPG value.
- Tooltips should explain that MPG is pending until the next full fill.
- The initial baseline entry should not appear as a false zero-MPG point.

## Fill-type anomaly assistance

The application may warn when a fill volume looks unusually small, but it should never silently change `full` to `partial`.

Preferred signal order:

1. User's explicit full/partial choice
2. Historical fill-volume distribution
3. Vehicle tank-capacity metadata, if configured

The historical **median** is preferred over the arithmetic mean as a baseline because occasional top-offs and unusual fills can distort the mean.

Thresholds should be derived from actual history once enough clean data exists. Until then, the radio selector simply defaults to `full`.

## Station data model

Station **brand** and physical **location** are separate entities.

A brand might be:

- Speedway
- Marathon
- Sunoco
- BP

A physical station profile may include:

- stable internal identifier
- brand
- city
- street address and/or intersection
- latitude
- longitude
- optional nickname
- created/last-used timestamps

This separation allows multiple locations belonging to the same brand to be compared independently.

## Self-learning station brands

Station brands should live in a separate data file rather than in hard-coded HTML.

When the user selects `Other` and enters a new brand:

1. Normalize for duplicate comparison.
2. Preserve a clean display value.
3. Save the brand to the station data store.
4. Offer it in future entry dropdowns.

Brand matching should be case-insensitive so `BP`, `bp`, and `Bp` resolve to the same brand.

## Location acquisition hierarchy

Location is optional. When available, use the least-friction trustworthy source.

Suggested hierarchy:

1. Photo EXIF GPS, when present and plausible
2. Live browser GPS
3. Previously saved station profile
4. Manual station/location selection

A later entry made away from the station must still be able to select the actual station from saved profiles.

## GPS-assisted station identification

GPS should identify a search area, not assert the exact station.

Expected workflow:

1. Obtain coordinates.
2. Find nearby fuel stations.
3. Present the closest plausible candidates.
4. Let the user select the correct station.
5. Save the confirmed station profile for reuse.

This is important at intersections where multiple fuel stations may be physically close enough that raw GPS cannot distinguish them reliably.

## Photo-assisted identification

The photo workflow may eventually accept up to four photos:

1. odometer
2. price-per-gallon sign/pump
3. pump totals/gallons
4. optional station sign/logo

Station-logo recognition is an additional signal, not authoritative identity. When logo recognition and GPS candidates agree, confidence is higher; when they disagree, the user must choose.

If photo EXIF contains GPS coordinates, extract them before asking for a separate live-location permission prompt.

## Location source and confidence

Where useful, an entry may store metadata describing how station/location information was obtained.

Examples:

- `photo_exif`
- `gps`
- `saved_station`
- `manual`

A confidence indicator may also be useful for UI and debugging, but it must not replace explicit confirmation.

## Mapping and analytics

The location model should support an eventual interactive map where each fuel event can expose:

- date
- vehicle
- station
- city/intersection
- gallons
- price per gallon
- total cost
- fill type
- MPG when applicable
- comments

Analytics may compare station brands, specific locations, cities/areas, and price trends. These analytics should not imply that station brand itself causes MPG changes without controlling for other factors.

## Explicit non-goals and guardrails

- No continuous/background location tracking.
- GPS must not be required to save a fuel entry.
- AI/OCR must not silently overwrite user-entered values.
- A station guess must not become confirmed merely because it is geographically closest.
- Partial fills must not be deleted or hidden from history.
- The system must not fabricate an MPG result for an incomplete full-to-full interval.
- New features should not make ordinary entry materially slower.
- Private production logs, exact personal location history, API keys, and device secrets should not be committed to the public repository.

## Decision summary

The application treats fuel entries as events and MPG as a measurement that exists only when a valid interval is complete. Automation is used to reduce friction, but the user's explicit choice remains the final authority.
