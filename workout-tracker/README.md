<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Setup, key-free quick entry, optional AI and screenshot import guidance for Workout Tracker.
Revision: 1.4.0
Created Date: 2026-10-21
Modified Date: 2026-10-27
Changelog: 1.4.0 - Flexible exercise measurements, calories and variable weighted sets; safe legacy records. 1.3.2 - Responsive date/time layout and exercise-specific form measurements without losing hidden legacy fields. 1.3.1 - Fix Edit button form opening and add browser-compatible regression test. 1.3.0 - Safely edit saved workouts, add tracking tags and treadmill incline without migrating user data. 1.2.0 - Optional AI retry, exact-name config template and prepared screenshot import. 1.1.0 - Added local language parsing, batch entry, mobile-first review and tests. 1.0.0 - Initial release.
-->
# Workout Tracker

Private PHP workout logger at https://jasr.me/github/workout-tracker/ using shared **1-Framework** storage and central **user-management** authentication.

## Quick entry — no API key required

1. Open the tracker after signing into user-management. Type a sentence such as **"1.16 miles at 3.3 on treadmill for today for 22 minutes"**.
2. Tap **Review workout**. The built-in PHP parser extracts exercise, distance, duration, speed, reps, sets, weight and date. Nothing is sent to an external AI provider.
3. Review the compact result (e.g. Treadmill · 1.16 mi · 22 min · 3.3 mph), expand **Edit measurements or date** only if a correction is needed, then tap **Save workout** once.
4. A sentence with multiple recognized exercises (e.g. "treadmill 1 mile in 20 minutes, then 20 push-ups") creates multiple separate exercise entries as one atomic session. Trends remain isolated by exercise and measurement.
5. Dates default to the user's **browser-local current date**. Time defaults to the current local time at review. "Yesterday", "last Monday", an ISO date, and US numeric MM/DD/YYYY dates are supported for backdating. An optional time control exists under review details.

The parser is rule-based rather than a general LLM. It recognizes common exercises and explicit numbers but does **not guess missing values**. Unrecognized exercises use the collapsed manual entry form. It does not analyze photos.

## Flexible measurements and weighted sets (v1.4.0; issue #128)

Organization **tags** (such as strength or cardio) remain separate from **measured numeric values**. Filters still organize workouts by tag.

### Treadmill and other cardio

- Record miles, minutes, speed, incline and **calories burned (kcal)** independently. The local parser recognizes "treadmill 2 miles for 30 minutes, incline 5%, 320 calories"; it never guesses calories.
- Under **Additional measurements**, tap **+ Add measurement** to log user-defined numeric metrics such as **Elevation gain / ft / 250**, **Resistance level / (blank) / 7**, or **Heart rate / bpm / 132**. You can track up to 12 custom metrics for one workout.
- Progress charts match custom metrics by a normalized name plus unit. Keep that pair consistent across dates. Custom metrics allow signed values for measurements such as change from baseline.

### Multiple weightlifting sets in ONE exercise

- Select bench press, deadlift, or another supported strength exercise. Under **Individual sets**, tap **+ Add set** to record each load and repetition count in order, up to 40 sets in one workout record.
- Example on one date: **95 lb × 12**, **115 lb × 10**, **135 lb × 8**, **115 lb × 10**.
- Show **40 total reps**, **135 lb heaviest set**, **4,520 lb total volume** (95×12 + 115×10 + 135×8 + 115×10), and **4 sets**. The chart offers these separate strength-progress metrics.
- Bodyweight sets may leave the load blank. Do not report an average weight for sets with varying loads.
- Local natural-language parsing recognizes ordered wording such as "bench press 95 lb x 12, 115 lb x 10, 135 lb x 8". Review the result before saving; optional AI fallback remains opt-in.
- A legacy bench-press entry with a scalar reps/sets/weight summary remains a **legacy summary**. The application never invents individual sets from those totals.

### Persistence and privacy

- The per-user JsonStore files retain their current IDs, session IDs, timestamps, notes, photo references, tags, edit history, record order and schemaVersion 1 envelope. **No database migration, reset, delete or destructive import.**
- Optional per-record additions: calories_kcal (number/null), custom_metrics (array of name, unit, value), and sets_detail (ordered reps, weight_lb and note). Existing records without them still work.
- Older clients that omit these optional fields must never clear them. Edits remain authenticated, CSRF protected, locked, revision-checked and audited; stale edits return HTTP 409.
- Custom labels/units and numeric values receive strict server validation. The browser builds labels using safe textContent, not HTML injection.
- Before Hostinger deployment, **back up the ignored storage directory, JSON files and private photos**, verify the webhook does not wipe runtime storage, and compare old records before and after.
- Manual verification: save treadmill with calories/custom metric, save one bench press with different loads, refresh, edit the last set, and verify the original treadmill entries and uploaded photos remain accessible.

### New test commands

    php workout-tracker/tests/measurements.test.php
    node workout-tracker/tests/advanced.test.cjs

