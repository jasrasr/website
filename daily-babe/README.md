# Baby Daily Photo Tracker

A private, mobile-first PHP/JSON gallery for one baby photo per day. Built from the conventions in `1-Framework` for shared Hostinger hosting.

## Included in v1.1.0

- First-run password setup and protected sessions
- Baby first-name usernames with current/new/confirm password changes
- Multiple independently authenticated baby galleries
- Daily or backdated JPEG, PNG, and WebP uploads
- One photo per calendar date
- Due date of November 2, 2026 and editable actual birth date
- Optional birth time, length, and weight details
- Automatic day numbering after the birth date is entered
- Photo count, current contiguous streak, and missing-date view
- Optional notes and full-photo viewer
- Browser-generated downloadable collage and animated GIF slideshow of up to 100 photos
- Day labels on every collage tile and GIF frame
- Atomic JSON writes, private image delivery, request IDs, and audit events
- Responsive iPhone-friendly layout

## Install

1. Deploy this folder to `jasr.me/github/daily-babe/`.
2. Ensure PHP can write to `storage/`.
3. For stronger privacy, copy `config/config.example.php` to `config/config.php` and set `storage_path` to a writable folder above `public_html`.
4. Open the site and create a password of at least 10 characters.
5. After the baby arrives, enter the actual birth date in Settings.

The due date powers the pre-birth countdown and shows whether the baby arrived early, on the due date, or afterward. Once an actual birth date is entered, it becomes the source for Day 1, age, and missing-day calculations; changing the due date will not renumber the photos.

## Multi-baby storage

All baby accounts and password hashes live in the single protected `storage/data/auth.json` file. Each account points to a random internal ID and stores its gallery separately:

```text
storage/
├── data/auth.json
└── babies/<random-baby-id>/
    ├── data/settings.json
    ├── data/entries.json
    ├── uploads/
    └── backups/
```

The baby’s first name is the sign-in username. Usernames are case-insensitive and must be unique. Add another baby from Settings; the newly created gallery opens immediately.

Existing v1.1 and earlier installations migrate on the next successful sign-in. Enter the baby’s first name with the existing password. The migration copies the old metadata and photos into the first baby folder before replacing the legacy authentication format.

Runtime photos, account data, logs, and settings are ignored by Git and must be backed up separately through Hostinger. The source repository contains no baby photos or production password.

The included Apache rules deny browser access to `storage/` and `config/`. Keep those files in place even if storage is later moved above `public_html`.

## Manual validation

- Complete first-run setup and confirm a second browser must sign in.
- Upload an image for today and a backdated image.
- Confirm a duplicate date is rejected.
- Sign out and confirm both the gallery API and `image.php` deny access.
- Enter the actual birth date and verify day numbering and missing dates.
- Download a collage and confirm photos remain ordered oldest to newest.
