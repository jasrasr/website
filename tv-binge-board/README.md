<!--
File: README.md
Project: TV Binge Board
Description: Setup, usage, credentials, deployment, and architecture notes for the PHP/JSON watch tracker.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-02
Revision: 1.4.2
-->

# TV Binge Board

TV Binge Board is a mobile-first PHP/JSON web app for tracking TV shows and movies on simple shared hosting. It is built for the `jasr.me`/Hostinger style workflow: upload PHP files, keep JSON data local, and avoid a database until the app grows enough to deserve one.

It tracks what users want to watch, are watching, completed, dropped, rated, imported, and noted. It does **not** stream TV shows or movies.

Recommended folder/URL slug:

```text
tv-binge-board
```

Example hosted URL:

```text
https://jasr.me/github/tv-binge-board/
```

## Seed credentials

Change these immediately after upload if the site is public.

| Role | Username | Password | Purpose |
|---|---|---|---|
| Admin | `admin` | `admin123` | Manage other accounts. Does not track personal shows. |
| User | `testuser` | `testuser123` | Initial test user with sample library data. |

## Features included through rev 1.4.2

- Project renamed to TV Binge Board with `tv-binge-board` as the folder/URL slug.
- JSON file storage with file locking and atomic writes.
- User registration and sign-in.
- Login failure rate limiting.
- User password change page.
- Seeded admin account.
- Seeded test user account.
- Admin account can manage other users' lists.
- Admin account is blocked from tracking its own shows/movies.
- Admin reset-password action.
- Admin disable/enable user action.
- Site setting to enable/disable public registration.
- Admin activity log.
- Mobile-first UI.
- Watchlist and status management.
- Ratings and notes.
- Last watched season/episode field for TV shows.
- Per-item detail page.
- Episode grid with watched/unwatched toggle.
- TV completion percentage based on watched episodes and total episode count.
- Search/filter/sort on the watchlist.
- Optional public list sharing per user.
- User-to-user connection requests.
- User avatar URL support.
- TMDB search endpoint with cache support.
- TMDB poster/detail refresh action.
- Local TMDB poster cache for linked movies and TV shows.
- Local season poster and episode still cache for TV shows.
- Per-item local artwork refresh and force-refresh actions.
- TMDB external links for linked movies and TV shows.
- Existing manual item link-to-TMDB workflow.
- Richer TMDB metadata: genres, ratings, release dates, runtime, homepage, and season summaries.
- TMDB-backed TV season/episode grids with episode titles and air dates when available.
- Batch refresh for all linked TMDB items in a library.
- Manual add fallback when no TMDB API key is configured.
- CSV and JSON export.
- CSV/JSON import staging review with duplicate detection.
- Screenshot upload queue for future OCR/AI-assisted import.
- `CHANGELOG.md` rendered from `changelog.php`.
- `TASKS.md` with completed tasks retained for audit.
- PWA icons and basic service worker.
- CLI backup helper: `tools/backup-data.php`.
- `.placeholder` files in intentionally empty folders.
- Unused artwork cleanup on delete and from the admin Site Settings page.
- `data/.htaccess` protection for JSON data.

## Installation

1. Upload the full folder contents to your PHP host.
2. Make sure PHP can write to the `data/` folder.
3. Open `login.php`.
4. Sign in with `admin` / `admin123` or `testuser` / `testuser123`.
5. Change the seeded passwords before using publicly.
6. Visit `admin/site-settings.php` to review public registration.

## TMDB setup

TMDB search and linking are optional. Manual add works without TMDB.

1. Copy `includes/config.local.example.php` to `includes/config.local.php`.
2. Add either `TMDB_API_READ_ACCESS_TOKEN_LOCAL` or `TMDB_API_KEY_LOCAL`. Prefer the read access token when available.
3. Do not commit `includes/config.local.php` to GitHub.

The app calls TMDB only from PHP on the server. Browser JavaScript calls the local `api/search-tmdb.php` endpoint, so the TMDB credential is not exposed to users.

