# Changelog

## 1.0.1 — 2026-08-25

- Fixed iPhone uploads being blocked when the phone's local date was one day ahead of the web server date.
- Daily upload and birth-date limits now use the browser's local calendar date.
- Added a one-day server-side timezone allowance while still rejecting genuinely future dates.

## 1.0.0 — 2026-08-25

- Added private first-run gallery setup and sign-in.
- Added daily and backdated photo uploads with optional notes.
- Added birth-day numbering, missing-day tracking, streaks, and gallery statistics.
- Added authenticated image delivery and runtime storage exclusions.
- Added a browser-generated downloadable collage.
- Added responsive mobile design, configuration example, audit events, and deployment documentation.
