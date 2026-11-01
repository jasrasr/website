<!--
File Name     : ALL-TODOS.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Repository-wide compilation of the project-folder TODO files.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Compiled verified folder-level TODO lists.
-->
# Website project TODOs

This snapshot concatenates the 48 project-folder TODO files. Update the relevant folder TODO first, then refresh this compilation. The root TODO.md is local-only and is not included. The empty CVC-Youth-Scoreboard, jurassic-terminal, and just-jason placeholders have no project source or folder TODO to include.

## 1-Framework

Source: [1-Framework/TODO.md](1-Framework/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for 1-Framework.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Shared Framework TODO

## Documented plans

- [ ] Finish the foundation items in [ROADMAP.md](ROADMAP.md): configuration loading, error handling, JSON storage with locking, API responses, and validation.
- [ ] Extract logging and audit modules with schemas, retention, and request IDs.
- [ ] Establish shared frontend components and a blank-project scaffold with upgrade and rollback guidance.

## Code-informed follow-up

- [ ] Reconcile the roadmap's still-open JSON/API/authentication entries with the existing `JsonStore`, `Response`, `PasswordSession`, and `SharedIdentity` implementations; split unfinished behavior from completed foundations before closing checklist items.

## ai-writing-tool

Source: [ai-writing-tool/TODO.md](ai-writing-tool/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for ai-writing-tool.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# AI Writing Tool TODO

## Code-informed follow-up

- [ ] Handle `localStorage` read/write failures and quota limits in `assets/app.js`; show a visible save failure so users do not assume a draft was saved.
- [ ] Add a manual draft export/import path for moving browser-local writing between devices, with clear overwrite confirmation.
- [ ] Add regression checks for API failure, rate limiting, and local draft recovery before changing the editor or proxy.

## availability

Source: [availability/TODO.md](availability/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for availability.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Availability TODO

## Code-informed follow-up

- [ ] Add automated browser coverage for attendee-added date controls across organizer, invite, and protected-event modes; the README currently provides a manual matrix for these flows.
- [ ] Keep the response-recovery browser suite in the project validation gate so fresh-session recovery, legacy links, and blocked storage remain covered when the UI changes.

## blog

Source: [blog/TODO.md](blog/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for blog.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Blog TODO

## Documented maintenance

- [ ] Run the automated-voice and revision-history checks from [EDITORIAL.md](EDITORIAL.md) whenever project-series posts are edited or synced.
- [ ] Refresh the media usage index with `tools/Build-Blog.ps1` when the admin dashboard reports "USAGE STALE" ([README.md](README.md)).

## Code-informed follow-up

- [ ] Add a repeatable check that the generated index, RSS feed, and sitemap agree on published post URLs after a build.

## box

Source: [box/TODO.md](box/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for box.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# QR Box Inventory TODO

## Documented release work

- [ ] Run and record the functional, QR, PDF, security, recovery, and hosting checks in [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md); unchecked entries remain unverified.
- [ ] Confirm the documented restore procedure and `RESTORE.md` exist before marking the documentation gate complete.
- [ ] Verify actual production requirements and the release tag only after all checklist gates pass.

## build-signal

Source: [build-signal/TODO.md](build-signal/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for build-signal.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Build Signal TODO

## Documented production follow-up

- [ ] Render and review the four-voice Studio worksheet using [FOUR-VOICE-UPLOAD.md](FOUR-VOICE-UPLOAD.md), including voice assignments, spoken-cue cleanup, and the actual duration.
- [ ] Compare the exported conversation against the script before calling the script a verbatim transcript; transcribe the separate sign-off recording if its text is shown on the page.

## Code-informed follow-up

- [ ] Add a practical local test command to `package.json` for the existing Playwright suite.

## computers

Source: [computers/TODO.md](computers/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for computers.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Computer Heartbeat TODO

## Documented future enhancements

- [ ] Add a stale-device filter and CSV export to the dashboard.
- [ ] Add per-device charts and a mobile-friendly dashboard layout.
- [ ] Evaluate offline push notices and a bulk API mode after the current single-device flow is covered by validation.

See [readme.md](readme.md) for the original optional enhancement list.

## construction-sign

Source: [construction-sign/TODO.md](construction-sign/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for construction-sign.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Construction Sign TODO

## Code-informed follow-up

- [ ] Add a small browser validation checklist for one-to-four-line wrapping, alignment, PNG output, and both clipboard exports; these are the documented features in [README.md](README.md).
- [ ] Verify that the self-contained HTML and embed exports render the same sign as the live preview after future changes to the LED renderer.

## countdown

Source: [countdown/TODO.md](countdown/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for countdown.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Countdown TODO

## Code-informed follow-up

- [ ] Decide whether this historical integration countdown should remain an archive or accept a configurable target; `index.html` still uses the August 10, 2026 target and now displays the arrived state.
- [ ] If reused, parse a target with an explicit time zone and display that zone so the countdown does not vary with the viewer's browser locale.

## custom-directory

Source: [custom-directory/TODO.md](custom-directory/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for custom-directory.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Custom Directory TODO

## Code-informed follow-up

- [ ] Tighten the download handler's root-path boundary check: a plain string-prefix comparison can accept a sibling path whose name begins with the document root.
- [ ] Add focused validation for traversal, sibling-prefix paths, and allowed nested downloads before modifying `download.php`.
- [ ] Document backup and recovery of favorites and download telemetry stored under the project directory.

## daily-babe

Source: [daily-babe/TODO.md](daily-babe/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for daily-babe.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Daily Babe TODO

## Documented next work

- [ ] Replace or delete an incorrect dated photo with confirmation and audit history; `api.php` currently rejects duplicate dates.
- [x] Add separate sign-ins for authorized family members and admin-managed gallery access.
- [ ] Add encrypted backup/export of originals and metadata.
- [ ] Evaluate server-generated thumbnails for long galleries.

## Documented later ideas

- [ ] Explore face-alignment guidance, time-lapse export, milestone tags, a home-screen PWA, and configurable comparison views.

See [ROADMAP.md](ROADMAP.md) for the original scope and ordering.

## debt-payoff

Source: [debt-payoff/TODO.md](debt-payoff/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Future improvements for the debt payoff planner.
Revision      : 1.0.0
Created Date  : 2026-07-25
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Added metadata and clarified existing backup scope and validation needs.
-->
# Future Upgrades

The items below are proposed enhancements. The current app already has snapshot backup and restore; the encrypted export and deployment options remain separate future work.

## Data and Modeling
- Add recurring extra-payment calendars instead of a single annual blue moon payment.
- Add adjustable interest-rate schedules for teaser APRs, HELOC resets, and ARM mortgage changes.
- Add escrow, taxes, insurance, and dynamic minimum-payment formulas for credit cards and mortgages.
- Add import and export for CSV, JSON, and spreadsheet formats.

## User Experience
- Add payoff charts, balance history, and printable payoff reports.
- Add filters, archive mode, and closed-loan history.
- Add due-date reminders and statement-date tracking.
- Add optional two-factor authentication and email-based password reset.

## Admin and Deployment
- Add audit logging for admin actions.
- Add configurable password policy and forced password rotation for the sample account.
- Add database-backed storage for multi-server deployments.
- Extend the existing backup/restore flow with encrypted export options for user data.

## Code-informed follow-up

- Add regression checks for rate-schedule and recurring-payment calculations before changing the amortization model.
- Define how imported loans and changed interest schedules interact with the existing audit and snapshot restore flows.

## dell-service-tag

Source: [dell-service-tag/TODO.md](dell-service-tag/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for dell-service-tag.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Dell Service Tag TODO

## Code-informed follow-up

- [ ] Decide whether the shared tag history in `api.php` should be limited to authorized users; current GET and POST routes have no access check.
- [ ] Add a retention or clear-history control if the shared 100-tag list should be manageable by users.
- [ ] Verify concurrent-write and failed-write behavior for the JSON history store.

## demo

Source: [demo/TODO.md](demo/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for demo.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Demo Screenshots TODO

## Code-informed follow-up

- [ ] Refresh the September 2026 desktop and mobile screenshots when the linked public pages change; record the capture date and viewport in [README.md](README.md).
- [ ] Add a lightweight check that every screenshot listed in the README exists and that desktop/mobile pairs are complete.

## family-tracker

Source: [family-tracker/todo.md](family-tracker/todo.md)

<!--
File Name     : todo.md
Revision      : 1.6.13
Project Revision Reference: 1.6.12
Description   : Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author        : Jason Lamb (with help from ChatGPT)
Created Date  : 2026-07-06
Modified Date : 2026-10-20
Changelog     :
  Rev 1.6.13 - Rechecked outstanding security and PWA items against current source and added validation follow-up.
  Rev 1.6.12 - Recorded completed owner member-management and prior feature work.
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.6.12**

## Completed in Rev 1.6.12

- [x] Add an owner option to permanently delete a member's account (not just remove them from the group).
- [x] Add owner-assisted password reset for a member.

## Completed in Rev 1.6.4

- [x] Add calculated routing/ETA through an external routing provider.
- [x] Add richer background/offline location guidance.
- [x] Add a map overlay showing configured geofence circles.

## Completed in Rev 1.6.3

- [x] Add optional generated avatars or profile pictures.
- [x] Add member-controlled nickname/profile preferences.
- [x] Add a dedicated account/profile page.

## Completed in Rev 1.6.1

- [x] Add geofence editing rather than delete/recreate only.
- [x] Add per-place arrival-notification toggles.
- [x] Add per-place departure-notification toggles.
- [x] Keep geofence transitions in the audit trail even when notices are disabled.
- [x] Show the notification mode on each place card.

## Completed in Rev 1.6.0

- [x] Move the map and live member information to the top of the tracker.
- [x] Add compact navigation for Map, Members, Sharing, Groups, Account, More, History, and Owner tools.
- [x] Move secondary tools into collapsed sections.

## Completed previously

- [x] Delete-my-account safeguards, active-group export, privacy summary, and status banners.
- [x] Multi-group support, owner dashboard, member management, maps, diagnostics, and account security.
- [x] Expiring and maximum-use managed invites.
- [x] Guarded group deletion.
- [x] PWA install support, offline status, appearance modes, and service-worker caching.
- [x] Login throttling, consent review, and security/audit cleanup.
- [x] Geofence zones and arrival/departure notices.
- [x] Member-list flicker fix and reduced background UI polling.

## Next reasonable batches

### Security and privacy

- [ ] Add Content Security Policy after CDN and map testing.

### PWA and UI

- [ ] Add loading skeletons.
- [ ] Add a PNG icon set for broader home-screen compatibility.
- [ ] Evaluate offline location-update queuing; do not queue sensitive writes without conflict handling.

### Validation

- [ ] Add browser checks for installed-app fallback, offline messaging, and reconnect behavior before changing the service worker or location flow.

## Implementation notes

- Keep revision numbers in the 1.x+ pattern.
- Any new live-data directory must include a `.placeholder` file.
- New PHP, JavaScript, CSS, and markdown files must include revision headers.
- Avoid hardcoded administrator credentials.
- Keep the automatic location-permission request on launch unless explicitly changed later.
- Preserve the mobile map fallback because iPhone browser map behavior remains fragile.

## file-manager

Source: [file-manager/TODO.md](file-manager/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the file manager.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# File Manager TODO

## Code-informed improvements

- [ ] Add automated coverage for upload validation, version naming, rate limits, and admin MFA. These flows are implemented in `api/upload.php` and `admin/`, but this folder has no test suite.
- [ ] Add a documented recovery procedure for the writable `uploads/`, `data/`, and `logs/` directories, including how versioned files are restored without overwriting newer uploads. See `README.md` and the directory layout.

## finances

Source: [finances/TODO.md](finances/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the budget tracker.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Finances TODO

## Code-informed improvements

- [ ] Add tests for monthly and annual projections, especially mixed expense frequencies and withholding. Calculations live in `index.php`; `AGENTS.md` confirms there is no automated test framework here.
- [ ] Document backup and restore for each user's JSON budget file without exposing private data. See `README.md` and `lib/Accounts.php`.

## fs

Source: [fs/TODO.md](fs/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the Freshservice dashboard and review tool.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented review limits and follow-up work.
-->
# Freshservice Tracker TODO

## Documented limitation

- [ ] Partition large ticket histories by date in the private review collector. `review/README.md` says histories at or above the 10,000-result ceiling currently fail visibly; preserve that failure behavior until complete pagination is verified.
- [ ] Design bounded review batches or resumable progress for large queues. `review/README.md` notes that sequential AI requests can exceed shared-host request timeouts; do not show partial queues as complete.

## Code-informed improvement

- [ ] Add a regression case for collector coverage at the result ceiling and interrupted batches in `tests/` before changing the review collector.

## game-buzzer

Source: [game-buzzer/TODO.md](game-buzzer/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the game buzzer.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Game Buzzer TODO

## Code-informed improvements

- [ ] Provide a host option to close active room links after a game. `README.md` states existing host links remain bearer capabilities after central logout or revocation; document the effect on connected leaders before implementing.
- [ ] Add a rehearsal checklist covering two projector views, leader phones, chime unlock, survey close, and score reset. These workflows are documented in `README.md` but currently require manual coordination.

## gps-eta

Source: [gps-eta/TODO.md](gps-eta/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the GPS and ETA tracker.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured open on-page requests and a stale note.
-->
# GPS ETA TODO

## Documented possibilities

- [ ] Add saved minute-offset presets and trip comparison if useful. `index-secure.php` lists both as remaining non-map enhancements.
- [ ] Evaluate route-distance and traffic-aware ETA only with a chosen directions provider; the current tracker explicitly uses GPS distance without road routing (`README.md`, `index-secure.php`).

## Documentation correction

- [ ] Reconcile the old future-calculations note in `index.php` and `index-secure.php` with `CHANGELOG.md`: pause count and last GPS step already exist, while the note still lists them as future calculations.

## how-much-time-worked

Source: [how-much-time-worked/TODO.md](how-much-time-worked/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the timeclock photo logger.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Timeclock Photo Logger TODO

## Code-informed improvements

- [ ] Add fixtures for overnight shifts, duplicate shifts, and OCR transcription errors. `README.md` describes these paths, but the folder has no automated tests.
- [ ] Document retention and restore for uploaded slips and the JSON work log. `README.md` identifies both writable stores without an operational recovery procedure.

## human-proof

Source: [human-proof/TODO.md](human-proof/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the press-and-hold gate.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Human Proof TODO

## Documented option

- [ ] Decide whether to use the documented optional Turnstile integration for actual bot resistance. `README.md` distinguishes the interaction from a full bot-protection service.

## Code-informed improvement

- [ ] Add browser checks for pointer, touch, keyboard hold, early release, reduced motion, and server-mode redirect; both modes are described in `README.md`, but this folder has no tests.

## jeremy

Source: [jeremy/TODO.md](jeremy/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Public-safe follow-up work for the trip map pages.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a public-safe, code-informed backlog.
-->
# Jeremy Trip Map TODO

## Code-informed improvements

- [ ] Review whether the historical trip pages should remain published and remove or generalize location details if they are no longer intended for public viewing. `index.html` and `map.html` contain embedded route and checkpoint content.
- [ ] Choose one maintained page between `index.html` and `index-map-replacement.html`, then document which is canonical. Both are present as separate full-page implementations.

## khootish

Source: [khootish/TODO.md](khootish/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Curated open work from the Khootish roadmap.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Summarized open roadmap work without duplicating completed features.
-->
# Khootish TODO

`ROADMAP.md` is the detailed backlog; `TASKLIST.md` contains a shorter overlapping list. Keep both synchronized as features ship.

## Documented priorities

- [ ] Add avatar selection, configurable question duration, and configurable scoring. See `ROADMAP.md` Next Up; the current `README.md` still describes a fixed 30-second timer.
- [ ] Add quiz duplication, deletion with confirmation, question ordering, and JSON import/export. See `ROADMAP.md` Quiz Management; quiz editing is already marked completed there.
- [ ] Add admin CSRF protection, rate limits for joins and answers, and damaged-JSON recovery before expanding public usage. See `ROADMAP.md` Reliability and Administration.
- [ ] Add keyboard and screen-reader support for host controls and timer updates. See `ROADMAP.md` Mobile and Accessibility.

## license-plate

Source: [license-plate/TODO.md](license-plate/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the photo logger.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# License Plate Photo Logger TODO

## Code-informed improvements

- [ ] Add test fixtures for duplicate files, repeated plates, scanner failures, and pending-entry reprocessing. `README.md` documents these flows; the folder has no automated tests.
- [ ] Document retention and restore for uploaded photos, active entries, and deleted-entry audit data. Relevant code exists in `process_upload.php`, `delete_entry.php`, and `deleted_audit.php`.

## life-group-attendance

Source: [life-group-attendance/TODO.md](life-group-attendance/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for attendance management.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented approval gap and test follow-up.
-->
# Life Group Attendance TODO

## Documented gap

- [ ] Consider approval notifications or a clear manual notification workflow. `README.md` says no email is sent when a leader account is approved.

## Code-informed improvement

- [ ] Add server-side regression tests for pending-account access, concurrent registration and admin writes, and soft-delete history preservation. `README.md` documents these behaviors; `tests/` currently contains a browser UI regression test only.

## linktree

Source: [linktree/TODO.md](linktree/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Planned implementation work for the link landing page.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Transcribed the existing README plan into an actionable backlog.
-->
# Linktree TODO

## Documented plan

- [ ] Build the landing page entry point, styles, scripts, and any needed assets listed in `README.md`; this folder currently contains only that README.
- [ ] Define the link content source and mobile/accessibility checks before publishing the page. `README.md` lists intended files but does not specify content or validation.

## magic-word

Source: [magic-word/TODO.md](magic-word/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the terminal demo.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Magic Word TODO

## Code-informed improvements

- [ ] Add interaction checks for three failed commands, auto demo, reset, sound toggle, and dialog close. All behavior currently lives in the single `index.html` file without tests.
- [ ] Add focus management and Escape-key handling for the alert dialog. `index.html` uses `role="dialog"` and a close button, but does not currently move focus into the dialog or handle Escape.

## mpg

Source: [mpg/TODO.md](mpg/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Curated open work from the MPG roadmap and changelog.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Summarized remaining planned work without duplicating completed features.
-->
# MPG TODO

`ROADMAP.md` is the detailed source of truth. Its completed full/partial-fill, station-profile, GPS, and map work is intentionally omitted here.

## Documented priorities

- [ ] Add recent-station ordering and search to saved-station selection (`ROADMAP.md`, `CHANGELOG.md` Planned).
- [ ] Add duplicate station-profile merge tooling with clear handling of existing fuel-log references (`ROADMAP.md`).
- [ ] Warn when photo-derived brand and nearby-station brand disagree; keep explicit user confirmation authoritative (`ROADMAP.md`).
- [ ] Define and display station-identification confidence without treating AI/GPS/EXIF suggestions as confirmed data (`ROADMAP.md`).

## odometer

Source: [odometer/TODO.md](odometer/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the odometer demo.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Odometer TODO

## Code-informed improvements

- [ ] Add checks for start, stop, reset, unit switching, and a browser tab becoming hidden. Animation state is managed in `script.js`, with no project tests.
- [ ] Add a reduced-motion path for flip and gauge animations. `style.css` defines animations but contains no `prefers-reduced-motion` rule.

## oregon-trail

Source: [oregon-trail/TODO.md](oregon-trail/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified remaining work for the offline browser game.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured remaining documented work and validation.
-->
# Oregon Trail TODO

## Documented remaining work

- [ ] Add a full per-journey event timeline. `README.md` says current saves retain only the latest event, so plan a backward-compatible save migration.
- [ ] Design buying and trading actions for stops. `README.md` and `CHANGELOG.md` list these as future work.
- [ ] Verify phone focus/scroll in portrait and landscape, plus airplane-mode reload on a real device. `README.md` explicitly records these checks as still needed.

## psnotify

Source: [psnotify/TODO.md](psnotify/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the notification viewer.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented push option and test follow-up.
-->
# PSNotify TODO

## Documented option

- [ ] Evaluate real background Web Push if notifications must arrive when the viewer is closed. `README.md` says the current page is a polling viewer and Web Push is a later upgrade.

## Code-informed improvement

- [ ] Add endpoint tests for publish authentication, request limits, and feed access. `publish.php`, `fetch.php`, and `common.php` implement these flows; this folder has no tests.

## random-password-generator

Source: [random-password-generator/TODO.md](random-password-generator/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for password generator prototypes.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Random Password Generator TODO

## Code-informed improvements

- [ ] Select and document one maintained entry point. `testing/` has multiple numbered prototypes and `working/` has another page; the folder has no root index or README.
- [ ] Remove or clearly label insecure `Math.random()` generator prototypes before publishing a canonical password generator. `testing/pw-generator.html` and `testing/pw-generator1.html` use `Math.random()` while later versions use `crypto.getRandomValues()`.
- [ ] Test required-character minimums, impossible settings, clipboard copy, and zero/maximum lengths in the selected implementation.

## random-url

Source: [random-url/TODO.md](random-url/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for static redirect/QR pages.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Random URL TODO

## Code-informed improvements

- [ ] Document the purpose and destination behavior of the numbered static pages before publishing or regenerating them. `0.html` through `9.html` and `create-qr-files.ps1` have no README.
- [ ] Make the generator explicit about its output directory and avoid overwriting existing numbered pages without confirmation. `create-qr-files.ps1` currently runs `Set-Content` for pages 3 through 9.

## scoreboard

Source: [scoreboard/TODO.md](scoreboard/TODO.md)

<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified open work for scoreboard instances.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Reconciled the legacy feature note with implemented code.
-->
# Scoreboard TODO

## Existing feature-request status

The two requests in `FEATURE-TODO.md` are verified as complete and marked there. They are not open work.

## Code-informed improvement

- [ ] Add a test that Frontlines shows the top three plus ties, while other instances retain their full team lists. `tests/viewer-team-limit-test.js` covers the Frontlines limit, and `README.md` says other instances are unaffected.

## smart-404

Source: [smart-404/TODO.md](smart-404/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Smart 404.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected documented migration work and code review suggestions.
-->

# Smart 404 TODO

## Documented work

- [ ] Plan and implement shared-identity access for `smart-404-admin.php` while keeping public missing-page handling available; preserve live mapping, malicious-pattern, and request-log files during rollout. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestions

- [ ] Add automated checks for exact/manual mappings, malicious pattern precedence, and fuzzy redirect decisions in `smart-404.php` before changing redirect rules.
- [ ] Review the deployment procedure for `create-password-hash.php` and remove or block the temporary helper after administrator setup; the README currently instructs operators to keep it protected.

## testing

Source: [testing/TODO.md](testing/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the testing redirect project.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded code review suggestions for the CVC redirect.
-->

# Testing TODO

## Code review suggestions

- [ ] Decide whether `CVC-Youth-Scoreboard/index.php` still needs redirect-hit logging; if it does, move the log outside the public web root or deny direct HTTP access and set a retention policy. The current script appends request metadata to a file beside the public redirect.
- [ ] Document the redirect's purpose and retirement criteria; this folder currently has no project-level README, and the child README contains temporary notes.

## text

Source: [text/TODO.md](text/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Shared Text.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected documented migration work and code review suggestions.
-->

# Shared Text TODO

## Documented work

- [ ] Decide who may load text, save changes, and view raw JSON, then migrate the password gates on both `index.php` and `index-rev2.php` to explicit shared-identity permissions. Preserve existing data and verify write authorization and CSRF. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestions

- [ ] Choose one supported entry point or make the alternate revision a non-deployed archive; both PHP pages currently contain independent copies of the save/load implementation.
- [ ] Add a restore path for the ten stored snapshots, or document the manual restore procedure in the README; history files are written and pruned, but no user-facing restore flow is documented.

## ticketing

Source: [ticketing/TODO.md](ticketing/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified backlog for the Ticketing application.
Revision: 1.1.0
Created Date: 2026-09-14
Modified Date: 2026-10-20
Changelog: 1.1.0 - Marked category management complete and clarified current security migration priorities.
-->

# Ticketing Future Features / TODO

## Current priorities
- [ ] Verify the deployed `TEST_MODE` setting and remove password-free sign-in as part of the per-project shared-identity migration. Preserve requester ticket ownership and agent-only private notes. Source: `user-management/MIGRATION-TRACKER.md`; the current `index.php` still bypasses password verification in test mode.
- [ ] Apply CSRF validation to every state-changing endpoint. `categories.php` already checks a token for its forms, so audit the remaining `index.php` and `csv.php` routes rather than treating category management as unfinished.
- [ ] Add focused tests for requester/agent access, ticket ownership, and private-note filtering before changing authentication.

## Authentication and account management
- [x] Requester login.
- [x] Agent login.
- [x] Requester self-registration.
- [x] First-agent bootstrap.
- [x] Persistent requester/agent directory.
- [ ] Password reset / forgot-password workflow.
- [ ] Agent password reset by administrator.
- [ ] Disable/enable requester and agent accounts.
- [ ] Change password while signed in.
- [ ] MFA / passkey support.
- [ ] Login throttling and account lockout protection.
- [ ] CSRF protection for write operations.
- [ ] Microsoft 365 / Entra ID single sign-on.
- [ ] Role levels such as Agent, Supervisor, Admin, and Read Only.

## Email integration
- [ ] Outbound email notifications when tickets are created, assigned, replied to, resolved, or reopened.
- [ ] Email requester when an agent sends a public reply.
- [ ] Email assigned agent/group when a ticket changes.
- [ ] Configurable sender address and SMTP/API provider.
- [ ] Inbound email-to-ticket creation.
- [ ] Convert email replies into public ticket replies.
- [ ] Preserve email thread/message IDs to reduce duplicate tickets.
- [ ] Email templates and per-event notification settings.
- [ ] Agent notification preferences.

## Ticket workflow
- [ ] Departments and agent groups.
- [x] Configurable categories and subcategories (`categories.php` and runtime category JSON).
- [ ] Ticket types: Incident, Service Request, Problem, Change, Question.
- [ ] Custom fields.
- [ ] Tags.
- [ ] Due dates.
- [ ] SLA policies by priority/type/category.
- [ ] Business hours and holiday schedules.
- [ ] Escalation rules.
- [ ] Auto-assignment / round-robin routing.
- [ ] Ticket watchers / CC users.
- [ ] Merge duplicate tickets.
- [ ] Split tickets.
- [ ] Parent/child tickets.
- [ ] Related tickets.
- [ ] Reopen closed/resolved tickets.
- [ ] Closure reason / resolution code.
- [ ] Ticket source options such as Portal, Email, Phone, Teams, Monitoring, API.
- [ ] Approval workflow.

## Replies and collaboration
- [ ] Canned responses.
- [ ] @mentions for agents.
- [ ] Rich-text replies.
- [ ] Reply templates/signatures.
- [ ] Internal activity timeline separate from conversation.
- [ ] Track every field change in an audit log.
- [ ] Show who changed assignment/status/priority and when.
- [ ] Draft replies.

## Attachments
- [ ] Requester attachments when creating or replying to a ticket.
- [ ] Agent attachments in replies/private notes.
- [ ] File type and size restrictions.
- [ ] Virus/malware scanning integration.
- [ ] Attachment cleanup/retention policy.

## Reporting and dashboards
- [ ] Tickets created/resolved by day, week, and month.
- [ ] Open backlog trend.
- [ ] Average first-response time.
- [ ] Average resolution time.
- [ ] SLA compliance percentage.
- [ ] Tickets by priority/category/source/requester/agent/group.
- [ ] Agent workload dashboard.
- [ ] Aging buckets for unresolved tickets.
- [ ] CSV/Excel export.
- [ ] Saved filters/views.

## Automation
- [ ] Rule engine: when conditions match, change fields/assign/send notifications.
- [ ] Scheduled rules for stale tickets.
- [ ] Auto-close resolved tickets after configurable number of days.
- [ ] Auto-reopen on requester reply.
- [ ] Webhooks for ticket events.
- [ ] REST API tokens.
- [ ] PowerShell helper/module for creating, updating, searching, and closing tickets.

## Assets / CMDB
- [ ] Assets linked to tickets.
- [ ] Computers, users, software, printers, network devices, and other CI types.
- [ ] Asset ownership / assigned user.
- [ ] Asset history and related tickets.
- [ ] Import asset data from scripts or CSV.

## Knowledge and self service
- [ ] Knowledge-base articles.
- [ ] Suggested articles while requester creates a ticket.
- [ ] Service catalog with structured request forms.
- [ ] Frequently requested services.
- [ ] Announcements / outage banner.
- [ ] Known issues page.

## Integrations
- [ ] Microsoft Teams notifications/actions.
- [ ] Entra ID user lookup.
- [ ] Freshservice import/migration utility.
- [ ] Monitoring-system integration.
- [ ] GitHub issue/PR linking when relevant.

## Data and maintenance
- [ ] Automated JSON backups.
- [ ] Backup/restore screen.
- [ ] Archive old closed tickets.
- [ ] Data-retention policy.
- [ ] Integrity validation / repair utility for JSON files.
- [ ] Migration path to SQLite or MySQL if ticket volume outgrows JSON.
- [ ] Installer/setup health check for writable folders, PHP version, and required extensions.

## time-clock

Source: [time-clock/TODO.md](time-clock/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Time Clock kiosk.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Consolidated documented kiosk and access-control work.
-->

# Time Clock TODO

## Documented work

- [ ] Replace the browser PIN/session-storage gate with server-side write authorization and CSRF for alert and alarm endpoints; keep the display readable as intended. Source: `user-management/MIGRATION-TRACKER.md`. `admin.html` currently gates the UI in the browser.
- [ ] Disable screen blanking in the Wayfire idle configuration and verify a full kiosk reboot. Source: `rpi/NOTES.md`.
- [ ] Implement and verify the custom Plymouth boot splash. Sources: `README.md`, `rpi/NOTES.md`.

## Code review suggestion

- [ ] Add a safe local validation path for weather, alert, and alarm polling so UI changes can be checked without touching live emergency endpoints.

## tv-binge-board

Source: [tv-binge-board/TODO.md](tv-binge-board/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Current open work for TV Binge Board; completed history stays in TASKS.md.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Summarized open tasks without duplicating completed audit history.
-->

# TV Binge Board TODO

## Documented work

- [ ] Finish the pre-public security review: rotate testing values, review public setup documentation, and disable or tightly restrict public registration. Source: `TASKS.md`.
- [ ] Add server-side upload safety scanning if the site becomes public or broadly multi-user. Source: `TASKS.md`.
- [ ] Add account recovery by email and consider optional two-factor authentication. Source: `TASKS.md`.
- [ ] Design shared-identity migration that preserves libraries, social connections, and remembered-login behavior without leaving legacy tokens as an authentication bypass. Source: `user-management/MIGRATION-TRACKER.md`.

Completed feature history remains in `TASKS.md`; its completed items should not be reopened here.

## user-management

Source: [user-management/TODO.md](user-management/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Current work for shared user management and app migration.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Summarized the migration tracker without changing its status claims.
-->

# User Management TODO

## Documented work

- [ ] Recheck `MIGRATION-TRACKER.md` against current branch and live deployment before treating any listed PR or candidate status as current; its last recorded inventory and statuses are dated 2026-09-25.
- [ ] Continue one project migration at a time, defining owner mappings and permissions, gating all pages and APIs, testing isolation/revocation, and recording rollout evidence before marking Complete. Source: `MIGRATION-TRACKER.md`.
- [ ] Deploy reviewed shared-account changes and provision accounts only after the applicable migration and isolated demo data are ready. Source: `MIGRATION-TRACKER.md`.

The detailed per-project checklist and completion criteria stay in `MIGRATION-TRACKER.md`.

## visual-ping-webhook-api

Source: [visual-ping-webhook-api/TODO.md](visual-ping-webhook-api/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Planning work for the Visual Ping webhook API folder.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded the missing specification and implementation.
-->

# Visual Ping Webhook API TODO

## Code review findings

- [ ] Replace the one-line placeholder README with a purpose, expected request/response contract, authentication model, and deployment notes before implementation.
- [ ] Add the webhook handler and tests once the contract is defined; this folder currently contains only `README.md` and no endpoint code.

## warranty-tracker

Source: [warranty-tracker/TODO.md](warranty-tracker/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Warranty Tracker.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected migration and validation work.
-->

# Warranty Tracker TODO

## Documented work

- [ ] Map existing local users to shared identities while preserving household invitations and private/shared warranty visibility. Verify account access and record ownership before rollout. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestion

- [ ] Automate the README's manual date-boundary, visibility, and CRUD checks so changes to `WarrantyStore.php`, `Auth.php`, or the API can be verified without a live household dataset.

## weather

Source: [weather/TODO.md](weather/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Weather Dashboard.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Added code review suggestions for cache and location handling.
-->

# Weather Dashboard TODO

## Code review suggestions

- [ ] Add fixture-based tests for latitude/longitude authority, city/state disambiguation, ZIP resolution, and API failure behavior. These are the accuracy guarantees in `README.md`, but this folder has no automated test suite.
- [ ] Document and test behavior when the upstream weather request fails while a cached payload exists; `weather_update.php` owns both fetching and cache/history writes.

## webstats

Source: [webstats/TODO.md](webstats/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Current open work for Webstats.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Summarized the existing roadmap and identity migration.
-->

# Webstats TODO

## Documented work

- [ ] Add server-side YOURLS redirect events; browser JavaScript cannot observe bare HTTP redirects. Source: `ROADMAP.md`.
- [ ] Add CSV export and configurable report widgets. Source: `ROADMAP.md`.
- [ ] Evaluate privacy-preserving browser/device aggregation and automated SPA route tracking with deduplication. Source: `ROADMAP.md`.
- [ ] Migrate dashboard authentication to shared identity while leaving public event collection working and preserving events. The shared framework exists, but Webstats still uses its own dashboard admin flow. Sources: `ROADMAP.md`, `user-management/MIGRATION-TRACKER.md`.
- [ ] Consider an indexed storage adapter only if measured traffic outgrows bounded JSON files. Source: `ROADMAP.md`.

## webstats-demo

Source: [webstats-demo/TODO.md](webstats-demo/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Webstats demo.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded demo validation and documentation gaps.
-->

# Webstats Demo TODO

## Code review suggestions

- [ ] Add a repeatable check that the sample HTML and PHP pages generate the expected page and click events while the excluded button generates none; `webstats/README.md` currently describes this as a manual dashboard check.
- [ ] Document that demo visits create real Webstats events and include a pointer to the verification steps in `webstats/README.md` so deployment testers know how to interpret the results.

## wheel

Source: [wheel/TODO.md](wheel/TODO.md)

<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Random Wheel.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded focused usability and accessibility improvements.
-->

# Random Wheel TODO

## Code review suggestions

- [ ] Persist the choice list separately from winner history so a reload does not reset edited entries to the four hard-coded defaults. `index.html` currently stores only the last ten winners.
- [ ] Provide a reduced-motion path for the five-second wheel spin itself; current reduced-motion CSS only disables the winner celebration.
- [ ] Add keyboard and screen-reader verification for spin status and long choice lists before changing the canvas interaction.
