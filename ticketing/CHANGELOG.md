<!--
File: CHANGELOG.md
File Revision: 1.5.0
Modified: 2026-09-14
History:
1.5.0 - Moved mutable ticketing data out of GitHub tracking and added sample datastore files.
1.4.0 - Redesigned the mobile header, profile, navigation, and dashboard spacing.
1.3.1 - Fixed mobile scrolling and responsive sidebar behavior.
1.3.0 - Added hierarchical ticket categories with stable category references.
1.2.0 - Added CSV import/export tools and sample import template.
1.1.1 - Added password confirmation validation for requester and agent account creation.
1.1.0 - Added passwordless testing mode and user profile/avatar support.
1.0.0 - Established the project revision baseline and documented authentication and directory features.
-->

# Ticketing Changelog

## Project Revision 1.5.0 — 2026-09-14

### Changed
- Removed live `data/users.json`, `data/directory.json`, and `data/tickets.json` from GitHub tracking.
- Added `data/users.json.sample`, `data/directory.json.sample`, and `data/tickets.json.sample` as empty templates.
- Added `ticketing/.gitignore` rules so live runtime JSON files cannot be accidentally recommitted.
- Uploaded profile images under `avatars/` are also treated as runtime content.
- `data/categories.json` remains source-controlled because categories are application configuration rather than live transactional data.

### Why
Previously, creating an agent on Hostinger wrote that account into `data/users.json`, but a later deployment could replace the live file with GitHub's empty `[]` copy. This caused the application to repeatedly offer **Create first agent account** even after an agent had already been created.

### Runtime behavior
- The application continues reading and writing `data/users.json`, `data/directory.json`, and `data/tickets.json` on Hostinger.
- If those files do not exist, PHP creates them automatically the first time data is written.
- Normal Git pulls should leave these ignored/untracked files alone.
- A deployment process that completely deletes the destination directory before copying the repository would still remove runtime files and should not be used without moving runtime storage outside the deployment directory.

## Project Revision 1.4.0 — 2026-09-14

### Changed
- Reworked the mobile top section so it no longer resembles a vertically expanded desktop sidebar.
- Kept the signed-in user's display name on one line next to the avatar, with role and email compactly underneath.
- Placed the Edit Profile control beside the identity card instead of giving it a full-width row.
- Changed agent/requester navigation to a two-column mobile button grid so related actions share rows.
- Combined utility links into a compact row and reduced dashboard spacing.

## Project Revision 1.3.1 — 2026-09-14

### Fixed
- Removed the full-page sticky sidebar behavior on tablet and mobile layouts.
- Added horizontal page overflow protection and improved touch scrolling.

## Project Revision 1.3.0 — 2026-09-14

### Added
- Hierarchical categories stored in `data/categories.json`.
- Stable `categoryId` plus human-readable category paths on tickets.
- Controlled category selection and CSV category round-trip support.

## Project Revision 1.2.0 — 2026-09-14

### Added
- Agent-only CSV import/export and sample CSV template.
- Complete ticket metadata and reply-history round trip.
- Sequential imported ticket numbering based on the live highest ticket number.

## Project Revision 1.1.1 — 2026-09-14

### Changed
- Added matching password confirmation to requester/agent account creation.

## Project Revision 1.1.0 — 2026-09-14

### Added
- Email usernames, display names, avatars, and passwordless test-mode login.

## Project Revision 1.0.0 — 2026-09-14

### Added
- Requester and agent authentication architecture.
- Separate dashboards, public replies, private notes, sortable ticket table, and JSON-backed storage.

### Revision policy
This project does not use revision numbers below `1.0.0`.