TMDB integration currently supports:

- Search movie and TV results.
- Add search results with full details.
- Link an existing/manual item to TMDB.
- Open linked items on TMDB.
- Refresh one linked item.
- Refresh all linked items in a library.
- Cache movie, TV, search, and season detail responses in `data/cache/tmdb/`.
- Use real TMDB season/episode metadata for the episode grid when available.
- Download TMDB artwork into `public-cache/posters/` and `public-cache/stills/` for local display.
- Fall back from episode still to season poster to show poster to placeholder.

The footer includes the required TMDB-style attribution text.

## Local artwork cache

TMDB-linked items now prefer local artwork when available. The app keeps TMDB metadata in JSON, but downloads browser-visible images into:

```text
/public-cache/
  posters/
  stills/
```

Artwork behavior:

- New TMDB-linked movies and shows cache the main poster locally when added or linked.
- `Refresh TMDB details/poster` refreshes metadata and downloads the current poster.
- `Cache local artwork` on an item downloads the show/movie poster, season posters, and episode stills when TMDB has them.
- `Force refresh artwork` re-downloads artwork even when a local file already exists.
- Episode images fall back in this order: local episode still, TMDB episode still URL, local season poster, TMDB season poster URL, show/movie poster, placeholder.

Downloaded poster/still files are runtime cache files and should not be committed to GitHub. The folders stay in source control through `.placeholder` files.

## JSON storage layout

```text
/data/
  accounts.json
  settings.json
  activity-log.json
  login-attempts.json
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

## Import/export

Export links are available from Settings for normal users and from Admin > Users for admins.

Supported export formats:

- JSON
- CSV

Supported import formats:

- CSV with headers such as `title`, `type`, `status`, `rating`, `season`, `episode`, `notes`, `overview`
- JSON using this app's `items` array structure, or a plain array of media items

Imports are staged first. Nothing is written into a library until the user confirms the import. Duplicate rows are detected and skipped unless explicitly included.

## Screenshot-assisted import

`upload-screenshot.php` validates and stores screenshots in the user's protected data folder, then creates a review queue entry. It does not run OCR yet and it does not automatically add shows.

That is intentional. Screenshot OCR should create guesses, then require manual approval before touching the library. OCR goblins are real, and they love turning `S1E8` into `51E8`.

## Backup

Run this from CLI if your PHP build has `ZipArchive`:

```bash
php tools/backup-data.php
```

Backups are written to `data/backups/` and exclude lock/temp files and previous backups.

## Security notes

This is still a starter project, not a finished production identity platform.

Recommended next hardening steps:

- Change the seed passwords.
- Force HTTPS.
- Keep `data/.htaccess` in place.
- Disable public registration if this is a personal/family app.
- Add account recovery/reset-by-email before public launch.
- Add optional two-factor authentication if multiple people use it.
- Add malware scanning before allowing public screenshot uploads.
- Do not commit live user data, screenshots, or secrets.

## Deployment notes for GitHub

Do not commit real user data or secrets from a live site. For a public repo, keep seed/demo data only.

Suggested `.gitignore` additions for a live deployment branch:

```text
includes/config.local.php
data/accounts.json
data/settings.json
data/activity-log.json
data/login-attempts.json
data/users/*/library.json
data/users/*/profile.json
data/users/*/connections.json
data/users/*/imports/*
data/users/*/uploads/*
data/cache/tmdb/*.json
data/backups/*.zip
public-cache/posters/*
public-cache/stills/*
data/**/*.lock
data/**/*.tmp.*
```

## Revision

Current revision: `1.4.2`


## Artwork cache cleanup

Cached posters and episode stills are stored under `public-cache/` only after media is added to a user library or explicitly refreshed. Search results do not create local artwork files.

When a library item is deleted, the app runs unused-artwork cleanup. Admins can also run cleanup manually from **Admin → Site Settings → Remove unused artwork**. Cleanup keeps files still referenced by any tracked user library item or by cached season metadata for a tracked TV show, then removes orphaned poster/still files.
