<!--
File: README.md
Project: TV Binge Board
Description: Setup, usage, credentials, persistent login, release packaging, tester attribution, PWA install support, social sharing, friend activity feed, search/add/import hub, direct screenshot image processing, episode display modes, next-up tracking, automatic new-episode refresh, in-app update notices, and architecture notes for the PHP/JSON watch tracker.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-05
Revision: 1.5.16
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

Matt served as an early user tester for TV Binge Board. His feedback directly shaped several usability passes, including Smart sorting for active shows, the Hide 100% / finished items filter, compact mobile list cards, moving long show descriptions to the detail page, progress rollback fixes, text-only episode display, checking for newly available episodes, next-up/caught-up tracking, clearer watched-state indicators, and prior-episode marking prompts.

## Features included through rev 1.5.16

- JSON file storage with file locking, atomic writes, and pre-overwrite restore points.
- User registration and sign-in.
- Persistent Remember Me login with hashed server-side tokens and one-year cookies.
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
- Automatic lazy refresh for stale tracked TMDB-linked TV shows.
- New seasons/newly aired episodes become available for next-up tracking without marking them watched.
- Picture-card and spoiler-safe text-only episode display modes.
- Check for new episodes action for TMDB-linked TV shows.
- Watched episode checkmarks, explicit unmarking, and optional prior-episode/prior-season marking prompts.
- Public sharing and mutual connection requests.
- Friend activity feed on the Connections page for visible connected/public-list activity.
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

## Persistent login

The login form includes a checked-by-default **Keep me signed in on this device** option.

When enabled:

- The browser receives a one-year `HttpOnly`, `Secure` when HTTPS is active, `SameSite=Lax` remember-me cookie.
- The raw remember token is never stored in JSON.
- `data/remember-tokens.json` stores only a selector, username, token hash, timestamps, expiration, and user-agent hash.
- If the PHP session expires but the remember cookie is valid, the app recreates the session automatically.
- The token rotates when it is used.
- Logout revokes the current remember token.
- Password changes and admin password resets revoke saved remember tokens for that account.

The login can still be lost if the browser clears website data, cookies are blocked, private browsing is used, or the account is disabled.

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
