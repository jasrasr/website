<!--
Project: Family GPS Tracker
File: CHANGELOG.md
Revision: 1.4.1
Description: Project revision history for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-06
-->

# Family GPS Tracker Changelog

Current Project Revision: **1.4.1**

## Rev 1.4.1 - 2026-07-06

- Removed the duplicate temporary group invite-code display from the Groups / Circles card.
- The full invite code now appears only in the main Invite Code card.
- Creating a new group still switches to that group and places the one-time code in the Invite Code card for copying.
- Added no new live-data folder.

## Rev 1.4.0 - 2026-07-06

- Added multi-group/circle support using the existing family JSON storage as group records.
- Added `groups.php` for listing groups, creating a new group, joining another group by invite code, and switching the active group.
- Added `assets/js/groups.js` for the Groups / Circles UI.
- Updated the main app so the active group controls the map, member list, location updates, and invite-code regeneration.
- Added per-group owner/member role handling through group `memberRoles` and `memberIds` metadata.
- Existing users are treated as members of their current family/group and can create or join additional groups.
- Added no new live-data folder.

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
