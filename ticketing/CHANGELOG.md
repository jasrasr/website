<!--
File: CHANGELOG.md
File Revision: 1.7.1
Modified: 2026-09-15
History:
1.7.1 - Compacted category management into one-line rows with on-demand editing.
1.7.0 - Added searchable requester/category autocomplete with usage-aware suggestions.
1.6.2 - Fixed iOS form-focus zoom and mobile dashboard viewport overflow after first-agent setup.
1.6.1 - Clarified fork/host independence and added generic installation guidance.
1.6.0 - Added web category management and moved live categories to instance-local runtime storage.
1.5.0 - Moved mutable ticketing data out of Git tracking and added sample datastore files.
1.4.0 - Redesigned the mobile header, profile, navigation, and dashboard spacing.
1.3.1 - Fixed mobile scrolling and responsive sidebar behavior.
1.3.0 - Added hierarchical ticket categories with stable category references.
1.2.0 - Added CSV import/export tools and sample import template.
1.1.1 - Added password confirmation validation for requester and agent account creation.
1.1.0 - Added passwordless testing mode and user profile/avatar support.
1.0.0 - Established the project revision baseline and documented authentication and directory features.
-->

# Ticketing Changelog

## Project Revision 1.7.1 — 2026-09-15

### Changed
- Reworked **Manage Categories** so each category is a compact single row instead of permanently displaying all editing controls.
- Kept category name and hierarchy path visible at a glance while moving the stable ID and detailed controls into an expandable **Edit** area.
- Kept move-up and move-down controls directly on each row for fast hierarchy ordering.
- Rename, change-parent, and enable/disable controls now appear only when that category is being edited.
- Opening one category editor automatically closes another open editor.
- Collapsed **Add Category** into an expandable panel so it no longer consumes screen space when not being used.
- Reduced mobile padding, row height, action-button size, and hierarchy indentation for iPhone portrait layouts.

### Result
The category list now fits substantially more categories on a phone screen while preserving the same stable-ID, rename, re-parent, ordering, and enable/disable behavior.

## Project Revision 1.7.0 — 2026-09-15

### Added
- Replaced the long native requester selection workflow with a searchable autocomplete field.
- Requester search matches both display name and email address.
- Opening requester search without typing prioritizes frequently used requesters, with recent ticket activity used as a secondary ranking signal.
- **+ Add new requester…** remains available directly from the requester suggestions.
- Replaced the long hierarchical category dropdown workflow with searchable autocomplete.
- Category search matches any portion of the category name or full hierarchy path, so searches such as `printer`, `battery`, `Teams`, or `laptop` find the appropriate category immediately.
- Category suggestions display the specific category name prominently and its parent hierarchy as smaller secondary text rather than repeating a long `Parent > Child > Grandchild` string as the primary label.
- Category suggestions use existing ticket history to prioritize frequently/recently used categories when the field is opened without a search term.
- Added keyboard support for Arrow Up/Down, Enter, and Escape.
- Added `autocomplete.css` with mobile-specific suggestion presentation that avoids the large native iOS select sheet.

### Data behavior
- The autocomplete UI still writes the existing requester email and stable `categoryId` values to the ticket API.
- No ticket schema changes were required.
- Requester popularity and category popularity are calculated from the local ticket history; no new tracking datastore is required.

## Project Revision 1.6.2 — 2026-09-15

### Fixed
- Prevented iOS Safari/Chrome from automatically zooming the first-agent/login forms by forcing mobile text inputs, selects, and textareas to a minimum 16px font size.
- Fixed the post-registration dashboard appearing enlarged and horizontally panned, which could clip the left side of **Agent Dashboard**, the right side of **Refresh**, and project-footer content.
- Added stricter width/max-width containment to the application shell, sidebar, main content, dashboard statistics, panels, tables, and footer.
- Added `-webkit-text-size-adjust: 100%` / `text-size-adjust: 100%` to prevent mobile text inflation from changing the intended layout.
- Kept wide ticket tables horizontally scrollable inside their own container instead of allowing the entire page to become wider than the viewport.
- Added safer flex sizing to the page-title area so the title/subtitle and Refresh button remain inside the screen.

### Root cause
On iOS, form controls rendered below 16px can trigger browser focus zoom. The account-creation form inherited the smaller label font, so after creating the first agent the browser could remain zoomed/panned when the login screen was hidden and the dashboard was displayed. The layout itself was then rendered correctly but viewed through a magnified visual viewport.

## Project Revision 1.6.1 — 2026-09-14

### Changed
- Reframed the application as host-agnostic and fork-friendly rather than Hostinger-specific.
- Clarified that Git is used only for application source and starter/sample data.
- Defined users, tickets, directory entries, categories, and avatars as **instance-local runtime data** owned by each deployment.
- Added `INSTALL.md` with generic deployment instructions for forks and non-original hosts.
- Removed documentation language implying that a fork needs the original GitHub account, original domain, or original hosting provider.

### Fork behavior
- A fork can be deployed independently and creates its own users, first agent, tickets, categories, directory, and avatars.
- Live runtime JSON is ignored by Git and does not need to be pushed back to the original repository.
- Sample files provide clean starting data for every new installation.
- No live installation needs to connect to the original repository owner after the source has been copied.

## Project Revision 1.6.0 — 2026-09-14

### Added
- Agent-only **Manage Categories** page at `categories.php`.
- Add a top-level category or add a category beneath any existing category.
- Rename categories without changing their stable IDs.
- Move categories to a different parent while preserving the category ID.
- Enable or disable categories instead of deleting historical references.
- Move categories up or down within their current sibling group.
- Display each category's current hierarchy path and permanent category ID.
- CSRF protection on category-management changes.

### Category ID behavior
- Category IDs are permanent references used by tickets and reporting.
- Existing seeded IDs are descriptive, such as `hardware-computer-laptop`.
- New category IDs are initially generated from the category name for readability; a short suffix is added if necessary to avoid a duplicate ID.
- Renaming or moving a category does **not** change its ID.
- This means a category can move from one part of the hierarchy to another without breaking tickets that already reference it.

### Runtime category storage
- Live `data/categories.json` is instance-local runtime data and is ignored by Git, matching users, directory, and tickets.
- The repository contains `data/categories.json.sample`, populated with the starter IT-helpdesk hierarchy.
- If live `categories.json` does not exist, `index.php`, `csv.php`, or `categories.php` automatically initializes it from the sample file.
- The seeded hierarchy therefore appears automatically on a new deployment but subsequent web edits are not overwritten by normal Git pulls.

## Project Revision 1.5.0 — 2026-09-14

### Changed
- Removed live `data/users.json`, `data/directory.json`, and `data/tickets.json` from Git tracking.
- Added `data/users.json.sample`, `data/directory.json.sample`, and `data/tickets.json.sample` as empty templates.
- Added `ticketing/.gitignore` rules so live runtime JSON files cannot be accidentally recommitted.
- Uploaded profile images under `avatars/` are also treated as runtime content.

### Why
Previously, creating an agent wrote that account into `data/users.json`, but a later deployment could replace the live file with the repository's empty `[]` copy. This caused the application to repeatedly offer **Create first agent account** even after an agent had already been created.

### Runtime behavior
- The application continues reading and writing instance-local live JSON.
- If runtime files do not exist, PHP creates or initializes them when needed.
- Normal Git pulls should leave ignored/untracked runtime files alone.
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
- Hierarchical categories with stable `categoryId` plus human-readable category paths on tickets.
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
