# Baby Daily Photo Tracker

A private, mobile-first PHP/JSON gallery for one baby photo per day. Built from the conventions in `1-Framework` for shared Hostinger hosting.

## Included in v1.0.0

- First-run password setup and protected sessions
- Daily or backdated JPEG, PNG, and WebP uploads
- One photo per calendar date
- Due date of November 2, 2026 and editable actual birth date
- Automatic day numbering after the birth date is entered
- Photo count, current contiguous streak, and missing-date view
- Optional notes and full-photo viewer
- Browser-generated downloadable collage of up to 100 photos
- Atomic JSON writes, private image delivery, request IDs, and audit events
- Responsive iPhone-friendly layout

## Install

1. Deploy this folder to `jasr.me/github/daily-babe/`.
2. Ensure PHP can write to `storage/`.
3. For stronger privacy, copy `config/config.example.php` to `config/config.php` and set `storage_path` to a writable folder above `public_html`.
4. Open the site and create a password of at least 10 characters.
5. After the baby arrives, enter the actual birth date in Settings.

Runtime photos, account data, logs, and settings are ignored by Git and must be backed up separately through Hostinger. The source repository contains no baby photos or production password.

The included Apache rules deny browser access to `storage/` and `config/`. Keep those files in place even if storage is later moved above `public_html`.

## Manual validation

- Complete first-run setup and confirm a second browser must sign in.
- Upload an image for today and a backdated image.
- Confirm a duplicate date is rejected.
- Sign out and confirm both the gallery API and `image.php` deny access.
- Enter the actual birth date and verify day numbering and missing dates.
- Download a collage and confirm photos remain ordered oldest to newest.
