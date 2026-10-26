# Baby Daily Photo Tracker

A private, mobile-first PHP/JSON gallery for one baby photo per day. Built from the conventions in `1-Framework` for shared Hostinger hosting.

## Included in v1.4.0

- First-run password setup and protected sessions
- Separate user sign-ins and baby galleries, with admin-managed gallery access
- Original gallery owner keeps admin access to every baby gallery
- Any authorized user can remove photos from an accessible gallery
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
4. Open the site and create an admin username, a password of at least 10 characters, and the first baby gallery.
5. After the baby arrives, enter the actual birth date in Settings.

The due date powers the pre-birth countdown and shows whether the baby arrived early, on the due date, or afterward. Once an actual birth date is entered, it becomes the source for Day 1, age, and missing-day calculations; changing the due date will not renumber the photos.

## Users and baby storage

User accounts, password hashes, and baby gallery references live in the protected `storage/data/auth.json` file. Each gallery keeps its existing random internal ID and stores its records separately:

```text
storage/
├── data/auth.json
└── babies/<random-baby-id>/
    ├── data/settings.json
    ├── data/entries.json
    ├── uploads/
    └── backups/
```

Usernames are case-insensitive and unique. The admin can add baby galleries and create users from Settings, then choose which galleries each user may access. A user can switch among their permitted galleries.

The first existing account remains the admin. Existing usernames and passwords keep working after migration, and each account retains access to its original baby gallery. The admin can open every gallery. Open a photo and choose **Remove photo** to delete it permanently; the date can then be used for a new upload.

Existing v1.2 and v1.3 installations migrate their authentication file to separate users and babies on the next page load. The migration writes a copy of the prior authentication file to `storage/backups/auth-before-user-management.json` and leaves each baby folder untouched. Existing v1.1 and earlier installations still migrate on the next successful sign-in; enter a username with the existing password. That older migration copies metadata and photos into the first baby folder before replacing the legacy authentication format.

Runtime photos, account data, logs, and settings are ignored by Git and must be backed up separately through Hostinger. The source repository contains no baby photos or production password.

The included Apache rules deny browser access to `storage/` and `config/`. Keep those files in place even if storage is later moved above `public_html`.

## Manual validation

- Complete first-run setup and confirm a second browser must sign in.
- Upload an image for today and a backdated image.
- Confirm a duplicate date is rejected.
- Sign out and confirm both the gallery API and `image.php` deny access.
- Enter the actual birth date and verify day numbering and missing dates.
- Download a collage and confirm photos remain ordered oldest to newest.
