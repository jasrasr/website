# Changelog

## 1.1.0 — 2026-08-27

- Close and reset the add-photo dialog immediately after a confirmed successful save.
- Let iPhone users choose an existing photo, take a new photo, or browse Files.
- Center-crop the upload preview instead of anchoring it to the top.
- Add a downloadable, shareable animated GIF slideshow in addition to the collage.
- Keep every image inside its collage cell so later rows cannot cover earlier day labels.
- Add a visible day label to every collage tile and GIF frame.
- Reduce the mobile add-photo dialog so its controls fit on screen more comfortably.
- Add optional birth time, length in inches, and weight in pounds/ounces.
- Clarify that actual birth date controls day numbering while due date remains the countdown and early/late reference.

## 1.0.2 — 2026-08-25

- Replaced unreliable iPhone data-URL collage downloads with a real JPEG blob.
- Added a collage preview with iOS Share/Save and standard file-download actions.
- Added clearer collage progress and image-loading failure handling.

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
