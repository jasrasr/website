# Changelog

All notable changes to the License Plate Photo Logger are documented here.

## [1.1.0] - 2026-07-23

### Added
- Pending-processing workflow for photos that upload successfully but cannot be scanned.
- `process_pending.php` endpoint for retrying a saved photo.
- **Retry** action for individual pending entries.
- **Process All Pending** action that processes saved photos sequentially.
- Pending count, status, scanner message, and retry feedback on the log page.
- `CHANGELOG.md`, `REVISION_HISTORY.md`, and `VERSION.txt`.

### Changed
- Scanner failures no longer discard successfully uploaded photos.
- Pending items remain in the JSON log and SHA-256 duplicate index.
- Successful retries update the existing entry rather than creating another entry.
- Duplicate plate flags are recalculated after a pending item is recognized.
- The file hash index is updated after successful reprocessing.

### Fixed
- AI configuration failures were previously displayed as successfully logged scans.
- Batch totals now distinguish completed scans, pending scans, and failed uploads.

## [1.0.0] - 2026-05-08

### Added
- Multi-image browser upload queue.
- AI Vision, OCR.Space, Tesseract, and manual scanner modes.
- JSON activity log.
- SHA-256 duplicate-file detection.
- Normalized repeated-value detection.
- Log viewer with stored-photo links and duplicate summaries.
