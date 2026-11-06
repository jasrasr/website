# Changelog

## 2.8.0 - 2026-11-06

- Make the “View larger photo” badge visibly translucent so the underlying photo shows through.
- Add three missing-photo reminder slots per day, defaulting to 12:00 PM, 4:00 PM, and 8:00 PM.
- Track email and SMS delivery separately for each reminder slot and stop all later reminders once the photo is added.
- Correct reminder-ledger cleanup for channel- and time-specific delivery keys.

## 2.7.1 - 2026-11-05

- Lighten the shared-gallery “View larger photo” overlay so it competes less with the photo.
- Prevent mobile birth-detail controls from overflowing their grid cells and stack them on narrow screens.

## 2.7.0 - 2026-11-05

- Add optional per-gallery SMS reminders through Twilio using server-only credentials.
- Send email and SMS reminders independently with separate delivery tracking and retries.
- Add a command-line Twilio test-message option and E.164 recipient validation.
- Record secure inbound email photo attachments as a future TODO.

## 2.6.0 - 2026-11-05

- Link each missing-photo reminder directly to the correct authorized baby gallery.
- Open the add-photo dialog automatically and preselect the missing date after sign-in.
- Ignore invalid or unauthorized gallery identifiers in reminder links.

## 2.5.0 - 2026-11-04

- Add an optional Boy/Girl setting to each baby gallery.
- Apply a subtle blue or pink palette to signed-in, unlisted, and public gallery views.
- Keep the neutral palette when gender is not selected.
- Restore the unlisted-gallery session check on direct shared-image requests.

## 2.4.1 - 2026-11-03

- Show the overall project revision and latest project-file modification date in signed-in and shared-gallery footers.
- Calculate the footer date automatically while excluding runtime photos, generated thumbnails, dependencies, and Git metadata.

## 2.4.0 - 2026-11-03

- Serve cached 720-pixel WebP thumbnails in signed-in, unlisted, and public gallery grids.
- Keep original-resolution photos for enlarged views, collages, GIFs, and stored downloads.
- Generate thumbnails lazily for existing photos and remove cached thumbnails when photos are deleted.
- Fall back safely to original files when the server does not provide PHP GD/WebP support.

## 2.3.0 - 2026-11-03

- Replace name-and-birthday unlisted URLs with opaque identifiers.
- Require the baby’s name and birth date before an unlisted gallery or its images can be viewed.
- Keep public gallery URLs in the readable `firstname-MMDDYYYY` format with no prompt.
- Limit repeated unlisted-gallery verification attempts in each browser session.

## 2.2.0 - 2026-11-03

- Add per-gallery missing-photo email reminders with a configurable time and recipient list.
- Send through authenticated Titan SMTP at `smtp.titan.email:465` using SSL and `notifications@justjason.fyi` as the sender.
- Add a CLI-only scheduled checker, duplicate-delivery ledger, delivery logs, and SMTP test command.

## 2.1.2 - 2026-11-03

- Add spacing inside the shared-gallery heading card and wrap long baby names within it.

## 2.1.1 - 2026-11-03

- Show larger shared-gallery photos in a screen-sized viewer with Escape and X close controls.

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


