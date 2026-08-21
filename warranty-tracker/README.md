# Warranty Tracker

A PHP and JSON home-warranty portal based on the conventions in `1-Framework`.

## Features

- Add, edit, delete, search, filter, and sort warranty records
- Track product, category, manufacturer, model, serial number, seller, provider, item cost, warranty cost, dates, receipt URL, and notes
- Dashboard totals for active, expiring, expired, and tracked value
- Continuous green-to-red expiration cue; warranties within 90 days are considered expiring soon
- Responsive phone and desktop layout
- JSON persistence with shared/exclusive locks and atomic replacement
- Framework-standard JSON API responses

## Hosting

Deploy beside `1-Framework` so `../1-Framework/bootstrap.php` resolves. PHP must be able to write to `storage/`. The included `.htaccess` blocks direct storage access on Apache; storage outside the public web root is preferred when the host permits it.

## Manual validation

1. Open `index.php` and verify the empty state.
2. Add a warranty and confirm all dashboard totals update.
3. Edit it, reload the page, and confirm persistence.
4. Test active, 90-day, today, and expired dates for correct status/color.
5. Search, filter, and sort the record.
6. Delete it and confirm the JSON record is removed.

## Version

1.1.0 — 2026-08-21
