# Timeclock Photo Logger

A self-hosted PHP web app for logging employee hours from clock-out slip photos. Upload a photo, optionally run OCR, review and correct the parsed fields, then save to a JSON log. Includes stats by employee, week, and month.

## Features

- Upload clock-out slip photos (JPG, PNG, WEBP, max 10 MB)
- Three OCR modes: manual, local Tesseract, or OCR.Space API
- Review and correct all parsed fields before saving
- Manual entry fallback (no photo required)
- Duplicate shift protection
- Recalculates shift minutes from time-in/time-out (handles overnight shifts)
- Compares printed shift hours against calculated shift hours
- Stats by employee, ISO week, and month
- JSON flat-file storage — no database required

## Requirements

- PHP 8.0+ with `curl` extension (for OCR.Space mode)
- Writable `data/` and `uploads/` directories
- Tesseract installed on the server (only for `tesseract` OCR mode)

## Installation

1. Upload the folder contents to your PHP host, e.g. into `/timeclock/`
2. Ensure these paths are writable by the web server:
   - `data/`
   - `uploads/`

   *(The app will create them automatically on first run if permissions allow.)*
3. Open `config.php` and configure as needed.

## Configuration

All settings are in `config.php`.

### OCR Mode

| Value | Description |
|-------|-------------|
| `manual` | No OCR — user types/corrects all fields after upload. Safe on shared hosting. **Default.** |
| `tesseract` | Runs local Tesseract via shell: `tesseract <image> stdout` |
| `ocrspace` | Calls the [OCR.Space](https://ocr.space) API |

```php
const OCR_MODE = 'manual';        // manual | tesseract | ocrspace
const OCRSPACE_API_KEY = '';      // required if OCR_MODE = 'ocrspace'
```

### Timezone

```php
date_default_timezone_set('America/New_York');
```

## Usage

| Page | URL | Description |
|------|-----|-------------|
| Upload | `index.php` | Upload a clock-out slip photo |
| Review | `review.php` | Review/correct OCR-parsed fields before saving |
| Manual Entry | `manual.php` | Enter a shift without a photo |
| View Log | `view.php` | Browse all saved entries |
| Stats | `stats.php` | Hours by employee, ISO week, and month |

## Data Storage

- `data/employees/<name>.json` — one JSON file per employee (e.g. `john_doe.json`)
- `uploads/` — uploaded clock-out slip images
- `data/*.ocr.txt` — raw OCR output per upload
- `data/*.parsed.json` — parsed field output per upload

Employee files are created automatically on first save. The filename is derived from the employee name (lowercase, spaces replaced with underscores).

## Parsed Clock Slip Fields

| Field | Description |
|-------|-------------|
| Date | Shift date |
| Employee | Employee name |
| Time In / Time Out | Shift start and end times |
| Printed Shift Hours | Hours shown on the slip |
| Printed Week Hours | Weekly hours shown on the slip |

## Revision History

| Version | Notes |
|---------|-------|
| 1.0.0 | Initial release |
| 1.1.0 | Per-employee JSON files instead of single hours.json |
| 1.2.0 | Remove unused fields: unit, job, carry over/declared/charge tips |
