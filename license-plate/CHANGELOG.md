# Changelog

All notable changes to the License Plate Photo Logger are documented here.

## [1.2.21] - 2026-07-25

### Fixed
- Photo links in the log now keep a direct browser fallback to the uploaded image if the preview overlay cannot open.
- The photo preview click handler now only prevents normal link navigation after the overlay is available.

## [1.2.20] - 2026-07-25

### Fixed
- The stats upload chart now renders visible colored bars instead of only counts and date labels.
- The chart now uses the full page width and shows an empty-state message when there are no uploads to graph.

## [1.2.19] - 2026-07-25

### Added
- Deleted-audit purges are now written to a separate purge log with purge time, trigger, item count, and removed archived-photo count.
- The deleted audit page now displays purge history so manual or future automatic purges remain visible after the deleted audit is cleared.

## [1.2.18] - 2026-07-25

### Fixed
- Deleted audit dates now display as `YYYY-MM-DD HH:MM:SS` in Eastern time instead of raw ISO timestamps.
- Uploaded, date-taken, stats date grouping, and project modified timestamps now use shared Eastern display helpers.

## [1.2.17] - 2026-07-25

### Added
- The log page now supports selecting multiple entries and deleting them with one shared reason.

### Fixed
- Deleting entries no longer reloads the page, so the active log filter and search stay in place.

## [1.2.16] - 2026-07-25

### Added
- The delete confirmation dialog now includes quick reason buttons for duplicate uploads, wrong uploads, and bad plate reads.

## [1.2.15] - 2026-07-25

### Fixed
- The log summary widgets now keep visible text and counts without requiring hover.
- The log page uses more of the available browser width.
- Log table columns are wider so values wrap less awkwardly and rely on horizontal scrolling for dense data.

## [1.2.14] - 2026-07-25

### Added
- The project revision and modified timestamp badge now appears in the corner of the main app pages, not just the upload page.

## [1.2.13] - 2026-07-25

### Fixed
- The app now reads the tracked `CHANGELOG.md` filename correctly on case-sensitive servers, restoring the visible changelog content and modified timestamp.

## [1.2.12] - 2026-07-25

### Added
- Active log entries can now be deleted with a required reason.
- Deleted items are now tracked on a dedicated deleted-audit page with deleted date/time, age in days, and permanent-delete actions.
- A purge button can now clear the deleted audit and archived deleted photos after confirmation.

### Changed
- Deleted photos are moved into a `deleted/` folder when they are no longer referenced by another active entry.

## [1.2.11] - 2026-07-25

### Added
- Clicking a day bar on the stats page now opens the log already filtered to that uploaded date.

## [1.2.10] - 2026-07-25

### Fixed
- Clicking `photo` in the log now reliably opens the in-page preview overlay again.

## [1.2.9] - 2026-07-25

### Fixed
- The log summary counters now show their text and counts without requiring hover.
- Clicking a log summary counter now visibly acts as a filter and jumps to the entries table.

### Changed
- The daily upload graph was moved off the log page and into a separate `Stats` page.

## [1.2.8] - 2026-07-25

### Added
- Log entries can now be edited in place for manual plate/state correction.
- Plates can now be marked as favorites and assigned a personal preference rank from 1 to 10.

### Changed
- Corrected entries now show the original scanner plus `Manual` in the scanner column.

## [1.2.7] - 2026-07-25

### Added
- The log page now shows a bar chart of how many plates were uploaded per day, based on the stored upload timestamp.

## [1.2.6] - 2026-07-25

### Changed
- Photo links in the log now open a large in-page overlay preview instead of navigating directly to the image file.

### Added
- The image preview can be dismissed with the `X` button, by clicking outside the image, or by pressing `Escape`.

## [1.2.5] - 2026-07-25

### Fixed
- The top log summary cards now trigger filtering more explicitly and show which filter is active.
- `changelog.php` now reflects the current revision and uses the wider page layout.

## [1.2.4] - 2026-07-25

### Changed
- The log page now uses the full desktop content width instead of the narrower centered container.
- The count widgets are now smaller, denser, and better suited for dashboard-style filtering.
- The log table columns now have minimum widths so headers no longer wrap into unreadable narrow stacks.

### Clarified
- Failed pending items are still selected with the first-column checkboxes, and the pending section keeps `Select All Failed` plus `Retry Selected Failed` for bulk retry.

## [1.2.3] - 2026-07-25

### Fixed
- The upload page now clears the selected batch after processing so the same files cannot be re-run accidentally with one tap.
- The batch buttons now stack cleanly on narrow mobile screens.
- The batch results table now uses horizontal scrolling instead of collapsing into unreadable narrow columns on mobile.

### Changed
- The upload summary now explicitly tells the user to choose new files after a completed batch.

## [1.2.2] - 2026-07-25

### Added
- Duplicate and repeated-plate entries now store a local `clarity_score`.
- Repeated-plate groups now flag the clearest available photo.
- The log shows a sortable `Clarity` column.

### Changed
- Duplicate messaging on the upload and log pages now identifies the `clearest photo` when applicable.
- Restored `changelog.md` as the visible changelog source used by `changelog.php`.

## [1.2.1] - 2026-07-25

### Added
- `changelog.php` page that renders the project changelog from `changelog.md`.
- `Changelog` navigation link on the upload and log pages.
- Front-page project revision and changelog modified timestamp badge.

### Changed
- The project now uses `changelog.md` as the visible changelog source.
- `config.php` provides shared helpers for project revision, changelog modified time, and changelog rendering.
- Updated touched file revisions for this change set.

## [1.2.0] - 2026-07-25

### Added
- EXIF/IPTC metadata capture for `date_taken`, `photo_state`, and GPS coordinates.
- Scanner state capture as `plate_state` when OpenAI or OCR text reveals the plate state.
- Client-side log search across plate, state, GPS, date taken, file, and message fields.
- `State`, `Date Taken`, and `GPS` columns on the log page.

### Changed
- Metadata fields are now written into `data/plate-log.json` and preserved in `data/file-hashes.json`.
