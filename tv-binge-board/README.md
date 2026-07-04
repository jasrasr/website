<!--
File: README.md
Project: TV Binge Board
Description: Setup, usage, credentials, release packaging, tester attribution, PWA install support, PWA screenshot assets, search/add/import hub, direct screenshot image processing, episode display modes, next-up tracking, in-app update notices, and architecture notes for the PHP/JSON watch tracker.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.8
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

## User testing attribution

Matt served as an early user tester for TV Binge Board. His feedback directly shaped several usability passes, including Smart sorting for active shows, the Hide 100% / finished items filter, compact mobile list cards, moving long show descriptions to the detail page, progress rollback fixes, text-only episode display, checking for newly available episodes, and next-up/caught-up tracking.

## Features included through rev 1.5.8

- JSON file storage with file locking, atomic writes, and pre-overwrite restore points.
- User registration and sign-in.
- Admin account can create users and manage other users' lists.
- Admin account is blocked from tracking its own shows/movies.
- Mobile-first UI.
- Search page acts as the main add/import/upload hub.
- Search page includes TMDB search, manual add, CSV/JSON import upload, and screenshot upload.
- Smart default watchlist sort that prioritizes active/in-progress items.
- Hide 100% / finished items filter.
- Hide 100% / finished items also hides caught-up TV shows.
- Compact mobile list cards.
- Next-up/caught-up TV labels on compact list cards.
- Dedicated next-up/caught-up status card on TV detail pages.
- Picture-card and spoiler-safe text-only episode display modes.
- Check for new episodes action for TMDB-linked TV shows.
- CSV and JSON export.
- CSV import column-mapping screen for non-standard headers.
- CSV/JSON import staging review with duplicate detection.
- Direct screenshot image processing into review guesses when AI vision or local OCR is configured.
- Screenshot upload queue with confidence-scored manual approve/reject review before import staging.
- One-time in-app update notice when a deployed project revision changes.
- PWA manifest, explicit Apple touch icon files, JL-style app icon assets, app scope, app shortcuts, screenshot assets, offline fallback, install help page, and service-worker update reload prompt.
- PowerShell release ZIP helper for shared-hosting uploads.
- `CHANGELOG.md` rendered from `changelog.php`.
- `TASKS.md` with completed tasks retained for audit.
- `data/.htaccess` protection for JSON data.

## Seed credentials

Seed credentials are still present while the app is being tested and configured. Keep them only during testing, then change/remove them during the future security wrap-up before public use.

| Role | Username | Password | Purpose |
|---|---|---|---|
| Admin | configured seed admin | configured testing password | Manage other accounts. Does not track personal shows. |
| User | configured seed test user | configured testing password | Initial test user with sample library data. |

## Release packaging

Use the PowerShell release helper to build a clean ZIP before manually uploading files to shared hosting.

From inside the `tv-binge-board` folder:

```powershell
.\scripts\make-release-zip.ps1
```

Optional placeholder mode keeps `.placeholder` and protective `.htaccess` files from runtime folders without bundling live data:

```powershell
.\scripts\make-release-zip.ps1 -IncludePlaceholders -OpenFolder
```

The ZIP excludes runtime data, `includes/config.local.php`, cache files, logs, and generated release ZIPs. Keep the server's live `includes/config.local.php` and `data` folder in place when uploading a new release.

## PWA / Home Screen install

The app is PWA-compatible and has been polished for a more app-like Home Screen flow.

Included PWA pieces:

