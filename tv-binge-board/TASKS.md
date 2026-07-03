<!--
File: TASKS.md
Project: TV Binge Board
Description: Restart-friendly task list and implementation plan for continuing development on another device.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.1
-->

# TV Binge Board Task List

## Audit rule

Completed tasks stay in this file with `[x]` so the project history can be audited later. Do not remove completed tasks unless the wording itself reveals a sensitive security detail.

## Revision rule

The project has one overall revision in `APP_VERSION`, `CHANGELOG.md`, and the README revision note. Individual file header revisions are file-specific and should only change when that file changes. New files should start with their own file revision instead of inheriting the project revision.

## Current state

- [x] 2026-07-02 - Renamed project identity to TV Binge Board and slug to `tv-binge-board`.
- [x] PHP/JSON project scaffold created.
- [x] Mobile-first layout created.
- [x] Login/logout added.
- [x] User registration added.
- [x] Seeded administrator account added.
- [x] Seeded testing account added.
- [x] Admin does not track its own shows/movies.
- [x] Admin can manage normal user libraries.
- [x] User library JSON structure created.
- [x] Public sharing toggle added.
- [x] Connections scaffolding added.
- [x] Manual add added.
- [x] TMDB search endpoint added.
- [x] Changelog viewer added.
- [x] README added.
- [x] `.placeholder` files added.

## rev 1.2.0 development pass

- [x] Add password change page.
- [x] Add admin reset-password action.
- [x] Add admin disable/enable user action.
- [x] Add option to disable public registration.
- [x] Add CSV export for each user's library.
- [x] Add JSON export for each user's library.
- [x] Add CSV import review screen.
- [x] Add duplicate detection during import.
- [x] Add poster refresh action using TMDB ID.
- [x] Add per-season episode grid for TV shows.
- [x] Add completed percentage for TV shows.
- [x] Add search/filter/sort on `watchlist.php`.
- [x] Add user profile avatars.
- [x] Add PWA icons.
- [x] Add backup script for the `data/` folder.

## rev 1.3.0 TMDB integration pass

- [x] Add server-side TMDB read-access-token support.
- [x] Keep TMDB credentials out of browser JavaScript.
- [x] Add external TMDB links for linked items.
- [x] Fetch full TMDB details when adding from search.
- [x] Add link-to-TMDB workflow for existing manual items.
- [x] Add richer TMDB metadata fields: release date, genres, vote average/count, runtime, homepage, and TV status.
- [x] Add TMDB season details cache.
- [x] Add TMDB-backed TV episode grid with episode titles and air dates.
- [x] Store episode title and air date when toggling watched episodes.
- [x] Add refresh-all TMDB metadata action for linked library items.
- [x] Update CSV export with TMDB URL and metadata columns.
- [x] Add UI to pick preferred poster/backdrop images.
- [x] Add scheduled/one-click stale cache cleanup.

## rev 1.4.0 local artwork pass

- [x] Add browser-visible local artwork cache folder.
- [x] Cache main TMDB posters locally when adding or linking items.
- [x] Add local poster refresh when refreshing TMDB metadata.
- [x] Add item-level local artwork cache/refresh API.
- [x] Add force-refresh option for artwork when TMDB images change.
- [x] Cache TMDB season posters locally.
- [x] Cache TMDB episode stills locally when requested.
- [x] Display episode stills in the episode grid.
- [x] Add fallback chain for missing episode images.
- [x] Keep runtime poster/still files out of GitHub while preserving folders.
- [x] Add UI to choose alternate TMDB poster/backdrop images.
- [x] Add stale artwork cleanup tool.

## rev 1.4.2 maintenance pass

- [x] Standardize headers and display labels to `rev 1.4.2`.
- [x] Remove unused local artwork automatically after deleting a media item.
- [x] Add admin-only unused artwork cleanup endpoint.
- [x] Add Site Settings button to remove orphaned cached artwork.
- [x] Keep completed cleanup tasks visible for audit.

## rev 1.4.4 Matt testing/mobile list cleanup pass

- [x] Add Smart sort so currently watching/in-progress items float higher on My List.
- [x] Add Hide 100% / finished items filter.
- [x] Replace tall list cards with compact mobile cards on My List.
- [x] Keep long descriptions on the detail page instead of the list page.
- [x] Fix last-episode rollback so lowering progress removes later watched episode records.
- [x] Bump visible revision and service worker cache to `rev 1.4.4`.

## rev 1.4.5 poster/backdrop selection pass

- [x] Add `artwork.php` picker for TMDB-linked items.
- [x] Show current poster and backdrop on the artwork picker.
- [x] List alternate TMDB posters with vote/size metadata.
- [x] List alternate TMDB backdrops with vote/size metadata.
- [x] Add `api/select-artwork.php` to save the preferred poster/backdrop choice.
- [x] Cache selected posters locally.
- [x] Cache selected backdrops locally in `public-cache/backdrops/`.
- [x] Add item detail link to choose poster/backdrop.
- [x] Add `.gitignore` and `.placeholder` support for backdrop cache files.
- [x] Update `README.md` for `rev 1.4.5`.
- [x] Update `CHANGELOG.md` for `rev 1.4.5`.

## rev 1.4.6 import mapping pass

