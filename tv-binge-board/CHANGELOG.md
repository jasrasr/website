<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.3
-->

# Changelog

## rev 1.5.3 - 2026-07-03

- Polished PWA support for a more app-like Home Screen experience.
- Added `id`, `scope`, `orientation`, `display_override`, `categories`, language, and app shortcuts to `manifest.webmanifest`.
- Added `install.php` with iPhone/iPad, Android, and desktop install guidance.
- Added `offline.php` as a service-worker navigation fallback.
- Improved the service worker to cache shell assets intentionally and avoid treating every dynamic PHP page as a cache target.
- Added user-triggered service-worker update activation with a `New version available` reload prompt.
- Added dashboard Install / Add to Home Screen card.
- Added dynamic iOS/mobile web app meta tags from the frontend script.
- Updated the one-time update notice summary for rev 1.5.3.
- Bumped the visible project revision and service worker cache to 1.5.3.

## rev 1.5.2 - 2026-07-03

- Added Matt-requested Next up / Caught up episode status for TV tracking.
- Added `includes/next-up.php` to calculate the next available unwatched episode from watched episode records and TMDB metadata.
- List cards now show `Next up: SxEy`, `Start: S1E1`, `Caught up`, or `Likely next` when metadata is incomplete.
- Item detail pages now show a dedicated next-up/caught-up status card.
- Hide 100% / finished items now also hides caught-up TV shows.
- Smart sort now pushes caught-up shows below shows with available next episodes.
- Updated the one-time in-app update notice summary for rev 1.5.2.
- Bumped the visible project revision and service worker cache to 1.5.2.

## rev 1.5.1 - 2026-07-03

- Added a one-time in-app update notice that appears when the deployed project revision changes.
- The notice shows the current rev number, a brief summary, and a direct link to the changelog.
- The notice is browser-local and uses local storage; it does not send text or email notifications.
- The notice appears on the next page load after a new deployed revision is seen, then records that rev so it does not keep repeating.
- Added dismiss support for users who do not want to open the changelog.
- Bumped the visible project revision and service worker cache to 1.5.1.

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

Older entries are available in Git history before rev 1.4.4.
