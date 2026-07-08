<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.3.7
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.3.7**

## Rev 1.3.7 - 2026-07-06

- Replaced member-list coordinate text with closest-city text when a saved location exists.
- Removed accuracy text from the member-list latest-location line.
- Normalized latest-location age text to `s`, `m`, `h`, or `d` age units.
- Cached reverse-geocoded city labels in local browser storage.
- Added no new live-data folder.

## Rev 1.3.6 - 2026-07-06

- Added a display-name edit form to the signed-in account card.
- Added `profile.php` to save the signed-in user's display name.
- The saved name refreshes the account card and family member list.
- No new live-data folder was added.

## Rev 1.3.5 - 2026-07-06

- Added visible `You` and `Owner` labels to the signed-in member card.
- Loaded `assets/js/member-badges.js` from the main page.
- No new live-data folder was added.