- [x] Add CSV column-mapping screen before import review.
- [x] Add automatic header guesses for common odd CSV headers.
- [x] Add first-rows CSV preview on the mapping screen.
- [x] Add downloadable import error report for rows that cannot be staged.
- [x] Bump visible revision and service worker cache to `rev 1.4.6`.
- [x] Update `README.md` for `rev 1.4.6`.
- [x] Update `CHANGELOG.md` for `rev 1.4.6`.

## rev 1.4.7 attribution/documentation pass

- [x] Add Matt user testing attribution to README.
- [x] Add Matt attribution to the rev 1.4.4 changelog items that came from his testing feedback.
- [x] Bump visible project revision and service worker cache to `rev 1.4.7`.

## rev 1.4.8 screenshot-assisted import review pass

- [x] Add OCR/AI text processing outside the core library save path.
- [x] Parse pasted OCR/AI text into show/movie guesses.
- [x] Display parsed guesses with confidence levels.
- [x] Add manual approve/reject screen for screenshot guesses.
- [x] Approved screenshot guesses create a normal import review file.
- [x] Keep library writes behind the existing import review confirmation step.
- [x] Document project-level vs file-level revision rules.

## rev 1.4.9 search/add/import hub pass

- [x] Make Search the main intake hub for normal tracking users.
- [x] Add TMDB search, manual add, CSV/JSON import upload, and screenshot upload sections to Search.
- [x] Keep CSV/JSON upload routed through the existing import mapping/review workflow.
- [x] Keep screenshot upload routed through the existing screenshot queue/review workflow.
- [x] Add bottom navigation overflow hint so users can tell there are more nav items to the right.
- [ ] Add direct image processing for uploaded screenshots so the upload itself can produce guesses.

## rev 1.5.0 Matt episode list / new episode pass

- [x] Add Picture cards / Text-only episode display toggle on item detail pages.
- [x] Add spoiler-safe text-only episode mode to hide episode still images.
- [x] Make text-only mode more compact for mobile episode lists.
- [x] Persist the episode display preference in a browser cookie.
- [x] Add Check for new episodes action for TMDB-linked TV shows.
- [x] Add `api/refresh-metadata.php` to force-refresh item, season, and episode metadata.
- [x] Show last metadata check timestamp on the item detail page.
- [x] Update README and changelog for Matt's new testing feedback.

## rev 1.5.1 in-app update notice pass

- [x] Add one-time on-screen update notice when the deployed rev changes.
- [x] Show current rev number and a brief update summary in the notice.
- [x] Add direct changelog link from the notice.
- [x] Store the last seen rev in browser local storage so the notice does not keep repeating.
- [x] Add dismiss button.
- [x] Bump visible revision and service worker cache to `rev 1.5.1`.

## Import plan

- [x] Create `import.php` page.
- [x] Accept `.csv` and `.json` uploads into `data/users/{username}/imports/`.
- [x] Parse imports into a temporary review JSON file.
- [x] Show parsed rows in a review UI.
- [x] Detect duplicates before confirmation.
- [x] Require final confirmation before importing.
- [x] Write import activity log with timestamp and item count.
- [x] Add custom column-mapping UI for odd CSV headers.
- [x] Add downloadable import error report.

## Screenshot-assisted import plan

- [x] Create `upload-screenshot.php` page.
- [x] Store screenshots in `data/users/{username}/uploads/`.
- [x] Add image validation: extension, MIME type, file size, dimensions.
- [x] Create review queue JSON file.
- [x] Require manual approval before any future screenshot import writes data.
- [x] Keep original screenshot attached to import history for audit/debugging.
- [x] Add OCR/AI processing outside the core save path.
- [x] Display parsed guesses with confidence levels.
- [x] Add manual approve/reject screen for screenshot guesses.
- [ ] Add direct image processing from the uploaded screenshot itself.

## Security hardening

- [ ] Future security wrap-up: rotate testing values after testing/configuration is complete.
- [ ] Future security wrap-up: review public-facing setup documentation before public use.
- [ ] Future security wrap-up: disable public registration or restrict it tightly before public use.
- [x] Add login rate limiting.
- [x] Add password change flow.
- [x] Add stronger session cookie settings for HTTPS.
- [x] Add activity log for admin changes.
- [x] Add recurring/manual JSON backup helper.
- [x] Add automatic pre-overwrite JSON restore points.
- [ ] Add server-side upload safety scanning if this becomes public/multi-user.
- [ ] Add account recovery/reset-by-email workflow.
- [ ] Add optional two-factor authentication.

## Future enhancements

- [x] Add true TMDB season/episode metadata instead of even-split episode grid.
- [x] Add import column mapping UI.
- [ ] Add structured parsing or fuzzy matching service integration.
- [ ] Add friend activity feed.
- [ ] Add list comparison between connected users.
- [ ] Add tags/custom lists.
- [ ] Add better deployment script for Hostinger.
- [ ] Add optional scheduled metadata refresh for all actively watched TMDB-linked shows.

## Pause/resume checklist

When resuming on another device:

1. Read `README.md`.
2. Open `CHANGELOG.md` or `changelog.php`.
3. Review this file.
4. Confirm whether `includes/config.local.php` exists on the target server.
5. Confirm that `data/.htaccess` is uploaded.
6. Confirm administrator user management works.
7. Confirm normal user manual add works.
8. Test export/import with a small CSV.
9. Add TMDB key only after the core app loads correctly.
10. Before public use, complete the security-wrap-up tasks above.
