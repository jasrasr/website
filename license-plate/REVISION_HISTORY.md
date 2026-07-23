# Revision History

## Current Revision

**Version:** 1.1.0  
**Updated:** 2026-07-23  
**Status:** Active development

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
