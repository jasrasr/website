<!--
File: README.md
File Revision: 0.2.0
Modified: 2026-09-14
History:
0.2.0 - Documented requester/agent portals, reply visibility, revision tracking, and backlog.
0.1.0 - Initial project documentation.
-->

# Ticketing

Project revision **0.2.0** — modified **2026-09-14**.

A lightweight Freshservice-style ticketing system built with PHP, JavaScript, CSS, and JSON file storage.

## Current features

### Requester portal
- Requester dashboard showing only tickets submitted with the entered requester email
- My Tickets view
- Submit new tickets
- View ticket status, priority, assigned agent, and conversation
- Submit public replies
- Private agent notes are stripped from requester API responses and are not sent to the requester browser

### Agent portal
- Dashboard across all tickets
- All Tickets queue
- Search and filtering by status, priority, and assigned agent
- Sort ticket table by ticket number, requester, status, priority, agent, created date, or updated date
- Edit ticket subject, description, requester, requester email, status, priority, category, assigned agent, and source
- Post public replies visible to requesters
- Post private notes visible only to agents

### General
- Statuses: Open, Pending, Resolved, Closed
- Priorities: Low, Medium, High, Urgent
- JSON persistence in `data/tickets.json`
- File locking during JSON writes
- Direct browser access to the ticket data folder blocked by `.htaccess`
- Project revision and modified date shown in the application footer
- Dashboard links to `CHANGELOG.md` and `TODO.md`
- Per-file revisions and revision histories tracked in `project.json`

## Important security status

Authentication is **not implemented yet**. Requester identity is currently based on an entered email address, and the agent portal is not protected by login. This is appropriate for development/testing but not yet for production use with sensitive ticket data.

Authentication and role enforcement are the highest-priority future feature in `TODO.md`.

## Files

- `index.php` — page markup and JSON API endpoints
- `app.js` — requester/agent UI behavior, sorting, filtering, and ticket workflow
- `styles.css` — responsive interface styling
- `project.json` — project revision, modified date, and per-file revision history
- `CHANGELOG.md` — overall project revision history
- `TODO.md` — future features and development backlog
- `data/tickets.json` — ticket datastore
- `data/.htaccess` — blocks direct web access to ticket JSON data

## Revision policy

The overall application uses a project revision such as `0.2.0`. Source/documentation files also carry their own file revision and modified date. `project.json` is the central manifest for these revisions and includes each file's change history.

Ticket data changes inside `data/tickets.json` do not increment the source-file revision because normal ticket activity changes that file continuously.

## Hosting requirements

- PHP 8.1+
- `ticketing/data/` must be writable by PHP
- Apache/Hostinger hosting should honor `.htaccess`

## API

The browser uses `index.php?api=1`.

- `GET &portal=agent` — all ticket data, including private notes
- `GET &portal=requester&email=user@example.com` — tickets for that requester with private notes removed
- `POST` — create a ticket
- `PUT` — update ticket metadata and/or add a public/private response

## Backlog

See [`TODO.md`](TODO.md) for the maintained future-features list. Major future areas include authentication, email integration, attachments, SLA management, automation, reporting, asset linking, knowledge base, service catalog, Microsoft 365/Entra integration, and a PowerShell API helper.
