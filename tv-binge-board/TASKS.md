<!--
File: TASKS.md
Project: TV Binge Board
Description: Restart-friendly task list and implementation plan for continuing development on another device.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-03
Revision: 1.5.7
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

## Completed revision passes

- [x] rev 1.2.0 - account controls, import/export, episode grid, PWA icons, backups.
- [x] rev 1.3.0 - richer TMDB metadata, TMDB links, season/episode metadata, refresh-all metadata.
- [x] rev 1.4.0 - local artwork cache, poster/still cache, refresh artwork, fallback artwork chain.
- [x] rev 1.4.2 - project rename cleanup, rev labels, unused artwork cleanup.
- [x] rev 1.4.4 - Matt mobile list feedback: Smart sort, Hide 100% / finished, compact cards, progress rollback.
- [x] rev 1.4.5 - poster/backdrop picker.
- [x] rev 1.4.6 - CSV import mapping and downloadable import error report.
- [x] rev 1.4.7 - Matt tester attribution in docs/changelog.
- [x] rev 1.4.8 - screenshot-assisted import review from OCR/AI text.
- [x] rev 1.4.9 - Search as add/import/upload hub and bottom-nav overflow hint.
- [x] rev 1.5.0 - Matt episode list feedback: picture/text toggle and check for new episodes.
- [x] rev 1.5.1 - one-time in-app update notice.
- [x] rev 1.5.2 - Matt next-up/caught-up tracking.
- [x] rev 1.5.3 - PWA polish and install/offline support.
- [x] rev 1.5.4 - JL favicon/app icon asset update.
- [x] rev 1.5.5 - explicit Apple icon/cache-bust pass.
- [x] rev 1.5.6 - direct screenshot image processing.

## rev 1.5.7 PWA screenshot asset pass

- [x] Add `assets/screenshots/pwa-mobile-list.svg`.
- [x] Add `assets/screenshots/pwa-mobile-search-import.svg`.
- [x] Add `assets/screenshots/pwa-desktop-dashboard.svg`.
- [x] Add manifest `screenshots` entries with narrow and wide form factors.
- [x] Add the screenshot assets to the service worker cache list.
- [x] Bump visible revision and service worker cache to `rev 1.5.7`.

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
- [x] Add direct image processing from the uploaded screenshot itself.

## PWA plan

- [x] Add baseline manifest and app icons.
- [x] Register service worker.
- [x] Add manifest scope, id, orientation, display override, and shortcuts.
- [x] Add offline fallback page.
- [x] Add install/help page.
- [x] Add visible install card.
- [x] Add service-worker update reload prompt.
- [x] Update 192px and 512px PWA icon assets to match the JL favicon/logo direction.
- [x] Add explicit Apple touch icon files.
- [x] Add new icon filenames to avoid iOS caching the old icon URL.
- [x] Add screenshot assets for richer PWA install surfaces.

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
