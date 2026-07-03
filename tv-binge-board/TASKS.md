<!--
File: TASKS.md
Project: TV Binge Board
Description: Restart-friendly task list and implementation plan for continuing development on another device.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.0
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
- [x] User sign-in and registration added.
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

## rev 1.4.4 Matt testing/mobile list cleanup pass

- [x] Add Smart sort so currently watching/in-progress items float higher on My List.
- [x] Add Hide 100% / finished items filter.
- [x] Replace tall list cards with compact mobile cards on My List.
- [x] Keep long descriptions on the detail page instead of the list page.
- [x] Fix last-episode rollback so lowering progress removes later watched episode records.

## rev 1.4.5 poster/backdrop selection pass

- [x] Add `artwork.php` picker for TMDB-linked items.
- [x] Show current poster and backdrop on the artwork picker.
- [x] List alternate TMDB posters with vote/size metadata.
- [x] List alternate TMDB backdrops with vote/size metadata.
- [x] Add `api/select-artwork.php` to save the preferred poster/backdrop choice.
- [x] Cache selected posters locally.
- [x] Cache selected backdrops locally in `public-cache/backdrops/`.
- [x] Add item detail link to choose poster/backdrop.

## rev 1.4.6 import mapping pass

- [x] Add CSV column-mapping screen before import review.
- [x] Add automatic header guesses for common odd CSV headers.
- [x] Add first-rows CSV preview on the mapping screen.
- [x] Add downloadable import error report for rows that cannot be staged.

## rev 1.4.7 attribution/documentation pass

- [x] Add Matt user testing attribution to README.
- [x] Add Matt attribution to the rev 1.4.4 changelog items that came from his testing feedback.

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
10. Before public use, complete the security wrap-up tasks.
