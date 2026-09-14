<!--
File: CHANGELOG.md
File Revision: 1.4.0
Modified: 2026-09-14
History:
1.4.0 - Redesigned the mobile header, profile, navigation, and dashboard spacing.
1.3.1 - Fixed mobile scrolling and responsive sidebar behavior.
1.3.0 - Added hierarchical ticket categories with stable category references.
1.2.0 - Added CSV import/export tools and sample import template.
1.1.1 - Added password confirmation validation for requester and agent account creation.
1.1.0 - Added passwordless testing mode and user profile/avatar support.
1.0.0 - Established the project revision baseline and documented authentication and directory features.
-->

# Ticketing Changelog

## Project Revision 1.4.0 — 2026-09-14

### Changed
- Reworked the mobile top section so it no longer resembles a vertically expanded desktop sidebar.
- Kept the signed-in user's display name on one line next to the avatar, with role and email compactly underneath.
- Placed the Edit Profile control beside the identity card instead of giving it a full-width row.
- Changed agent/requester navigation to a two-column mobile button grid so related actions share rows.
- Kept New Ticket visually prominent while placing CSV Import / Export alongside the other agent navigation options.
- Combined View Changelog, Future Features, and Sign out into one compact utility row.
- Reduced mobile header, navigation, title, subtitle, statistics-card, and general dashboard spacing.
- Kept Agent Dashboard / page title and Refresh on one compact row.
- Added extra small-screen handling for narrow phones while preserving readable email/profile information.

## Project Revision 1.3.1 — 2026-09-14

### Fixed
- Removed the full-page sticky sidebar behavior on tablet and mobile layouts.
- Profile, navigation, changelog/future-feature links, and sign-out now scroll normally with the page instead of remaining pinned over the ticket content.
- Added horizontal page overflow protection so wide ticket tables do not interfere with normal vertical scrolling.
- Enabled smooth touch scrolling inside horizontally scrollable ticket tables.
- Improved dialog scrolling on mobile by using dynamic viewport height where supported.
- Tightened signed-in profile text wrapping so long display names and email addresses do not force the mobile layout wider than the viewport.

## Project Revision 1.3.0 — 2026-09-14

### Added
- Hierarchical category datastore at `data/categories.json`.
- Stable `categoryId` references on ticket records.
- Human-readable category path snapshot on each ticket, such as `Hardware > Printer`.
- Controlled category dropdown in the ticket form instead of free-text category entry.
- Category API that exposes the hierarchy and a flattened path list to authenticated users.
- Initial category hierarchy for General, Hardware, Software, Access & Accounts, Network, Email & Collaboration, and Security.

### Category behavior
- New and edited tickets resolve the selected category against `data/categories.json`.
- Tickets store both `categoryId` and `category` so reporting can use the stable ID while the readable path remains visible even if labels later change.
- Existing legacy tickets that only contain a text category continue to display; when edited, the system attempts to match the old path and otherwise falls back to General.
- CSV export now includes both `categoryId` and `category`.
- CSV import prefers `categoryId`, can fall back to the category path, and defaults invalid/missing values to General.

### Existing ticket metadata
- Internal ticket ID
- Ticket number
- Subject
- Description
- Requester display name
- Requester email
- Status
- Priority
- Category ID
- Category path
- Assigned agent
- Source
- Created timestamp
- Updated timestamp
- Comment/reply history, including comment ID, author, role, public/private visibility, body, and timestamp

## Project Revision 1.2.0 — 2026-09-14

### Added
- Agent-only CSV Import / Export page at `csv.php`.
- Downloadable CSV export of the complete ticket queue.
- Downloadable `sample-ticket-import.csv` template.
- CSV import support for ticket metadata, timestamps, and complete comment/reply history.
- `commentsJson` column for round-tripping public replies and private agent notes.
- Row-level validation and sequential ticket numbering based on the live highest ticket number.

### Ticket numbering
- Imported CSV ticket numbers are ignored to avoid duplicates.
- The importer locks `data/tickets.json`, finds the highest existing ticket number, and assigns imported tickets sequentially from the next number.

## Project Revision 1.1.1 — 2026-09-14

### Changed
- Requester account creation, first-agent creation, Add Requester, and Add Agent require matching password confirmation when a password is supplied.
- In test mode, both password fields may remain blank.

## Project Revision 1.1.0 — 2026-09-14

### Added
- Email address as username.
- Display names and avatar/profile pictures.
- Passwordless test-mode sign-in while retaining PHP sessions and role authorization.

### Storage clarification
- Tickets are stored together in `data/tickets.json`.
- Login accounts are stored in `data/users.json`.
- Requester/agent directory entries are stored in `data/directory.json`.
- Categories are stored in `data/categories.json`.
- Profile pictures are image files under `avatars/`.

## Project Revision 1.0.0 — 2026-09-14

### Added
- Requester and agent authentication architecture.
- Separate requester and agent dashboards.
- Public replies and private agent notes.
- Sortable/filterable agent ticket table.
- JSON-backed storage and project/file revision tracking.

### Revision policy
This project does not use revision numbers below `1.0.0`. Early prototype work is treated as pre-release development and is not part of the numbered revision history.
