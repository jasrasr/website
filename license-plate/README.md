# License Plate Photo Logger

A self-hosted PHP app for uploading many license plate photos, extracting the plate text, capturing available photo metadata, and saving a JSON log with duplicate file and repeated plate checks.

## Why AI Vision Is The Default

License plate photos are often angled, reflective, partially cropped, or taken at night. General OCR can work on clean, straight-on photos, but it often returns surrounding bumper stickers, state text, or random background text. The default `SCAN_MODE` is `ai` because the vision parser can focus on the plate itself and return one normalized value.

OCR modes are still available:

| Mode | Use When |
|------|----------|
| `ai` | Best default for mixed real-world plate photos |
| `ocrspace` | Good for clean photos and lower-cost OCR-only processing |
| `tesseract` | Useful if local Tesseract is installed on the web server |
| `manual` | Logs uploads without scanner calls |

## Files

| File | Purpose |
|------|---------|
| `index.php` | Multi-photo upload queue and progress table |
| `process_upload.php` | Processes one uploaded photo, extracts the plate, and writes the log |
| `view_log.php` | Shows logged entries, metadata columns, search, and duplicate summaries |
| `changelog.php` | Renders the project changelog from `changelog.md` |
| `config.php` | App settings, scanner integrations, metadata extraction, and duplicate helpers |
| `data/plate-log.json` | Runtime JSON log, created automatically |
| `data/file-hashes.json` | Runtime SHA-256 duplicate file index, created automatically |
| `uploads/` | Runtime uploaded photos |

## Setup

1. Upload this folder to the PHP host as `/license-plate/`.
2. Make `data/` and `uploads/` writable by the web server.
3. Copy `secrets.example.php` to `secrets.php`, or create a local `.env` file like the `mpg` app uses.
4. Add `OPENAI_API_KEY` for `SCAN_MODE = 'ai'`, or `OCRSPACE_API_KEY` for `SCAN_MODE = 'ocrspace'`.
5. Open `index.php` and upload a batch of photos.

Runtime data, uploaded photos, API keys, and `.env` files are ignored by git.

## Project Revision

The project revision shown on the upload page comes from `VERSION.txt`, and the modified timestamp shown there comes from `changelog.md`.

## Metadata

When EXIF or IPTC fields are present, uploads now store:

- `date_taken`
- `photo_state`
- `gps_latitude`
- `gps_longitude`
- `gps_display`

The scanner may also store `plate_state` when the state name is visible on the plate.

The log page includes a client-side search box that filters by plate, state, GPS, date taken, original filename, and message text.
Repeated-plate groups also store a local `clarity_score` so the clearest photo can be flagged in the log.

On mobile, the batch results table scrolls horizontally instead of compressing columns, and completed batches clear the current file selection automatically.

## Duplicate Handling

Every upload is hashed with SHA-256 before scanning. If the same file is uploaded again, the app flags it as `same file` and reuses the original stored file metadata. The plate value is also normalized to uppercase letters and digits so repeated plate numbers are counted even when punctuation or spacing differs.

The browser sends files one at a time. This is intentional for large batches: a folder with several thousand photos can keep progressing without one oversized HTTP request.
