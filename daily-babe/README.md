# Baby Daily Photo Tracker

A mobile-first PHP/JSON gallery for one baby photo per day. Daily Babe uses the site-wide User Management service for adult accounts and keeps baby galleries, photos, and metadata isolated inside the project.

## Included in v2.1.0

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
| Unlisted | Anyone with the first-name/birthday link; absent from the public gallery list. People who know those details can guess the link. |
| Public | Anyone with the link; also listed at `public.php` with no sign-in or prompt. |

Unlisted and public links use this format:

```text
https://jasr.me/github/daily-babe/public.php?baby=hudson-11022026
```

Existing galleries shared before v2.1.0 become unlisted, preserving their links. The shared page is read-only. Visitors can select a photo to open its full-size original in a new tab. Switching to Private immediately disables both the shared page and its image endpoint. It cannot remove copies that visitors saved while sharing was on. Changing the first name or birth date changes the shared link.

## Backups and privacy

Runtime photos, central membership data, logs, and settings are ignored by Git. Hostinger performs a nightly backup, but a manual pre-rollout copy is still recommended before deleting old runtime folders. The included Apache rules deny browser access to `storage/` and `config/`.

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
