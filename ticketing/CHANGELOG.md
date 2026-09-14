<!--
File: CHANGELOG.md
File Revision: 1.1.0
Modified: 2026-09-14
History:
1.1.0 - Added passwordless testing mode and user profile/avatar support.
1.0.0 - Established the project revision baseline and documented authentication and directory features.
-->

# Ticketing Changelog

## Project Revision 1.1.0 — 2026-09-14

### Added
- Email address is the login username for requesters and agents.
- Optional display name separate from the login email.
- Profile editor for changing display name.
- Profile-picture/avatar upload for requester and agent accounts.
- Avatar files are stored under `ticketing/avatars/`; the relative avatar path is stored with the user record.
- Avatars are limited to PNG, JPEG, WEBP, or GIF files up to 2 MB.
- Test-mode login that keeps PHP sessions and role authorization but temporarily bypasses password verification.
- New requesters and agents created while testing can log in using their email without a password.
- Visible testing-mode notice on the login screen.

### Changed
- Requester and agent identity now uses `displayName` for visible names and email for the account username.
- New directory entries automatically receive a login account during test mode, so they can immediately be used for role testing.
- Ticket requester names and reply authors use the account display name.
- Project revision advanced to `1.1.0`.

### Storage clarification
- Tickets continue to be stored together in one file: `data/tickets.json`.
- The file contains a JSON array with one object per ticket, including that ticket's comments/replies.
- Login accounts are stored separately in `data/users.json`.
- Requester/agent directory entries are stored in `data/directory.json`.
- Uploaded profile pictures are stored as image files under `avatars/`, not embedded into the ticket JSON.

### Testing warning
`TEST_MODE` is currently enabled in `index.php`. Password checks are intentionally bypassed while it is enabled. Disable test mode before using the site with real or sensitive ticket data.

## Project Revision 1.0.0 — 2026-09-14

### Added
- Requester login with email and password architecture.
- Requester self-registration.
- Agent login and first-agent bootstrap flow.
- PHP session enforcement for authenticated access.
- Server-side role authorization for requester and agent operations.
- Requesters can only retrieve and reply to tickets associated with their authenticated email address.
- Private agent notes are never returned to requester sessions.
- Persistent requester and agent directory stored in `data/directory.json`.
- Persistent login accounts stored in `data/users.json`.
- Agent ticket form requester dropdown populated from the persistent requester directory.
- Agent assignment dropdown populated from the persistent agent directory.
- `+ Add new requester...` and `+ Add new agent...` dropdown entries.
- Public replies and private agent notes.
- Sortable/filterable agent ticket table.
- Project and file revision policy requiring all revisions to begin at `1.0.0` or higher.

### Revision policy
This project does not use revision numbers below `1.0.0`. Early prototype work is treated as pre-release development and is not part of the numbered revision history.
