<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-05
Revision: 1.5.23
-->

# Changelog

## rev 1.5.23 - 2026-07-05

- Verified Matt's current list/detail/home feedback against the active GitHub source.
- The episode detail page no longer defaults to Season 1 for long-running shows; it opens the most recent season that still has unwatched episodes.
- When the detail page auto-selects a later season, it scrolls that season into view after load.
- The List page now remembers each user's last filter choices in their profile JSON.
- The List page now defaults to hiding 100% complete, watched, caught-up, finished, and dropped items.
- The List page top area is more compact, uses a title-only partial search by default, and moves status/type/sort options into an Advanced filters panel.
- Home screen media sections now hide finished/caught-up/100% watched items, while stats still reflect the full library.
- Bumped the visible project revision and service worker cache to 1.5.23.

## rev 1.5.22 - 2026-07-05

- Changed the episode-grid prior-progress prompt to appear only when the selected episode would skip over an unwatched earlier episode.
- Marking the true next unwatched episode no longer asks whether to mark all prior episodes and seasons.
- The season-level prior-season prompt now appears only when there is an unwatched earlier episode before that season.
- Episode and season metadata are sorted before prompt checks so the gap detection follows natural season/episode order.
- Updated the Episode grid helper text to explain the new gap-aware behavior.
- Bumped the visible project revision and service worker cache to 1.5.22.

## rev 1.5.21 - 2026-07-05

- Added optional screenshot attachments to `suggestions.php`.
- Suggestion attachments accept PNG, JPG, and JPEG only.
- Upload validation checks extension, upload status, file size, and image MIME/dimensions using `getimagesize()`.
- Screenshots are saved under `public-cache/suggestions/` with random suggestion-based filenames.
- Suggestion JSON now stores attachment metadata including public URL, original filename, MIME type, dimensions, and byte size.
- Public board entries display attached screenshots as clickable previews.
- HEIC is intentionally not accepted for this suggestion-board screenshot field; iPhone screenshots are normally PNG, while HEIC photos should be converted to PNG or JPG before upload.
- Bumped the visible project revision and service worker cache to 1.5.21.

## rev 1.5.20 - 2026-07-05

- Added `suggestions.php`, a public suggestion and bug board that behaves like a lightweight issue tracker.
- Suggestions require an email address and use the signed-in user's saved email when available.
- If a signed-in user has no saved email, the first suggestion prompts for email and saves it to the user's profile.
- Suggestions are stored in `data/suggestions.json` and listed publicly with type/status filters.
- Public list entries show masked email addresses while keeping the full email in JSON for follow-up.
- Added an email field and Suggestions link to Settings.
- Queued the next list-page update in `TASKS.md`.
- Bumped the visible project revision and service worker cache to 1.5.20.

Older entries are available in Git history before rev 1.5.20.
