<!--
File: README.md
Project: TV Binge Board
Description: Setup, usage, credentials, deployment, tester attribution, search/add/import hub, episode display modes, screenshot-assisted import, and architecture notes for the PHP/JSON watch tracker.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.0
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

Seed credentials are still present while the app is being tested and configured. Keep them only during testing, then change/remove them during the future security wrap-up before public use.

| Role | Username | Password | Purpose |
|---|---|---|---|
| Admin | configured seed admin | configured testing password | Manage other accounts. Does not track personal shows. |
| User | configured seed test user | configured testing password | Initial test user with sample library data. |

## User testing attribution

Matt served as an early user tester for TV Binge Board. His feedback directly shaped several rev 1.4.4 usability changes, including Smart sorting for active shows, the Hide 100% / finished items filter, compact mobile list cards, moving long show descriptions to the detail page, and the progress rollback fix when a season or episode is corrected.

Matt's later testing also shaped rev 1.5.0: a spoiler-safe text-only episode list, a more compact episode display, and a clearer way to check TMDB for newly available episodes.

## Features included through rev 1.5.0

- JSON file storage with file locking, atomic writes, and pre-overwrite restore points.
- User registration and sign-in.
- Admin account can create users and manage other users' lists.
- Admin account is blocked from tracking its own shows/movies.
- Site setting to enable/disable public registration.
- Search page acts as the main add/import/upload hub.
- Search page includes TMDB search, manual add, CSV/JSON import upload, and screenshot upload.
- Bottom navigation includes a visual overflow hint when more items are available by horizontal scrolling.
- Watchlist and status management.
- Smart default watchlist sort that prioritizes active/in-progress items.
- Hide 100% / finished items filter.
- Compact mobile list cards with full descriptions kept on the item detail page.
- Ratings and notes.
- Last watched season/episode field for TV shows.
- Manual last-episode rollback trims later watched episodes so completion percentage recalculates correctly.
- Per-item detail page.
- TV episode grid with watched/unwatched toggle.
- Picture-card and spoiler-safe text-only episode display modes.
- Check for new episodes action for TMDB-linked TV shows.
- TV completion percentage based on watched episodes and total episode count.
- Optional public list sharing per user.
- User-to-user connection requests.
- TMDB search endpoint with cache support.
- Live TMDB suggestions while typing in Search.
- TMDB poster/detail refresh action.
- TMDB metadata refresh action for newly available seasons/episodes.
- Local TMDB poster cache for linked movies and TV shows.
- Local season poster and episode still cache for TV shows.
- Preferred TMDB poster/backdrop picker for linked items.
- CSV and JSON export.
- CSV import column-mapping screen for non-standard headers.
- CSV/JSON import staging review with duplicate detection.
- Downloadable import error report for rows that cannot be staged.
- Screenshot upload queue for OCR/AI-assisted import text processing.
- Screenshot text parsing into confidence-scored show/movie guesses.
- Manual approve/reject screen before screenshot guesses become import review rows.
- `CHANGELOG.md` rendered from `changelog.php`.
- `TASKS.md` with completed tasks retained for audit.
- PWA icons and basic service worker.
- CLI backup helper: `tools/backup-data.php`.
- `.placeholder` files in intentionally empty folders.
- Unused artwork cleanup on delete and from the admin Site Settings page.
- `data/.htaccess` protection for JSON data.

## Search/add/import hub

For normal tracking users, `search.php` is the main intake page. It includes:

- TMDB search for adding a known show or movie with metadata.
- Manual add for quick placeholders or missing TMDB results.
- CSV/JSON import upload that submits into the existing import workflow.
- Screenshot upload that submits into the existing screenshot-assisted import queue.

The standalone `import.php` and `upload-screenshot.php` pages still exist for review, mapping, and queue detail work, but users should be able to start all intake workflows from Search.

## Episode display and new episodes

TV detail pages have two episode display modes:

- **Picture cards**: shows episode stills when available.
- **Text-only**: hides episode stills, makes the list more compact, and reduces spoiler risk from episode images.

For TMDB-linked shows, the episode grid uses cached TMDB season metadata. Cached season data refreshes weekly when the detail page is viewed. The **Check for new episodes** button forces a metadata refresh immediately and updates cached season/episode information so newly available episodes appear in the grid.

