# Revision History

## Current Revision

**Version:** 1.2.11  
**Updated:** 2026-07-25  
**Status:** Active development

## Revision 1.2.11 — Stats Chart Drill-Down Links

Made the stats-page daily upload bars clickable so they open the log page already filtered to the selected uploaded date.

Key implementation changes:

- `stats.php` now links each daily upload bar to `view_log.php?uploaded=YYYY-MM-DD`.
- `view_log.php` now accepts an uploaded-date filter and shows a clear-filter chip when active.
- `style.css` adds the date-filter chip styling and hover treatment for clickable chart bars.

## Revision 1.2.10 — Reliable Photo Preview Trigger

Hardened the log photo preview so clicking the `photo` link still opens the overlay even if later page scripts fail to bind the delegated click listener.

Key implementation changes:

- `view_log.php` now gives photo links an inline preview handler with the image URL as a normal fallback target.
- The shared photo preview function is reused by both the inline handler and the JS event binding.

## Revision 1.2.9 — Dedicated Stats Page and Visible Log Filters

Moved the daily upload chart off the log page into its own stats dashboard and fixed the log summary cards so their counts are visible without hover and clearly filter the entries list.

Key implementation changes:

- `stats.php` now holds the daily uploads chart and summary counters.
- `view_log.php` removes the chart and scrolls to the entries table when a summary card filter is clicked.
- `style.css` fixes stat-card text visibility and active-state styling.
- Navigation now includes a `Stats` link on the main pages.

## Revision 1.2.8 — Manual Correction, Favorites, and Preference Ranking

Added an in-place log editor so missed or incorrect plate values can be corrected manually, and plates can now be marked as favorites with a 1-10 personal ranking.

Key implementation changes:

- `update_entry.php` updates existing log rows with manual plate/state corrections, favorite status, and preference rank.
- `view_log.php` adds visible `Fav` and `Rank` columns plus an edit dialog for every row.
- `config.php` now supports manual update normalization and scanner labels that show `+ Manual` after a correction.
- `process_upload.php` initializes new entries with favorite and rank defaults.

## Revision 1.2.7 — Daily Upload Volume Chart

Added a per-day upload chart to the log page so activity can be reviewed visually instead of only through the raw table.

Key implementation changes:

- `view_log.php` now groups log entries by uploaded date and renders a daily bar chart.
- `style.css` adds the chart layout and horizontal scrolling for days that exceed the viewport width.

## Revision 1.2.6 — In-Page Photo Overlay Preview

Changed the log photo links so they open a large overlay preview instead of navigating away from the log page.

Key implementation changes:

- `view_log.php` now opens photo links inside an in-page overlay dialog.
- `style.css` adds the large centered preview layout, backdrop, and close controls.
- The preview can be closed with the `X`, the dark backdrop, or the `Escape` key.

## Revision 1.2.5 — Active Log Filters and Changelog Page Alignment

Made the top dashboard cards visibly active when filtering the log and aligned the changelog page with the current revision and full-width layout.

Key implementation changes:

- `view_log.php` now wires the top stat cards directly to filter actions and shows an active selected state.
- `style.css` adds active-card styling so the current filter is obvious.
- `changelog.php` now carries the current file revision and uses the full-width page container.

## Revision 1.2.4 — Full-Width Log Layout and Clearer Retry Selection

Expanded the log page to use the full desktop width, tightened the count widgets, and sized the log columns so the headers stay readable.

Key implementation changes:

- `style.css` adds a `container-wide` layout for full-width desktop pages.
- `style.css` shrinks the stat cards into a denser clickable grid.
- `style.css` gives the log table explicit minimum widths so headers like `Confidence`, `Date Taken`, and `Original File` no longer collapse.
- The failed-entry retry flow remains checkbox-based in the first column, with `Select All Failed` and `Retry Selected Failed` controls in the pending section.

## Revision 1.2.3 — Mobile Batch Layout and Queue Reset

Fixed the upload-page mobile layout and made completed batches clear themselves so the same selected files are not processed again by accident.

Key implementation changes:

- `index.php` now clears the file input and disables the process button after a completed batch.
- `index.php` wraps the batch results table in a horizontal scroll container.
- `style.css` gives the batch-results columns practical minimum widths and stacks the action buttons on small screens.

## Revision 1.2.2 — Duplicate Clarity Scoring

