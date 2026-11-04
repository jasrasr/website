# Baby Daily Photo Tracker

A mobile-first PHP/JSON gallery for one baby photo per day. Daily Babe uses the site-wide User Management service for adult accounts and keeps baby galleries, photos, and metadata isolated inside the project.

## Included in v2.4.0

- Central User Management sign-in, password changes, sessions, and account status
- Multiple babies and multiple adults, with per-gallery membership
- Central `viewer`, `member`, and `admin` project roles
- Daily or backdated JPEG, PNG, and WebP uploads
- One photo per calendar date
- Editable due date, actual birth date, birth time, length to hundredths of an inch, and weight
- Day numbering based on actual birth date when present
- Missing-day and streak tracking
- Collage and animated GIF exports with a day label on every image
- Private, unlisted, and public gallery visibility
- Public links based on first name and birthday, such as `hudson-11022026`
- Atomic JSON writes, protected image delivery, request IDs, and audit events
- Optional daily missing-photo email reminders through authenticated Titan SMTP
- Lazy 720-pixel WebP gallery thumbnails while enlarged views and exports use the original photo

Thumbnail generation requires PHP GD with JPEG, PNG, and WebP support. If it is unavailable, galleries safely fall back to the original image. Existing photos receive cached thumbnails automatically the first time a gallery tile requests them.

## Authentication and permissions

Daily Babe does not store usernames, password hashes, or its own login session. It connects to `1-Framework` shared identity using the project slug `daily-babe`.

| Central project role | Daily Babe access |
| --- | --- |
| Viewer | View assigned galleries and exports |
| Member | Viewer access plus uploads, deletions, and settings |
| Admin / Super Admin | All galleries plus baby creation and gallery membership management |

Create and maintain people, passwords, account status, and project roles in User Management. In Daily Babe, an admin assigns eligible central users to one or more baby galleries. The baby's name is gallery metadata, not a person's login name.

The first signed-in central administrator automatically registers the project, creates a fresh first gallery, and becomes able to configure access. Non-admin users must have both central Daily Babe project access and at least one local gallery assignment.

## Clean v2 cutover

Version 2 uses a new storage namespace and intentionally does not migrate the earlier test galleries:

```text
storage/central/
├── data/state.json
├── logs/
└── babies/<random-baby-id>/
    ├── data/settings.json
    ├── data/entries.json
    ├── uploads/
    └── backups/
```

Old runtime files under `storage/data/` and `storage/babies/` are ignored by v2. They may remain on Hostinger temporarily, but they are not read or exposed by the application. Hostinger's nightly backup provides recovery coverage during the cutover.

## Install and rollout

1. Deploy the repository so `daily-babe`, `user-management`, and `1-Framework` remain sibling folders.
2. Ensure PHP can write to `daily-babe/storage/` and the central User Management storage location.
3. Sign in through User Management with a central administrator account.
4. Open `https://jasr.me/github/daily-babe/` to register the project and initialize the first clean gallery.
5. In User Management, grant adults the appropriate `daily-babe` project role.
6. In Daily Babe Settings → Manage users, assign non-admin adults to baby galleries.
7. Verify sign-out, role enforcement, gallery isolation, and public sharing on Hostinger.

The due date powers the pre-birth countdown and the early/on-time/late message. Once an actual birth date is entered, that date controls Day 1, age, and missing-day calculations; the due date does not renumber photos.

## Gallery visibility

Choose a visibility setting for each baby in Settings:

| Setting | Who can view |
| --- | --- |
| Private | Assigned signed-in users only; public gallery and image links return 404. |
| Unlisted | Anyone with the opaque link who enters the baby’s name and birth date; absent from the public gallery list. |
| Public | Anyone with the readable first-name/birthday link; also listed at `public.php` with no sign-in or prompt. |

Public links keep this format:

```text
https://jasr.me/github/daily-babe/public.php?baby=hudson-11022026
```

Unlisted links use an opaque identifier that contains neither the baby’s name nor birth date. Opening one displays a prompt, and its photo endpoint remains unavailable until the visitor enters matching details in that browser session. The former name-and-birthday URL stops working when a gallery is unlisted. Public galleries retain the readable `firstname-MMDDYYYY` URL and open without a prompt.

The shared page is read-only. Visitors can select a photo to view its full-size original fitted within the screen, then press Escape or the X button to return to the gallery. Switching to Private immediately disables both the shared page and its image endpoint. It cannot remove copies that visitors saved while sharing was on.

## Backups and privacy

Runtime photos, central membership data, logs, and settings are ignored by Git. Hostinger performs a nightly backup, but a manual pre-rollout copy is still recommended before deleting old runtime folders. The included Apache rules deny browser access to `storage/` and `config/`.

## Missing-photo email reminders

Each gallery can enable one daily email after a selected time when that calendar day has no photo. Enter one or more recipient addresses in Settings. The reminder starts only after the actual birth date, and a private delivery ledger prevents duplicate messages for the same gallery and date.

Copy `config/config.example.php` to the ignored `config/config.php` on Hostinger and add the Titan mailbox password:

```php
'smtp' => [
    'host' => 'smtp.titan.email',
    'port' => 465,
    'encryption' => 'ssl',
    'authentication' => true,
    'username' => 'notifications@justjason.fyi',
    'password' => 'YOUR_TITAN_MAILBOX_PASSWORD',
    'from_email' => 'notifications@justjason.fyi',
    'from_name' => 'Daily Babe',
],
```

Do not commit `config/config.php`. Test delivery from the Hostinger terminal with:

```text
php /ABSOLUTE/PATH/github/daily-babe/send-missing-photo-reminders.php --test YOUR_EMAIL_ADDRESS
```

Hostinger PHP must have OpenSSL stream support enabled for the SSL connection on port 465.

Then create a Hostinger cron job that runs every 15 minutes:

```text
php /ABSOLUTE/PATH/github/daily-babe/send-missing-photo-reminders.php
```

The script uses the configured `America/New_York` timezone, checks only enabled galleries at or after their selected reminder time, and exits without sending when today already has a photo.

## Validation checklist

- Sign in through User Management and open Daily Babe without a second login.
- Confirm password changes occur only in User Management.
- Confirm a viewer cannot upload, delete, or change settings.
- Confirm a member can edit only an assigned gallery.
- Confirm an admin can add babies and manage assignments.
- Confirm an unassigned user receives a clear 403 response.
- Confirm private `image.php` requests require central access and gallery membership.
- Confirm public URLs work only while sharing is enabled.
- Confirm collage and GIF exports retain day labels.
- Enable a reminder, run the SMTP test, and confirm the scheduled check sends once when today is missing.