The new tests operate on synthetic data and temporary JSON files only. Existing parser, edit, security and mobile validation remain mandatory.

## Exercise-specific measurements and mobile layout (v1.3.2)

- **Treadmill:** the quick-entry review, manual form, and saved-workout editor show **miles, minutes, speed (mph), incline (%)** by default; reps, sets and lifting weight are collapsed under **Show other measurements**.
- **Walking/running/cycling/rowing/swimming/elliptical/stairs:** miles, minutes and speed are primary. **Push-ups/squats/sit-ups/lunges/crunches:** reps and sets. **Bench press/deadlift/weight training:** weight, reps and sets. **Yoga/plank:** duration and sets. Unrecognized/custom exercise names show all currently supported fields.
- Exercise field visibility is **presentation-only**. Every measurement remains in the page's controls—even when collapsed—so old records containing unusual values are not cleared by opening/editing an entry. **Show other measurements** now sits directly after the primary measurements, before tags or notes, and indicates when an older workout has extra saved values. Changing the exercise name dynamically refreshes its suggested fields without discarding values.
- Trends also offer measurements relevant to the selected exercise, plus any *nonstandard historical metrics* already saved for that exercise. No JSON schema changes or migrations.
- On **typical phones (375–560 CSS pixels)**, compact measurements use two columns while the exercise name, date, time, tag, and notes occupy a full-width row. On very narrow phones (360 CSS pixels and below), fields stack in one column. Native iOS date/time pickers remain enabled and must not overlap adjacent fields. Trend selectors and optional time/photo controls stack on narrow screens; form inputs use 16px text to avoid Safari focus zoom and buttons meet a 44px minimum touch height.
- Run `node workout-tracker/tests/edit-button.test.cjs`; it checks exercise-specific field visibility, Edit actions, unchanged saved records, placement of extra measurements, and the mobile date/time styling rules. A local Chromium preview with representative workout markup was checked at viewport widths **320, 360, 375, 390, 430, 560, 667, 768, and 1024 CSS pixels**: no horizontal overflow or date/mileage intersection was observed. That preview is **not a live production or WebKit/iOS device test**; verify the date/time pickers on a physical iPhone after deployment.

## Edit button repair (v1.3.1)

- Fix a JavaScript `DOMException` that prevented an existing workout's **Edit** button from opening its form. Empty CSS class tokens were being added to ordinary date/number fields; classes are now only added when needed.
- `app.js?v=1.3.1` forces browser refresh of the corrected code after Hostinger deployment; PHP storage schema and saved workout files are unchanged.
- `node workout-tracker/tests/edit-button.test.cjs` exercises the actual history click listener and populated editor with realistic class-token validation, including legacy workouts that have no tag or incline; the CI workflow now runs it.
- After deployment, tap **Edit** on both the October 21 and October 22 workouts. Each should open a prefilled form; **Cancel** should leave values unchanged. The live workout files must be preserved as before.

## Edit saved workouts, tracking tags and treadmill incline (v1.3.0)

- Under **Workout history**, select **Edit** beside an existing workout (select **View all entries** to go beyond the newest ten). The form preloads exercise, date, local time if stored, distance, speed, duration, reps, sets, weight, treadmill incline, tag and notes. Confirm to update **only that workout record**; **Cancel** makes no change.
- A tracking **tag** is an optional short label (e.g. `Cardio`, `Morning`, `Recovery`). Enter it before a new quick workout, adjust it in the preview, or attach it while editing a saved workout. Use the **Filter workouts and trends by tag** dropdown to narrow history and the metric chart. Local descriptions containing `#cardio` are recognized as a tag.
- Treadmill **incline_percent** is an optional separate measurement, not derived from speed/distance and not retroactively assigned to old entries. Log `treadmill 1.2 miles for 23 minutes at 3.5 mph incline 5%`, fill the incline in a preview/manual form, or edit an older treadmill entry. The chart's **Treadmill incline (%)** metric trends it separately.
- **No JSON migration or destructive initialization:** existing `storage/<hashed-user-id>.json` files keep the same filename and framework envelope (`schemaVersion:1`, `records`). Reads never rewrite records. Existing entries missing `tag`, `incline_percent` or `performed_at` remain valid. No prior values are automatically backfilled.
- **Targeted edits and recovery:** records are selected by original ID, edited under `JsonStore::update`'s exclusive file lock, and require the exact revision hash supplied by the authenticated GET. A conflicting concurrent edit returns HTTP 409 and saves nothing; missing IDs return 404. `created_at`, `session_id`, `photo` and unknown legacy fields stay intact; untouched records remain unchanged. Every actual edit appends a full prior record snapshot to `edit_history` before saving its new values, so previous values are retained for manual recovery. There is no delete operation.
- **Before deployment:** back up the *existing* ignored `workout-tracker/storage/` folder, including JSON, `.lock` files and private photos, and verify the Hostinger webhook does **not** run `git clean -fdx`, `rsync --delete` over runtime storage or an equivalent destructive cleanup. Do not reset or replace live JSON files. Backups stay outside the public web directory.
- **After deployment:** confirm the existing entry count, notes, dates and photos still exist; edit one known record and verify the other entries, session totals, tags and charts remain intact.

