<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Workout Tracker setup, architecture and validation.
Revision: 1.0.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.0 - Initial release.
-->
# Workout Tracker

Private, mobile-first PHP workout logger at target URL https://jasr.me/github/workout-tracker/.

## Setup
1. Deploy beside `1-Framework` and `user-management` on the same host. PHP 8.1+, fileinfo, curl and HTTPS required.
2. In the central user-management portal register project ID `workout-tracker`, path `/github/workout-tracker/`, then grant your account `member` access. No standalone accounts.
3. Ensure `storage/` is writable by PHP and inaccessible over HTTP. The included Apache/LiteSpeed .htaccess denies requests; **verify a direct HTTP request returns 403**. For nginx, configure a server deny rule or move storage outside document root before using.
4. Optional AI: copy `config.example.php` to `config.local.php` and set an OpenAI-compatible HTTPS chat-completions endpoint, model and API key. Never commit local config. AI is optional; manually enter exercise fields when unavailable.
5. Add a deployment backup for ignored `storage/` files and photos. Never clean ignored runtime directories during sync.

## Behavior
Each saved record is one exercise, with date, distance, duration, speed, reps, sets, weight, notes and optional photo. Different exercises have separate chart series. AI proposes fields for **review** and does not write records. Photos are private server files and not exposed by a public route. Data is separated by stable central user ID. Entries are append-only in v1; editing, image viewing, OCR and multi-exercise AI parsing are future work.

## Initial treadmill record
The October 21, 2026 workout (1.16 mi, 22 min, 3.3 mph) was reported in chat. It is **not automatically inserted** into the hosted app; enter it once after first login to avoid assuming ownership or live deployment.

## Manual validation
- Unauthenticated GET and POST must reject or redirect. A viewer cannot save. Member can save.
- POST with missing/invalid CSRF returns 403. Invalid dates, exercise names and metrics return 422.
- Add two treadmill entries and a push-ups entry; treadmill chart must exclude push-ups. Change metric to verify null values are skipped.
- Upload JPG/PNG/WebP and reject unsupported types and files over 5 MB; direct requests to storage must return 403.
- Reload and verify persistence; verify another authorized account cannot read these entries.
- Configure AI with a dummy key and verify failed calls reveal no secret; test successful extraction and review before saving.
- Check mobile layout, server PHP error logs, and backups. Do not merge until hosting security tests pass.
