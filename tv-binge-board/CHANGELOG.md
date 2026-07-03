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

Older entries are available in Git history before rev 1.5.0.
