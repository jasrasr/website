<!--
File: CHANGELOG.md
File Revision: 0.1.0
Modified: 2026-09-14
History:
0.1.0 - Created project changelog covering initial release through project rev 0.2.0.
-->

# Ticketing Changelog

## Project Revision 0.2.0 — 2026-09-14

### Added
- Separate requester-facing and agent-facing dashboards.
- Requester email lookup for loading submitted tickets.
- Requester dashboard showing only tickets submitted with the selected requester email.
- Server-side requester filtering so private notes are not returned to requester views.
- Agent dashboard with all tickets.
- Sortable agent ticket table by ticket number, requester, status, priority, assigned agent, created date, and updated date.
- Agent filtering by search text, status, priority, and assigned agent.
- Public replies visible to requesters and agents.
- Private notes visible only in the agent response data/UI.
- Requester replies, always treated as public.
- Agent editing for ticket subject, description, requester, email, priority, status, category, assigned agent, and source.
- Project revision and modified date displayed on the application dashboard.
- Dashboard links to this changelog and `TODO.md`.
- `project.json` manifest tracking project revision plus per-file revision/history.

### Changed
- Ticket creation now requires requester email.
- New tickets default to Open status and Portal source.
- Existing legacy comments without an explicit visibility value are treated as public.

### Known limitations
- Agent access is not authenticated yet. The agent portal is a prototype view and should not be considered secure for production use.
- Requester identity is currently based on entered email address and is not authenticated.
- Email notifications are not implemented yet.
- Attachments are not implemented yet.

## Project Revision 0.1.1 — 2026-09-14

### Fixed
- Corrected the PUT response so the API returns the updated ticket instead of a null ticket object.

## Project Revision 0.1.0 — 2026-09-14

### Added
- Initial PHP/JavaScript/CSS ticketing application.
- JSON-backed ticket storage.
- Dashboard ticket counts.
- Ticket create/edit flow.
- Statuses: Open, Pending, Resolved, Closed.
- Priorities: Low, Medium, High, Urgent.
- Requester, category, assignment, description, and comment fields.
- Search and filtering.
- JSON file locking during writes.
- `.htaccess` protection for the data folder.
