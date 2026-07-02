<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-02
Revision: 1.4.3
-->

# Changelog

## rev 1.4.3 - 2026-07-02

- Added guest-facing registration entry points and admin-created user accounts.
- Added a signed-in logout link to the bottom navigation.
- Added live TMDB search suggestions while typing on the Search page.
- Bumped the visible app revision and service worker cache to refresh browser assets.
- Added versioned CSS/JS asset URLs so browsers load current frontend code after deploys.
- Stopped tracking runtime JSON account, settings, activity, login, profile, library, and connection data so deploys do not overwrite live users.


## rev 1.4.2 - 2026-07-02

- Renamed the project from WatchLedger to TV Binge Board.
- Updated the folder/URL slug recommendation to `tv-binge-board`.
- Updated app constants, session name, manifest, service worker cache name, export filenames, backup filename prefix, docs, and seeded JSON metadata to match the new name.


## rev 1.4.1 - 2026-07-02

- Standardized project revision labels to `rev 1.4.1` and removed `0.x.x` version labels.
- Updated the visible app header from `v` to `rev`.
- Added automatic unused-artwork cleanup after a media item is deleted.
- Added admin-only `api/cleanup-artwork.php` maintenance endpoint.
- Added a Site Settings action to remove orphaned cached posters and episode stills.
- Artwork cleanup keeps images referenced by any tracked user library item or tracked TV season cache.

## rev 1.4.0 - 2026-07-02

- Added browser-visible local artwork cache under `public-cache/`.
- Added local TMDB poster caching when adding, linking, or refreshing linked items.
- Added season poster and episode still caching for TV shows.
- Added `api/refresh-artwork.php` for item-level and library-level local artwork refreshes.
- Added force-refresh support for artwork when TMDB images change.
- Added episode still display in the TV episode grid.
- Added fallback chain from local episode still to season poster to show poster to placeholder.
- Updated watched episode records to preserve still-path/local-still references for audit and display.
- Updated `.gitignore` to keep downloaded artwork out of source control while retaining cache folders.


## rev 1.3.0 - 2026-07-02

- Added TMDB read access token support in addition to v3 API-key fallback.
- Added external TMDB links for linked movies and TV shows.
- Added full TMDB detail enrichment when adding items from search.
- Added manual item link-to-TMDB workflow.
- Added richer TMDB metadata fields: genres, release date, vote average/count, runtime, homepage, TV status, and season summaries.
- Added TMDB season detail cache.
- Replaced the even-split TV grid with TMDB-backed episode metadata when available.
- Added episode title and air date storage when marking episodes watched.
- Added refresh-all TMDB metadata action for linked library items.
- Updated CSV export with TMDB URL and release metadata columns.

## rev 1.2.0 - 2026-07-02

- Added password change workflow for signed-in users.
- Added admin password reset action.
- Added admin disable/enable user action.
- Added site setting to enable/disable public registration.
- Added login failure rate limiting.
- Added stronger session cookie settings.
- Added activity log for admin and account-changing actions.
- Added CSV and JSON export.
- Added CSV/JSON import staging review with duplicate detection.
- Added screenshot upload staging queue for future OCR/AI import work.
- Added TMDB detail refresh action for posters and show metadata.
- Added per-item detail page.
- Added per-season episode grid with watched/unwatched toggles.
- Added TV completion percentage based on watched episodes and total episode count.
- Added watchlist search, status filter, type filter, and sorting.
- Added user avatar URL support with fallback initials.
- Added PWA icons and a basic service worker cache.
- Added CLI-only `tools/backup-data.php` backup helper.
- Updated `TASKS.md` to keep completed tasks for audit.

## rev 1.1.0 - 2026-07-02

- Created initial PHP/JSON mobile-first watch tracker.
- Added JSON storage helpers with file locking and atomic saves.
- Added user authentication with seeded `admin` and `testuser` accounts.
- Added admin-only user management and user library management.
- Blocked admin accounts from personal show/movie tracking.
- Added user registration.
- Added manual media add workflow.
- Added optional TMDB search endpoint and cache folder.
- Added watch status, ratings, notes, and TV episode progress fields.
- Added public sharing toggle per user.
- Added basic user connection request/accept/decline workflow.
- Added `changelog.php` to render this file.
- Added `TASKS.md` to make the project easy to resume later.
- Added `README.md` with setup, credentials, security notes, and import roadmap.
- Added `.placeholder` files where empty folders are expected.
