<!--
File: TASKS.md
Project: TV Binge Board
Description: Restart-friendly task list and implementation plan for continuing development on another device.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-02
Revision: 1.4.2
-->

# TV Binge Board Task List

## Audit rule

Completed tasks stay in this file with `[x]` so the project history can be audited later. Do not remove completed tasks unless the wording itself reveals a sensitive security detail.

## Current state

- [x] 2026-07-02 - Renamed project identity to TV Binge Board and slug to `tv-binge-board`.
- [x] PHP/JSON project scaffold created.
- [x] Mobile-first layout created.
- [x] Login/logout added.
- [x] User registration added.
- [x] Seeded `admin` account added.
- [x] Seeded `testuser` account added.
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
- [ ] Add UI to pick preferred poster/backdrop images.
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
- [ ] Add UI to choose alternate TMDB poster/backdrop images.
- [x] Add stale artwork cleanup tool.

## rev 1.4.2 maintenance pass

- [x] Standardize headers and display labels to `rev 1.4.2`.
- [x] Remove unused local artwork automatically after deleting a media item.
- [x] Add admin-only unused artwork cleanup endpoint.
- [x] Add Site Settings button to remove orphaned cached artwork.
- [x] Keep completed cleanup tasks visible for audit.

## Import plan

- [x] Create `import.php` page.
- [x] Accept `.csv` and `.json` uploads into `data/users/{username}/imports/`.
- [x] Parse imports into a temporary review JSON file.
- [x] Show parsed rows in a review UI.
- [x] Detect duplicates before confirmation.
- [x] Require final confirmation before importing.
- [x] Write import activity log with timestamp and item count.
- [ ] Add custom column-mapping UI for odd CSV headers.
- [ ] Add downloadable import error report.

## Screenshot-assisted import plan

- [x] Create `upload-screenshot.php` page.
- [x] Store screenshots in `data/users/{username}/uploads/`.
- [x] Add image validation: extension, MIME type, file size, dimensions.
- [x] Create review queue JSON file.
- [x] Require manual approval before any future screenshot import writes data.
- [x] Keep original screenshot attached to import history for audit/debugging.
- [ ] Add OCR/AI processing outside the core save path.
- [ ] Display parsed guesses with confidence levels.
- [ ] Add manual approve/reject screen for screenshot guesses.

## Security hardening

- [ ] Change seed passwords after deployment.
- [x] Add login rate limiting.
- [x] Add password change flow.
- [x] Add stronger session cookie settings for HTTPS.
- [x] Add activity log for admin changes.
- [x] Add recurring/manual JSON backup helper.
- [x] Add automatic pre-overwrite JSON restore points.
- [ ] Add server-side upload malware scanning if this becomes public/multi-user.
- [ ] Add account recovery/reset-by-email workflow.
- [ ] Add optional two-factor authentication.

## Future enhancements

- [x] Add true TMDB season/episode metadata instead of even-split episode grid.
- [ ] Add import column mapping UI.
- [ ] Add friend activity feed.
- [ ] Add list comparison between connected users.
- [ ] Add tags/custom lists.
- [ ] Add better deployment script for Hostinger.

## Pause/resume checklist

When resuming on another device:

1. Read `README.md`.
2. Open `CHANGELOG.md` or `changelog.php`.
3. Review this file.
4. Confirm whether `includes/config.local.php` exists on the target server.
5. Confirm that `data/.htaccess` is uploaded.
6. Sign in as `admin` and confirm user management works.
7. Sign in as `testuser` and confirm manual add works.
8. Test export/import with a small CSV.
9. Add TMDB key only after the core app loads correctly.
