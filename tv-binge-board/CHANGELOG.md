<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.0
-->

# Changelog

## rev 1.5.0 - 2026-07-03

- Added Matt-requested episode display controls on the item detail page.
- Added a Picture cards / Text-only toggle for TV episode lists.
- Added spoiler-safe text-only episode mode to hide episode still images and compact the episode list.
- Added a Check for new episodes action for TMDB-linked TV shows.
- Added `api/refresh-metadata.php` to force-refresh TMDB item, season, and episode metadata so newly available episodes appear in the episode grid.
- Added a visible timestamp for the last new-episode metadata check.
- Updated the episode metadata note to explain that cached TMDB season data refreshes weekly on view and can be refreshed immediately with the new button.
- Bumped the visible project revision and service worker cache to 1.5.0.

## rev 1.4.9 - 2026-07-03

- Made Search the main add/import/upload hub for normal tracking users.
- Added quick anchors on Search for TMDB search, manual add, CSV/JSON import, and screenshot upload.
- Added a CSV/JSON upload form directly on Search that submits into the existing import review workflow.
- Added a screenshot upload form directly on Search that submits into the existing screenshot queue.
- Added a bottom navigation overflow hint so users can tell more nav items are available by horizontal scrolling.
- Bumped the visible project revision and service worker cache to 1.4.9.

## rev 1.4.8 - 2026-07-03

- Added OCR/AI text processing for screenshot-assisted imports outside the core library save path.
- Added confidence-scored guesses parsed from pasted screenshot OCR/AI text.
- Added manual approve/reject review for screenshot guesses before they become import review rows.
- Approved screenshot guesses now create a normal import review file; nothing is written to the library until that import review is confirmed.
- Added screenshot queue status updates for needs-processing, needs-review, and review-created states.
- Added screenshot review UI styling.
- Documented that project revision and file header revision are separate: file headers should change only when that specific file changes.

## rev 1.4.7 - 2026-07-03

- Added tester attribution for Matt in the README.
- Added explicit changelog attribution noting that Matt's user testing directly informed several rev 1.4.4 usability changes.
- Bumped documentation and visible app revision references to 1.4.7 for this attribution/documentation update.

## rev 1.4.6 - 2026-07-02

- Added a CSV column-mapping step before import review.
- Added automatic header guesses for common CSV column names.
- Added mapping support for title, type, status, rating, season, episode, notes, overview, year, TMDB ID, release date, poster fields, and TMDB URL.
- Added a first-rows preview table on the mapping screen.
- Added downloadable CSV import error reports for rows that cannot be staged.
- Bumped the visible app revision and service worker cache to 1.4.6.
- Updated README and TASKS for the import mapping pass.

## rev 1.4.5 - 2026-07-02

- Added a TMDB poster/backdrop picker for linked media items.
- Added `artwork.php` to preview current artwork and select alternate TMDB posters or backdrops.
- Added `api/select-artwork.php` to save selected artwork and cache the selected image locally.
- Added `public-cache/backdrops/` for selected backdrop images while keeping runtime image files out of GitHub.
- Added a Choose poster/backdrop action from the item detail page.
- Updated README and task planning notes for the future security wrap-up.

## rev 1.4.4 - 2026-07-02

- Added a default Smart sort on My List that puts actively watching items first and pushes finished/dropped items lower. Suggested by Matt during user testing.
- Added a Hide 100% / finished items filter for testers who want to see only items still in progress. Suggested by Matt during user testing.
- Replaced the full watchlist cards with compact mobile cards that keep long descriptions on the detail page. Suggested by Matt during user testing.
- Fixed manual last-episode rollback so lowering the selected episode trims later watched episodes and recalculates progress. Reported by Matt during user testing.
- Bumped the visible app revision to 1.4.4.
- Added compact-card CSS and refreshed the service worker cache name so mobile browsers pick up the new layout.

## rev 1.4.3 - 2026-07-02

- Added guest-facing registration entry points and admin-created user accounts.
- Added a signed-in logout link to the bottom navigation.
- Added live TMDB search suggestions while typing on the Search page.
- Bumped the visible app revision and service worker cache to refresh browser assets.
- Added versioned CSS/JS asset URLs so browsers load current frontend code after deploys.
- Stopped tracking runtime JSON account, settings, activity, login, profile, library, and connection data so deploys do not overwrite live users.
- Added automatic restore-point backups before existing runtime JSON files are overwritten.


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

- Added TMDB token support in addition to key fallback.
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
- Added stronger session cookie settings for HTTPS.
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
- Added authentication with seeded accounts.
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
