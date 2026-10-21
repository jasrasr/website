<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Setup, key-free quick entry, optional AI and screenshot import guidance for Workout Tracker.
Revision: 1.2.1
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.2.1 - Document paid AI request limits, Super Admin exemptions and config backup denial. 1.2.0 - Optional AI retry, exact-name config template and prepared screenshot import. 1.1.0 - Added local language parsing, batch entry, mobile-first review and tests. 1.0.0 - Initial release.
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

## Optional AI fallback (explicit and private)

**Local parsing stays the default and requires no AI key.** A secondary **Try AI interpretation instead (optional)** button appears below **Review workout**, including after the local parser fails or misreads a sentence. It is disabled until private credentials are set. It only sends your description when you deliberately tap the button, and the resulting suggestion must be reviewed before saving.

Hostinger setup (never do this inside GitHub):

1. The tracked template is **`config.local.example.php`**. On Hostinger copy it to **`config.local.php`** by **removing only `.example`**, keeping the rest of the name unchanged.
2. Enter the provider endpoint, model and secret API key in `config.local.php`. Never commit or show the real key in screenshots.
3. The app's root `.htaccess` denies HTTP access to every `config.local` prefix (case insensitive), including `.bak`, `~`, template, placeholder, hidden swap and editor backup variants. For nginx or other servers configure an equivalent deny rule or store secrets outside the public web directory.
4. Reload the app. Once configured, its optional AI button becomes enabled. The app sends workout text to your configured external provider **only after explicit opt-in**. Suggestions may be wrong; check every metric and date.

`config.local.php.placeholder` is a tracked, safe marker explaining the host-only file. It is **not** loaded as a config and must not contain a key. The app never auto-generates actual API credentials.

### Paid AI request limits

Both `parse_ai` and the legacy `parse` route share a persisted per-account budget: **5 attempts per rolling minute and 50 per rolling 24 hours**. Reservations happen atomically before the external request, survive refreshes/new sessions, and count provider failures too. Invalid input, rejected authorization/CSRF, and missing provider configuration do not consume the budget. A blocked request returns HTTP **429**, `Retry-After` seconds, and JSON `retry_after`; no provider call is made. Storage/limit configuration failures block AI calls rather than allowing unlimited usage.

These are **request-count limits**, alongside the existing **1,800 output-token maximum per call** and 4,000-byte input validation. They are not a billed-token or dollar budget: input tokens, model prices and failed-call billing vary. Set provider-side billing controls as appropriate. Local parsing and saving workouts are not rate limited.

Authenticated **non-demo central Super Admins are exempt** from these request limits. The repository's Jason account provisioning assigns `jasrasr` this central role; verify the actual live account role in user-management. Project admins and demo Super Admins remain limited. Exemption never bypasses project access, CSRF, input validation or the per-call output-token maximum.

For another specific account, add its immutable central user ID to `request_limits.exempt_user_ids` in the private `config.local.php` (not its username/display name). The tracked template shows the optional `request_limits` keys. Existing configs automatically receive the defaults above; **do not overwrite an existing config or API key**. Thresholds must be integers from 1 to 10,000. Exemption decisions use only the central provider's server-side identity.

Counters are auto-created as ignored `storage/ai-requests-<hashed-user-id>.json` plus stable lock files, using framework `JsonStore`. They contain attempt timestamps, no workout text or credentials, and are separate from workout history. Preserve them through deployments so quotas do not reset; include them in private storage backups. Expired timestamps are pruned on the next request.

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
- Entries are append-only. Editing saved entries, OCR, authenticated photo viewing, body weight and blood-pressure logs are future work.

## Verification

Run these from the repository root:

    php workout-tracker/tests/parse.test.php
    php workout-tracker/tests/ai-limits.test.php
    php -l workout-tracker/index.php
    php -l workout-tracker/parse.php

Also test the optional AI fallback with a safe test account/endpoint: disabled without credentials, enabled after server-only setup, failure without exposing keys, returned one or multiple activity suggestions requiring review, and no automatic saves.

The Workout Tracker validation workflow runs syntax, parser and quota regressions, including parallel reservations, rolling-window boundaries, cross-account isolation, corrupt/missing storage, role/ID exemptions and config filename patterns. Before enabling a real key on Hostinger, verify with harmless config-named fixtures that HTTP requests for `config.local.php.bak`, `config.local.php~`, `.config.local.php.swp` and uppercase variants receive 403, then remove the fixtures. Verify valid app routes still load. Pattern tests cannot establish the host honors `.htaccess`.

On staging with a mock provider, use a member account to consume the limit through both AI action aliases and confirm the next request returns 429 with `Retry-After`, across a new session. Confirm failures count and invalid-CSRF requests do not. Verify the intended central Super Admin can exceed both thresholds, a demo Super Admin cannot, and changing client-supplied role/ID fields cannot claim exemption. Confirm local parsing and saves still work while AI is limited, and repeat with Hostinger's PHP version. No automated check proves deployment or live account provisioning.

The parser regression tests cover treadmill wording, sets/reps, multiple exercises, dates, durations and invalid text. Test separately on staging/Hostinger: authentication, viewer vs member permissions, CSRF, cross-account isolation, no-key parsing, one-time saves, private file HTTP 403, mobile display, storage persistence, upload size/type validation, and ignored-runtime backups.

## Initial treadmill record

The October 21, 2026 workout (1.16 miles, 22 minutes, 3.3 mph) is **not imported**. Enter it once after deploying the revised application.

## Mobile screenshots

Six October 21, 2026 screenshots have been prepared as a **separate import archive**. Their exact filenames and provenance are documented in [screenshots/README.md](screenshots/README.md). The screenshot images are not yet committed in this branch, so a gallery is intentionally not embedded here until the files have been uploaded to the repository.

The archive contains six matching WebP files under `workout-tracker/screenshots/`. It covers the dashboard, quick text entry, push-up example, review, separate trend chart, and manual-entry fallback. User-uploaded workout photos remain private and ignored under `storage/photos/`.
