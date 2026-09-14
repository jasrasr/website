<!--
File: README.md
File Revision: 1.0.0
Modified: 2026-09-14
History:
1.0.0 - Documented authenticated requester/agent portals, persistent directories, and revision policy.
-->

# Ticketing

A lightweight Freshservice-style ticketing system built with PHP, JavaScript, CSS, and JSON file storage.

## Project revision

Current project revision: **1.0.0**  
Modified: **2026-09-14**

Revision policy: project and file revisions start at `1.0.0` and do not use revision numbers below `1.0.0`.

## Authentication

- Requesters sign in with email and password.
- Requesters can self-register.
- Agents sign in with email and password.
- When no agent account exists, the Agent login screen exposes a one-time **Create first agent account** option.
- PHP sessions enforce the signed-in role.
- Requesters can only retrieve tickets associated with their authenticated email address.
- Private agent notes are filtered server-side and never returned to requester sessions.

## Requester portal

- Requester dashboard with Open, Pending, Resolved, Closed, and Total counts.
- My Tickets list.
- Submit new tickets.
- View public replies.
- Send replies to agents.
- Requester replies are always public.

## Agent portal

- Dashboard across all tickets.
- All Tickets table.
- Sort by ticket number, requester, status, priority, assigned agent, created date, and updated date.
- Search and filter tickets.
- Edit ticket subject, description, requester, email, status, priority, category, assigned agent, and source.
- Public replies visible to requesters.
- Private notes visible only to agents.
- Persistent requester dropdown.
- Persistent agent assignment dropdown.
- `+ Add new requester...` and `+ Add new agent...` entries save new directory records to JSON.
- When adding a person, an optional 8+ character password also creates login access for that person.

## Data files

- `data/tickets.json` — ticket database.
- `data/users.json` — requester and agent login accounts with PHP password hashes.
- `data/directory.json` — persistent requester and agent directory.
- `data/.htaccess` — blocks direct web access to the data directory.

## Source files

- `index.php` — application markup, JSON API, sessions, authentication, authorization, and persistence.
- `app.js` — login UI, dashboards, ticket interactions, sorting/filtering, directory dropdowns, and dialogs.
- `styles.css` — responsive application styles.
- `project.json` — project revision and per-file revision/history manifest.
- `CHANGELOG.md` — project release history.
- `TODO.md` — future feature and development backlog.

## Hosting requirements

- PHP 8.1+
- PHP sessions enabled.
- `password_hash()` / `password_verify()` support.
- `ticketing/data/` writable by PHP.
- Apache/Hostinger configuration that honors `.htaccess`.

## First setup

1. Open the application.
2. Select **Agent** on the login screen.
3. If no agent exists, choose **Create first agent account**.
4. Enter a name, email, and password of at least 8 characters.
5. The account is saved to `data/users.json` and the person is added to `data/directory.json`.
6. Additional agents or requesters can be added from ticket dropdowns after signing in as an agent.

## Security notes

This is intentionally a lightweight JSON-backed application. Passwords are hashed before storage and ticket authorization is enforced server-side, but features such as password reset, MFA, lockout/rate limiting, CSRF tokens, and centralized identity are still future work and should be added before treating the system as a production help desk exposed to untrusted users.