- `manifest.webmanifest` with `id`, `scope`, `start_url`, `orientation`, standalone display mode, categories, language, app shortcuts, icons, and screenshots.
- JL-style icon assets at `assets/icons/icon-jl-192.png` and `assets/icons/icon-jl-512.png`, matching the JasonLamb.me favicon/logo direction.
- Explicit Apple icon files at `assets/icons/apple-touch-icon.png` and `assets/icons/apple-touch-icon-180.png`.
- PWA screenshot assets at `assets/screenshots/pwa-mobile-list.svg`, `assets/screenshots/pwa-mobile-search-import.svg`, and `assets/screenshots/pwa-desktop-dashboard.svg`.
- Legacy icon files at `assets/icons/icon-192.png` and `assets/icons/icon-512.png` remain for fallback compatibility.
- `service-worker.js` shell cache.
- `offline.php` fallback page for offline navigation.
- `install.php` with iPhone/iPad, Android, and desktop install guidance.
- Dashboard card that links users to install instructions.
- Frontend install button support when the browser exposes a native install prompt.
- `New version available` reload prompt when a waiting service-worker update is detected.

Important limitations:

- Server-backed features still require network access.
- Offline mode only provides a controlled fallback and cached shell assets.
- iPhone Home Screen icons may not update automatically after changing the icon file; users may need to delete and re-add the Home Screen app.

## Search/add/import hub

For normal tracking users, `search.php` is the main intake page. It includes:

- TMDB search for adding a known show or movie with metadata.
- Manual add for quick placeholders or missing TMDB results.
- CSV/JSON import upload that submits into the existing import workflow.
- Screenshot upload that submits into the existing screenshot-assisted import queue.

The standalone `import.php` and `upload-screenshot.php` pages still exist for review, mapping, and queue detail work, but users can start all intake workflows from Search.

## In-app update notices

The app displays a browser-local update notice when the deployed project revision changes. The notice appears on the next page load after the user sees a new rev, then records that rev in local storage so it does not keep repeating.

The notice includes the current rev number, a brief update summary, a link to `changelog.php`, and a dismiss button. This is not a text, email, push, or account-level notification system.

## Episode display, next-up tracking, and new episodes

TV detail pages have two episode display modes:

- **Picture cards**: shows episode stills when available.
- **Text-only**: hides episode stills, makes the list more compact, and reduces spoiler risk from episode images.

Next-up tracking uses the user's watched episode records plus saved TMDB season/episode metadata. The app can show `Start`, `Next up`, `Caught up`, or `Likely next` depending on the saved episode data.

For TMDB-linked shows, the episode grid uses cached TMDB season metadata. Cached season data refreshes weekly when the detail page is viewed. The **Check for new episodes** button forces a metadata refresh immediately and updates cached season/episode information so newly available episodes appear in the grid.

## Direct screenshot image processing

Screenshot upload now attempts to process the uploaded image itself. The upload still does not write anything to the library. The flow is:

1. Upload a screenshot from Search or from `upload-screenshot.php`.
2. The server attempts direct image processing.
3. The resulting guesses are shown for manual review.
4. Approved guesses create a normal import review file.
5. Confirm the import review before anything is written to the library.

Direct image processing supports an optional AI vision path and a local OCR fallback when the server has OCR available. Configure the AI vision values in `includes/config.local.php`; see `includes/config.local.example.php` for the exact local constants.

If AI vision is not configured and local OCR is unavailable, the manual pasted text fallback remains available.

## TMDB setup

TMDB search and linking are optional. Manual add works without TMDB.

1. Copy `includes/config.local.example.php` to `includes/config.local.php`.
2. Add either `TMDB_API_READ_ACCESS_TOKEN_LOCAL` or `TMDB_API_KEY_LOCAL`. Prefer the read access token when available.
3. Do not commit `includes/config.local.php` to GitHub.

The app calls TMDB only from PHP on the server. Browser JavaScript calls local PHP endpoints, so the TMDB credential is not exposed to users.

## Import/export

Supported export formats:

- JSON
- CSV

Supported import formats:

- CSV with standard or custom headers.
- JSON using this app's `items` array structure, or a plain array of media items.

CSV imports use a mapping step before the review screen. Imports are staged first. Nothing is written into a library until the user confirms the import.

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

## Revision

Current project revision: `1.5.8`

Note: file header revisions are file-specific and should only be bumped when that file changes. New files should start with their own file revision instead of inheriting the project revision.
