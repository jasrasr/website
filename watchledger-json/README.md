<!--
File: README.md
Project: WatchLedger JSON
Description: Setup, usage, credentials, deployment, and architecture notes for the PHP/JSON watch tracker.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-02
Version: 0.1.0
-->

# WatchLedger JSON

WatchLedger is a mobile-first PHP/JSON web app for tracking TV shows and movies. It is designed for simple shared hosting and GitHub-friendly deployment.

It tracks what users want to watch, are watching, completed, dropped, rated, and noted. It does **not** stream TV shows or movies.

## Seed credentials

Change these immediately after upload if the site is public.

| Role | Username | Password | Purpose |
|---|---|---|---|
| Admin | `admin` | `admin123` | Manage other accounts. Does not track personal shows. |
| User | `testuser` | `testuser123` | Initial test user with sample library data. |

## Features included in v0.1.0

- JSON file storage with file locking and atomic writes.
- User registration and sign-in.
- Seeded admin account.
- Seeded test user account.
- Admin account can manage other users' lists.
- Admin account is blocked from tracking its own shows/movies.
- Mobile-first UI.
- Watchlist and status management.
- Ratings and notes.
- Last watched season/episode field for TV shows.
- Optional public list sharing per user.
- User-to-user connection request scaffolding.
- TMDB search endpoint with cache support.
- Manual add fallback when no TMDB API key is configured.
- `CHANGELOG.md` rendered from `changelog.php`.
- `TASKS.md` for restart-friendly development planning.
- `.placeholder` files in intentionally empty folders.
- `data/.htaccess` protection for JSON data.

## Installation

1. Upload the full folder contents to your PHP host.
2. Make sure PHP can write to the `data/` folder.
3. Open `login.php`.
4. Sign in with `admin` / `admin123` or `testuser` / `testuser123`.
5. Change the seeded passwords before using publicly.

## TMDB setup

TMDB search is optional. Manual add works without it.

1. Copy `includes/config.local.example.php` to `includes/config.local.php`.
2. Add your TMDB API key as `TMDB_API_KEY_LOCAL`.
3. Do not commit `includes/config.local.php` to GitHub.

The footer includes the required TMDB-style attribution text.

## JSON storage layout

```text
/data/
  accounts.json
  cache/
    tmdb/
  users/
    admin/
      profile.json
      connections.json
    testuser/
      profile.json
      library.json
      connections.json
      imports/
      uploads/
```

## Security notes

This is a starter project, not a finished production identity platform.

Recommended next hardening steps:

- Change the seed passwords.
- Force HTTPS.
- Keep `data/.htaccess` in place.
- Add password change and password reset workflows.
- Add admin-only account creation controls if public registration should be disabled.
- Add rate limiting for login and registration.
- Add file upload validation before enabling screenshot imports.
- Add backup/export before major edits.

## Import roadmap

Future import options should be added in this order:

1. CSV import.
2. JSON import/export using this app's schema.
3. Trakt import if desired.
4. Screenshot upload staging.
5. AI-assisted screenshot parsing review screen.
6. Final user confirmation before importing parsed items.

Screenshot import should never automatically write directly into a user's library. It should create a review queue first. OCR goblins are real, and they love turning `S1E8` into `51E8`.

## Deployment notes for GitHub

Do not commit real user data or secrets from a live site. For a public repo, keep seed/demo data only.

Suggested `.gitignore` additions for a live deployment branch:

```text
includes/config.local.php
data/accounts.json
data/users/*/library.json
data/users/*/profile.json
data/users/*/connections.json
data/cache/tmdb/*.json
data/**/*.lock
data/**/*.tmp.*
```

## Version

Current version: `0.1.0`