## Optional AI fallback (explicit and private)

**Local parsing stays the default and requires no AI key.** A secondary **Try AI interpretation instead (optional)** button appears below **Review workout**, including after the local parser fails or misreads a sentence. It is disabled until private credentials are set. It only sends your description when you deliberately tap the button, and the resulting suggestion must be reviewed before saving.

Hostinger setup (never do this inside GitHub):

1. The tracked template is **`config.local.example.php`**. On Hostinger copy it to **`config.local.php`** by **removing only `.example`**, keeping the rest of the name unchanged.
2. Enter the provider endpoint, model and secret API key in `config.local.php`. Never commit or show the real key in screenshots.
3. The root `.htaccess` denies HTTP access to private local configuration. For nginx or other servers configure an equivalent deny rule or store secrets outside the public web directory.
4. Reload the app. Once configured, its optional AI button becomes enabled. The app sends workout text to your configured external provider **only after explicit opt-in**. Suggestions may be wrong; check every metric and date.

`config.local.php.placeholder` is a tracked, safe marker explaining the host-only file. It is **not** loaded as a config and must not contain a key. The app never auto-generates actual API credentials.

## Setup

1. Deploy workout-tracker beside 1-Framework and user-management on the same host. PHP 8.1+, fileinfo and HTTPS are required; cURL is only needed if using an optional external AI service.
2. In central user-management register project ID workout-tracker with path /github/workout-tracker/, then grant the intended account member access. Existing all-project access works once the project is registered.
3. Ensure storage/ is writable by PHP but inaccessible over HTTP. Apache/LiteSpeed must honor storage/.htaccess; verify a direct request for a private test file receives **403**. On nginx, configure a deny rule or use a private directory outside the public web root.
4. The tracked `storage/.placeholder` and `storage/photos/.placeholder` keep private directories in deployment. PHP creates per-user JSON files and photo subdirectories **automatically** when used. Only optional `config.local.php` is copied manually. Never auto-copy or overwrite an existing private config.
5. Back up private storage and photos. Preserve ignored files through GitHub webhook deployments.

## Architecture

- index.php: authenticated page/API, CSRF validation, optional authenticated AI fallback, per-user isolation, atomic multi-exercise writes using the shared framework JsonStore.
- parse.php: no-key text interpretation of workouts and relative or explicit dates.
- app.js / app.css: responsive, progressive entry UI, review and isolated exercise trends.
- storage/.htaccess: deny direct access to JSON records and photos. Photos are private but not yet viewable in the app.
- JSON follows the 1-Framework schemaVersion 1 records envelope, separated by hashed stable user ID.
- Saved exercises share a session_id, the chosen date, local time with UTC offset, and creation timestamp. Each batch is written atomically.
- Existing entries support revision-checked editing and retained previous values, optional calories/custom metrics, and per-set weights. OCR, authenticated photo viewing, body weight and blood-pressure logs remain future work.

## Verification

Run these from the repository root:

    php workout-tracker/tests/parse.test.php
    php workout-tracker/tests/edit.test.php
    php -l workout-tracker/entries.php
    node --check workout-tracker/app.js
    php -l workout-tracker/index.php
    php -l workout-tracker/parse.php

Also test the optional AI fallback with a safe test account/endpoint: disabled without credentials, enabled after server-only setup, failure without exposing keys, returned one or multiple activity suggestions requiring review, and no automatic saves.

The edit tests create an **isolated temporary JSON file**, start with three preexisting legacy entries, edit exactly one, and verify the other two remain byte-for-byte equivalent in decoded records; they also assert timestamps/photos/session IDs remain, the original values are preserved in edit history, bad inputs are rejected, and a stale revision cannot overwrite concurrent updates. CI runs these tests without touching Hostinger storage. Browser execution of the CLI tests is denied by `tests/.htaccess`.

The parser regression tests cover treadmill wording, sets/reps, multiple exercises, dates, durations and invalid text. Test separately on staging/Hostinger: authentication, viewer vs member permissions, CSRF, cross-account isolation, no-key parsing, one-time saves, private file HTTP 403, mobile display, storage persistence, upload size/type validation, and ignored-runtime backups.

## Initial treadmill record

The October 21, 2026 workout (1.16 miles, 22 minutes, 3.3 mph) is **not imported**. Enter it once after deploying the revised application.

## Mobile screenshots

Six October 21, 2026 screenshots have been prepared as a **separate import archive**. Their exact filenames and provenance are documented in [screenshots/README.md](screenshots/README.md). The screenshot images are not yet committed in this branch, so a gallery is intentionally not embedded here until the files have been uploaded to the repository.

The archive contains six matching WebP files under `workout-tracker/screenshots/`. It covers the dashboard, quick text entry, push-up example, review, separate trend chart, and manual-entry fallback. User-uploaded workout photos remain private and ignored under `storage/photos/`.