Added a local image-clarity score so repeated-plate groups can identify which stored photo appears sharpest.

Key implementation changes:

- `config.php` computes `clarity_score` from image edge contrast and recalculates the best photo within repeated-plate groups.
- `process_upload.php` stores clarity scores for new uploads and duplicate reuse records.
- `process_pending.php` preserves and returns clarity data after pending retries complete.
- `view_log.php` shows a sortable `Clarity` column and labels repeated-plate winners as `clearest photo`.

## Revision 1.2.1 — Visible Changelog and Project Revision Badge

Added a rendered changelog page, linked it in the main navigation, and exposed the project revision plus changelog modified time on the upload page.

Key implementation changes:

- `changelog.php` renders `changelog.md` into a readable project changelog page.
- `index.php` shows the current project revision and changelog modified timestamp in a corner badge.
- `config.php` now provides changelog rendering and project revision helper functions.
- Touched files were revision-bumped for this update set.

## Revision 1.2.0 — Searchable Photo Metadata

Added metadata extraction and log search so uploaded photos can be filtered by GPS, state, and date-taken details.

Key implementation changes:

- `config.php` now extracts EXIF/IPTC `date_taken`, `photo_state`, and GPS coordinates when available.
- Scan results can also store `plate_state` when the plate state is visible in OpenAI or OCR text.
- `process_upload.php` and `process_pending.php` preserve metadata in both the main log and the hash index.
- `view_log.php` adds searchable `State`, `Date Taken`, and `GPS` columns plus a client-side search box.

## Revision 1.1.3 — Scanner Labels and Confidence Normalization

Clarified which scanner produced each plate result and normalized OpenAI confidence values so a returned `1` is treated as `100%` rather than `1%`.

Key implementation changes:

- `config.php` now normalizes confidence values into a consistent `0..100` range.
- `view_log.php` displays confidence with a percent sign and labels the scanner source with user-friendly names.
- Duplicate-file reuse preserves the original scanner source in the hash index and new log entries.
- Pending status text is scanner-agnostic.

## Revision 1.1.2 — Sortable Auto-Sized Log Columns

Updated the log table so the entry columns size to their content and each header can reorder the visible rows in the browser.

Key implementation changes:

- `view_log.php` wraps the entries table in a horizontal scroll container and converts entry headers into sort buttons.
- Sorting supports uploaded timestamps, numeric confidence values, and text-based columns.
- The current sort direction is reflected on the active header.

## Revision 1.1.1 — Bulk Retry for Failed Pending Entries

Added failed-entry selection controls to the log page so previously failed pending photos can be retried in smaller batches instead of only one at a time or all pending at once.

Key implementation changes:

- `view_log.php` shows a checkbox for each pending entry that already has a scanner error.
- The pending-actions card includes **Retry Selected Failed** and **Select All Failed** controls when failed pending entries exist.
- Selected retries still run sequentially in the browser to avoid PHP timeout risk.
- Successful selected retries remove the row from the failed-selection pool and refresh pending counts.

## Revision 1.1.0 — Pending Processing

Introduced a persistent processing queue for photos that upload successfully but cannot be scanned because the configured AI or OCR service is unavailable.

Key implementation changes:

- Pending records are retained in `data/plate-log.json`.
- Uploaded images remain in `uploads/` for later processing.
- File hashes remain in `data/file-hashes.json` for duplicate detection.
- `process_pending.php` retries one existing record by ID.
- `view_log.php` provides individual and batch retry controls.
- Batch retries run sequentially in the browser to reduce PHP timeout risk.
- Successful retries update the existing record, scanner result, hash index, and duplicate indicators.
- Failed retries remain pending and retain the latest scanner error.

Related commits:

- `46eb28e6db522c9107c673a795bec6909d3cb7b1` — Correct failed-scan accounting.
- `177cad169b9317817c9e942844d2b8ad2e0637e4` — Preserve uploads for later processing.
- `0e9c01611e28dcd3e8390f572b3e3bbaa76627e5` — Add pending reprocessing endpoint.
- `1ea16f647160f3e23eb67aa9139aedb9af8576c2` — Add retry controls to the log page.

## Revision 1.0.0 — Initial Release

Initial self-hosted PHP implementation with multi-photo uploads, configurable recognition modes, JSON logging, SHA-256 duplicate-file detection, repeated-value detection, and a browser log viewer.