This does not automatically mark new episodes as watched. It only makes newly available episodes visible for tracking.

## Installation

1. Upload the full folder contents to your PHP host.
2. Make sure PHP can write to the `data/` folder.
3. Open `login.php`.
4. Sign in with the seeded testing accounts while still testing/configuring.
5. During the future security wrap-up, change/remove seeded credentials and review public registration.
6. Visit `admin/site-settings.php` to review public registration.

## TMDB setup

TMDB search and linking are optional. Manual add works without TMDB.

1. Copy `includes/config.local.example.php` to `includes/config.local.php`.
2. Add either `TMDB_API_READ_ACCESS_TOKEN_LOCAL` or `TMDB_API_KEY_LOCAL`. Prefer the read access token when available.
3. Do not commit `includes/config.local.php` to GitHub.

The app calls TMDB only from PHP on the server. Browser JavaScript calls local PHP endpoints, so the TMDB credential is not exposed to users.

TMDB integration currently supports:

- Search movie and TV results.
- Add search results with full details.
- Link an existing/manual item to TMDB.
- Refresh one linked item or all linked metadata.
- Open linked items on TMDB.
- Cache movie, TV, search, and season detail responses in `data/cache/tmdb/`.
- Use real TMDB season/episode metadata for the episode grid when available.
- Download TMDB artwork into `public-cache/posters/`, `public-cache/stills/`, and `public-cache/backdrops/` for local display.
- Choose preferred TMDB posters and backdrops from the item detail page.
- Fall back from episode still to season poster to show poster to placeholder.

The footer includes the required TMDB-style attribution text.

## Import/export

Export links are available from Settings for normal users and from Admin > Users for admins.

Supported export formats:

- JSON
- CSV

Supported import formats:

- CSV with standard or custom headers.
- JSON using this app's `items` array structure, or a plain array of media items.

CSV imports use a mapping step before the review screen. Imports are staged first. Nothing is written into a library until the user confirms the import. Duplicate rows are detected and skipped unless explicitly included.

## Screenshot-assisted import

`upload-screenshot.php` validates and stores screenshots in the user's protected data folder, then creates a review queue entry. The upload itself does not automatically add shows.

The screenshot flow is intentionally staged:

1. Upload a screenshot from Search or from `upload-screenshot.php`.
2. Process the screenshot into guesses using the available OCR/AI path.
3. Review each guess, edit the fields, and approve or reject it.
4. Approved guesses create a normal import review file.
5. Confirm the import review before anything is written to the library.

Server-side image AI extraction is the next required improvement so uploaded images can be processed directly without requiring users to paste OCR text manually.

## Backup

Run this from CLI if your PHP build has `ZipArchive`:

```bash
php tools/backup-data.php
```

Backups are written to `data/backups/` and exclude lock/temp files and previous backups.

Before an existing runtime JSON file is overwritten through the web app, the prior file is copied to `data/restore-points/YYYYMMDD-HHMMSS/` using the same relative path. The restore-point folder is ignored by Git and should stay on the server for recovery.

## Security notes

This is still a testing/configuration-stage project, not a finished production identity platform.

Future security wrap-up before public use:

- Rotate the testing credentials.
- Remove public testing credential details from public-facing documentation.
- Disable public registration or restrict it tightly unless intentionally public.
- Force HTTPS.
- Keep `data/.htaccess` in place.
- Add account recovery/reset-by-email before public launch.
- Add optional two-factor authentication if multiple people use it.
- Add upload safety scanning before allowing public screenshot uploads.
- Do not commit live user data, screenshots, or secrets.

## Deployment notes for GitHub

Do not commit real user data or secrets from a live site. Runtime JSON data is intentionally ignored and should stay on the server between deploys.

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
data/restore-points/*
public-cache/posters/*
public-cache/stills/*
public-cache/backdrops/*
data/**/*.lock
data/**/*.tmp.*
```

## Revision

Current project revision: `1.5.0`

Note: file header revisions are file-specific and should only be bumped when that file changes. New files should start with their own file revision instead of inheriting the project revision.
