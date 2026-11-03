# Changelog

## 2.1.0 - 2026-11-03

- Add Private, Unlisted, and Public gallery visibility choices.
- Preserve existing shared galleries as unlisted and add a browseable list for public galleries.
- Keep shared photo and gallery links unavailable when a gallery is private.

## 2.0.3 - 2026-11-03

- Increase the size, weight, and contrast of the public-sharing setting text.

## 2.0.2 - 2026-11-03

- Let public gallery visitors open each original photo at full size from its thumbnail.

## 2.0.1 - 2026-11-03

- Allow birth length measurements in hundredths of an inch, including 19.69 and 19.7.
- Improve text contrast in the public-sharing settings panel and darken secondary text throughout the app.

## 2.0.0 - 2026-11-03

- Replace Daily Babe usernames, passwords, and local sessions with central User Management.
- Separate adult identity from baby gallery metadata and support multiple adults across multiple babies.
- Add central viewer/member/admin role enforcement to page, API, and private image access.
- Keep per-baby membership and runtime data in a new `storage/central/` namespace.
- Start with a clean gallery set; earlier test galleries are intentionally not migrated or read.
- Keep optional public first-name/birthday links independent from authenticated editing.
- Route password and account changes to User Management.

## 1.5.0 - 2026-11-03

- Add an optional fully open, read-only public view for each baby gallery.
- Derive the public identifier from the baby’s first name and actual birthday as `firstname-MMDDYYYY`.
- Keep uploads, deletions, settings, exports, and account controls authenticated.
- Add dedicated public gallery and public image endpoints that fail closed when sharing is disabled.
- Prevent two public galleries from claiming the same first-name/birthday link.

## 1.4.0 - 2026-10-26

- Separate user accounts from baby galleries while retaining existing usernames, password hashes, baby IDs, settings, and photos.
- Back up the prior authentication file before migration to the new format.
- Let the admin create users, assign gallery access, and add babies without creating baby sign-ins.
- Let users switch among the galleries they can access.

## 1.3.3 - 2026-10-26

- Send an explicit UTF-8 Content-Type for the changelog on Hostinger so punctuation in release notes displays correctly.

## 1.3.2 - 2026-10-24

- Declare UTF-8 for Markdown and use plain separators in release headings so dates display correctly.

## 1.3.1 - 2026-10-22

- Refresh the gallery with a warmer, photo-first layout, clearer controls, and responsive styling.
- Hide inactive notifications fully so no tab peeks up from the bottom edge.

## 1.3.0 - 2026-10-22

- Give the original account super admin access to all baby galleries through a gallery selector.
- Keep gallery switching restricted by role and preserve the super admin session when adding a baby.
- Let signed-in users remove photos from their own gallery, including photos they uploaded earlier.
- Let the super admin remove photos from any selected baby gallery.
- Stop browser caching of delivered photos so removed photos are not retained by the app cache.

## 1.2.0 - 2026-08-27

- Added a baby first-name username to setup and sign-in.
- Added password changes requiring the current password, a new password, and a matching confirmation.
- Added creation of additional baby galleries from the authenticated Settings screen.
- Kept all account credentials in one protected authentication file.
- Isolated each baby’s settings, entries, backups, and photos in a separate random-ID storage folder.
- Added a safe first-login migration that copies the existing single-baby gallery before upgrading authentication.
- Added duplicate-username prevention and per-baby authenticated image delivery.

## 1.1.0 - 2026-08-27

- Close and reset the add-photo dialog immediately after a confirmed successful save.
- Let iPhone users choose an existing photo, take a new photo, or browse Files.
- Center-crop the upload preview instead of anchoring it to the top.
- Add a downloadable, shareable animated GIF slideshow in addition to the collage.
- Keep every image inside its collage cell so later rows cannot cover earlier day labels.
- Add a visible day label to every collage tile and GIF frame.
- Reduce the mobile add-photo dialog so its controls fit on screen more comfortably.
- Add optional birth time, length in inches, and weight in pounds/ounces.
- Clarify that actual birth date controls day numbering while due date remains the countdown and early/late reference.

## 1.0.2 - 2026-08-25

- Replaced unreliable iPhone data-URL collage downloads with a real JPEG blob.
- Added a collage preview with iOS Share/Save and standard file-download actions.
- Added clearer collage progress and image-loading failure handling.

## 1.0.1 - 2026-08-25

- Fixed iPhone uploads being blocked when the phone's local date was one day ahead of the web server date.
- Daily upload and birth-date limits now use the browser's local calendar date.
- Added a one-day server-side timezone allowance while still rejecting genuinely future dates.

## 1.0.0 - 2026-08-25

- Added private first-run gallery setup and sign-in.
- Added daily and backdated photo uploads with optional notes.
- Added birth-day numbering, missing-day tracking, streaks, and gallery statistics.
- Added authenticated image delivery and runtime storage exclusions.
- Added a browser-generated downloadable collage.
- Added responsive mobile design, configuration example, audit events, and deployment documentation.

