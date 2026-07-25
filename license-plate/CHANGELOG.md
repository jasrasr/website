# Changelog

All notable changes to the License Plate Photo Logger are documented here.

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
