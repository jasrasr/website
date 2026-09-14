<!--
File: CHANGELOG.md
File Revision: 1.0.0
Modified: 2026-09-14
History:
1.0.0 - Established the project revision baseline and documented authentication and directory features.
-->

# Ticketing Changelog

## Project Revision 1.0.0 — 2026-09-14

### Added
- Requester login with email and password.
- Requester self-registration.
- Agent login with email and password.
- First-agent bootstrap flow when no agent account exists yet.
- PHP session enforcement for authenticated access.
- Server-side role authorization for requester and agent operations.
- Requesters can only retrieve and reply to tickets associated with their authenticated email address.
- Private agent notes are never returned to requester sessions.
- Persistent requester and agent directory stored in `data/directory.json`.
- Persistent login accounts stored in `data/users.json` using PHP password hashes.
- Agent ticket form requester dropdown populated from the persistent requester directory.
- Agent assignment dropdown populated from the persistent agent directory.
- `+ Add new requester...` and `+ Add new agent...` dropdown entries.
- New directory entries are saved permanently to JSON and appear in future dropdowns.
- Optional password when an agent creates a directory entry; providing one creates login access for that person.
- Signed-in user identity and role displayed in the sidebar.
- Sign-out support.
- Project and file revision policy requiring all revisions to begin at `1.0.0` or higher.

### Existing core features included in the 1.0.0 baseline
- Separate requester-facing and agent-facing dashboards.
- Requester ticket list limited to the authenticated requester.
- Agent dashboard across all tickets.
- Sortable agent ticket table by ticket number, requester, status, priority, assigned agent, created date, and updated date.
- Search and filtering by ticket content, status, priority, and agent.
- Agent editing of ticket metadata.
- Public replies visible to requester and agents.
- Private notes visible only to agents.
- Requester replies treated as public.
- JSON-backed ticket storage with file locking.
- Project revision and modified date displayed in the application.
- Links to `CHANGELOG.md` and `TODO.md`.

### Revision policy
This project does not use revision numbers below `1.0.0`. Early prototype work is treated as pre-release development and is not part of the numbered revision history.

### Still planned
- Email delivery and inbound email-to-ticket support.
- Password reset/recovery.
- Agent administration and account disable/reset controls.
- Attachments.
- SLA and business-hours calculations.
- Automation rules, reporting, assets, knowledge base, and additional integrations.
