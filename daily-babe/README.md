# Baby Daily Photo Tracker

A mobile-first PHP/JSON gallery for one baby photo per day. Daily Babe uses the site-wide User Management service for adult accounts and keeps baby galleries, photos, and metadata isolated inside the project.

## Included in v2.0.1

- Central User Management sign-in, password changes, sessions, and account status
- Multiple babies and multiple adults, with per-gallery membership
- Central `viewer`, `member`, and `admin` project roles
- Daily or backdated JPEG, PNG, and WebP uploads
- One photo per calendar date
- Editable due date, actual birth date, birth time, length to hundredths of an inch, and weight
- Day numbering based on actual birth date when present
- Missing-day and streak tracking
- Collage and animated GIF exports with a day label on every image
- Optional fully open, read-only public gallery
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

## Public gallery

Public access is off by default for every baby. After entering the baby’s first name and actual birth date, enable **Make this gallery fully open and public** in Settings. The link uses this format:

```text
https://jasr.me/github/daily-babe/public.php?baby=hudson-11022026
```

The public page is read-only. Turning sharing off immediately disables both the public page and its image endpoint. Changing the first name or birth date changes the public link.

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
