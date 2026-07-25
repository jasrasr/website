# Revision History

## Current Revision

**Version:** 1.2.25  
**Updated:** 2026-07-25  
**Status:** Active development

## Revision 1.2.25 — Local Server Launcher For License Plate

Replaced the copied HumidorHQ local launcher with a repo-specific PHP server script that works for the flat `license-plate` application.

Key implementation changes:

- `start-local-server.ps1` now validates the local app files instead of HumidorHQ runtime JSON collections.
- `start-local-server.ps1` starts PHP with the repository root as the document root for this app.
- `start-local-server.ps1` reports the local URL and can skip opening Chrome with `-NoBrowser`.

## Revision 1.2.24 — Cache-Busted Stylesheet Loading

Added revision-based query strings to the main stylesheet links so the live site stops serving stale cached CSS after layout and UI updates.

Key implementation changes:

- `index.php`, `view_log.php`, `stats.php`, `deleted_audit.php`, and `changelog.php` now load `style.css?v=<project revision>`.
- This forces browsers and intermediate caches to request the correct stylesheet after each visible UI revision bump.

## Revision 1.2.23 — Rebalanced Log Column Widths

Reduced the oversized desktop log table widths so the table still stays readable without stretching far wider than necessary.

Key implementation changes:

- `style.css` reduces the overall log-table minimum width.
- `style.css` trims several individual desktop column minimum widths.
- `style.css` allows more wrapping in long-text columns so the table uses width more efficiently.

## Revision 1.2.22 — Wider Desktop Layout And Larger Table Columns

Expanded the upload page to use the full desktop width and increased the minimum widths on both the upload results table and the log table so columns stay readable.

Key implementation changes:

- `index.php` now uses the wide desktop container instead of the narrower centered layout.
- `style.css` increases the base desktop container width and widens the upload results table columns.
- `style.css` increases the log table minimum width and individual log column minimum widths.
- `style.css` also widens the top stat-card grid so summary cards have more room on desktop.

## Revision 1.2.21 — Reliable Photo Link Fallback

Fixed log photo links so they can still open the uploaded image directly if the JavaScript preview overlay is unavailable.

Key implementation changes:

- `view_log.php` adds `target="_blank"` and `rel="noopener"` to photo links for a direct browser fallback.
- `view_log.php` makes `openPhotoPreviewFromLink()` return normal navigation when the overlay elements are not available.
- `view_log.php` changes the click listener so it only calls `preventDefault()` after the overlay successfully takes over.

## Revision 1.2.20 — Visible Stats Chart Bars

Fixed the stats upload chart so the daily bars are visually obvious and the chart uses the available page width.

Key implementation changes:

- `stats.php` raises the minimum bar height and adds an explicit empty state when there are no uploads to graph.
- `style.css` expands the chart card to full width.
- `style.css` gives the chart bars fixed visible width, stronger color, border, shadow, and a baseline/grid background.
- `style.css` increases chart row and item dimensions for desktop and mobile.

## Revision 1.2.19 — Deleted Audit Purge Log

Added a separate purge log so clearing the deleted-item audit still leaves a record of when the purge happened and how much it removed.

Key implementation changes:

- `config.php` adds `DELETED_PURGE_LOG_FILE` for purge history separate from `deleted-audit.json`.
- `config.php` logs every `purgeDeletedAudit()` call with purge time, trigger, purged item count, and removed archived-photo count.
- `deleted_audit.php` marks user-triggered purges as `manual`.
- `deleted_audit.php` displays a Purge Log table above the deleted items table.
- `style.css` adds a minimum width for the purge log table.

## Revision 1.2.18 — Eastern Timestamp Display

Standardized displayed timestamps so pages show Eastern time in `YYYY-MM-DD HH:MM:SS` format instead of raw ISO strings.

Key implementation changes:

- `config.php` adds shared Eastern date/time display helpers.
- `deleted_audit.php` renders deleted dates through the shared Eastern display helper.
- `view_log.php` renders uploaded and date-taken values through Eastern display helpers and uses Eastern dates for uploaded-date filters.
- `stats.php` groups upload counts by Eastern date.
- Project modified timestamps now render without the timezone suffix for a consistent page display format.

## Revision 1.2.17 — Persistent Filter Deletes And Multi-Delete

Changed log deletion so the current filter/search context stays visible after a delete, and added multi-select deletion with one shared reason for all selected entries.

Key implementation changes:

- `view_log.php` adds delete-selection checkboxes to the Select column for every row.
- `view_log.php` adds Select Visible, Clear Selection, and Delete Selected controls above the entries table.
- `view_log.php` reuses the existing delete dialog for single and multi-delete workflows, sending the same reason to each selected deletion request.
- `view_log.php` removes successfully deleted rows from the current table without reloading the page, preserving active filter/search state.
- `style.css` adds compact layout styles for the bulk delete controls and Select column labels.

## Revision 1.2.16 — Quick Delete Reasons

Added preset delete reasons to the log entry delete dialog so common cleanup cases can be selected without typing.

Key implementation changes:

- `view_log.php` adds quick reason buttons for duplicate uploads, wrong uploads, and bad plate reads above the required delete-reason textarea.
- `view_log.php` fills the existing delete-reason textarea from the selected preset, preserving the existing required-reason validation and audit storage.
- `style.css` adds compact button spacing for the quick reason row.

## Revision 1.2.15 — Wider Log Layout And Visible Summary Widgets

Fixed the log page dashboard widgets so their labels and counts remain visible without hover, and widened the entry table so dense values rely on horizontal scrolling instead of cramped column wrapping.

Key implementation changes:

- `style.css` moves the stat-card button styling after the generic button rules so the widget text, count, background, and active state are not overridden.
- `style.css` expands the compact stats grid across the available page width.
- `style.css` increases log table minimum width and per-column widths for uploaded time, status, metadata, original file, duplicate, scanner, message, and actions.
- `style.css` reduces outer page padding on desktop so full-width pages have more usable space.

## Revision 1.2.14 — Corner Revision Badge On Main Pages

Added the project revision and modified timestamp badge to the main app pages so the same corner status is visible outside the upload page.

Key implementation changes:

- `view_log.php`, `stats.php`, `deleted_audit.php`, and `changelog.php` now render the `project-badge` corner block.
- `changelog.php` removes the duplicate revision box from the page header and relies on the shared corner badge.

## Revision 1.2.13 — Case-Safe Changelog Source

Fixed the project changelog source path so case-sensitive servers read the tracked `CHANGELOG.md` file correctly and the front-page modified timestamp resolves again.

Key implementation changes:

- `config.php` now points to `CHANGELOG.md`, which matches the tracked filename in Git.
- `config.php` also falls back to `changelog.md` so the app remains tolerant if a lowercase copy exists in another environment.
- `changelog.php` now labels the rendered source file using the tracked uppercase filename.

## Revision 1.2.12 — Deleted Audit Workflow

Added a soft-delete workflow that removes entries from the active log, moves photos into a deleted archive when possible, records delete reasons in an audit log, and allows permanent delete or full purge from a dedicated deleted-items page.

Key implementation changes:

- `delete_entry.php` now soft-deletes active log entries and records audit metadata.
- `deleted_audit.php` shows deleted items, delete reasons, deleted age in days, permanent-delete actions, and a purge control.
- `config.php` now manages the `deleted/` folder plus the deleted-audit JSON log and permanent-delete helpers.
- `view_log.php` adds a required delete-reason prompt before removing an active entry.
- Main navigation now includes a `Deleted` page link.

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
