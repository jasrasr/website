<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release history for private Workout Tracker.
Revision: 1.3.0
Created Date: 2026-10-21
Modified Date: 2026-10-27
Changelog: 1.3.0 - Document v1.4.0 calories, custom numeric metrics and per-set strength logging. 1.2.2 - Document workout-specific fields and iPhone-safe date/time layout in v1.3.2. 1.2.1 - Document v1.3.1 Edit button DOMException repair and regression. 1.2.0 - Document v1.3.0 non-destructive edits, tags, incline and regression coverage. 1.1.0 - Document v1.2.0 AI fallback, prepared screenshots and secrets convention. 1.0.0 - Initial release history including v1.0.0 and v1.1.0.
-->
# Workout Tracker changelog

## v1.4.0 — 2026-10-27 (Issue #128)

- Optional calories burned (kcal) and arbitrary validated named numeric measurements, each with unit and individual exercise chart.
- Repeatable per-set weight/reps entries for a single strength session; calculate total repetitions, heaviest set and accurate total lifted volume.
- Extract calories and ordered varying set loads from natural-language workout descriptions without an AI key; extend explicit optional AI schema.
- Add thumb-friendly per-set and custom-measurement editors in quick-entry review, manual entry and existing-record editing.
- Protect historical user data: optional fields only, unchanged per-user JSON files and schema envelope, locked revision-checked edits, audit snapshots, backward-compatible older-client updates.
- Add PHP preservation/regression tests, Node editor tests and CI validation. No migration or automatic modifications of stored workouts.

## v1.3.2 — 2026-10-27

- Display only measurements relevant to the named exercise across natural-language review, manual entry and editing saved workouts (e.g. no reps/sets for treadmill; weights/reps/sets for bench press).
- Provide **Show other measurements** for unusual combinations, retaining hidden historical values in the existing form and JSON fields without any storage migration.
- Make mobile fields single-column and ensure date/time inputs cannot overlap adjacent fields on iOS.
- Limit trend measurement choices to relevant exercise fields plus any atypical values already stored.
- Add regression checks for treadmill/strength field visibility, preservation of untouched entry values and date/time layout rules.

## v1.3.1 — 2026-10-27

- Fix tapping **Edit** silently failing because the editor attempted to add an empty CSS class token and stopped with a browser `DOMException`.
- Add a regression test simulating the actual history Edit click and verifying the form opens with existing values, including missing optional fields, without modifying data.
- Bump the JavaScript cache query to `app.js?v=1.3.1`; no storage schema changes or workout-file migrations.

## v1.3.0 — 2026-10-22

- Add an **Edit** control on each saved exercise, **View all entries** for older records, and editable dates, time, notes and measurements.
- Preserve the existing JSON store and other workout records: use stable IDs, an atomic locked edit of one entry, and a revision token to reject stale changes. Keep original `created_at`, `session_id`, photos and unknown metadata.
- Append original values to `edit_history` rather than losing prior entry details. Existing entries require **no migration**.
- Add freeform **tracking tag** labels to new and existing workouts; filter workout history and the exercise chart by tag.
- Track optional treadmill `incline_percent` separately from speed/distance with local parsing and charting; existing workouts keep missing incline until explicitly entered.
- Add isolated JSON preservation tests, parser cases, PHP/JS validation workflow and Apache/LiteSpeed protection for CLI-only tests.

## v1.2.0 — 2026-10-21

- Add explicit, optional **Try AI interpretation instead** action for uncertain or failed local text parsing; requires a private, configured provider and always shows a review before save.
- Standardize secret template as `config.local.example.php`; copy to ignored `config.local.php` on Hostinger by removing only `.example`.
- Track `.placeholder` markers for config instructions, private workout JSON storage and photo directory. Enforce local configuration HTTP denial.
- Prepare six mobile screenshot assets for import; add `screenshots/README.md` with filenames and import instructions. Binary images are not yet committed.
- Update framework-wide development instructions so future hosted projects follow these conventions.

## v1.1.0 — 2026-10-21

- Built-in PHP description parser; no external AI API key required for common workouts.
- Supports multiple exercises in one sentence with a single atomic save and shared session ID.
- Defaults workout date and time to browser local values; understands yesterday, last Monday and numeric dates.
- Replaced the long required form with compact Review workout → Save workout flow, optional per-exercise correction controls and collapsed manual form.
- Separate exercise trends, mobile dashboard, private optional image uploads, and test cases for language extraction.
- Continued shared user-management authorization, CSRF protection, private JSON storage and framework JsonStore.
- Added repository project-index entry.

## v1.0.0 — 2026-10-21

- Initial private workout tracker with per-exercise chart, manual logging, optional external AI extraction and photo storage.
- Merged via PR #122; initial parser required optional configured service and recognized one exercise per extraction.
