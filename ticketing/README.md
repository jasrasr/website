<!--
File: README.md
File Revision: 1.1.0
Modified: 2026-09-14
History:
1.1.0 - Documented Hostinger-only runtime JSON storage with GitHub sample files and ignore rules.
1.0.0 - Documented authenticated requester/agent portals, persistent directories, and revision policy.
-->

# Ticketing

A lightweight Freshservice-style ticketing system built with PHP, JavaScript, CSS, and JSON file storage.

## Project revision

Current project revision: **1.5.0**  
Modified: **2026-09-14**

Revision policy: project and file revisions start at `1.0.0` and do not use revision numbers below `1.0.0`.

## Authentication

- Email is the username for requesters and agents.
- PHP sessions enforce the signed-in role.
- Test mode currently allows sign-in without requiring a password.
- Requesters can only retrieve tickets associated with their authenticated email address.
- Private agent notes are filtered server-side and never returned to requester sessions.

## Data storage

Live mutable data is intentionally **not tracked by GitHub**:

- `data/tickets.json` — live ticket database on Hostinger only.
- `data/users.json` — live requester and agent accounts on Hostinger only.
- `data/directory.json` — live requester and agent directory on Hostinger only.

GitHub contains empty templates instead:

- `data/tickets.json.sample`
- `data/users.json.sample`
- `data/directory.json.sample`

`ticketing/.gitignore` excludes the live JSON files so normal Git pulls do not overwrite them. PHP creates the live files automatically when data is first written, so after deployment the first-agent setup can create `users.json` and `directory.json` directly on Hostinger.

Configuration data remains tracked in GitHub:

- `data/categories.json` — hierarchical ticket categories.
- `data/.htaccess` — blocks direct browser access to the data directory.

## Requester portal

- Dashboard and My Tickets list.
- Submit tickets.
- View public replies.
- Send public replies to agents.

## Agent portal

- Dashboard across all tickets.
- Sort/filter/search ticket table.
- Edit ticket metadata.
- Public replies and private agent notes.
- Persistent requester and agent dropdowns.
- Add new requesters and agents.
- CSV import/export tools.

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
- `ticketing/data/` writable by PHP.
- Apache/Hostinger configuration that honors `.htaccess`.
- Deployment must preserve ignored/untracked runtime files. Avoid a deployment mode that deletes the entire destination directory before copying the repository.

## First setup

1. Deploy the application.
2. Open the application and choose Agent.
3. Choose **Create first agent account**.
4. The application creates Hostinger-only `data/users.json` and `data/directory.json` as needed.
5. Those live files remain untracked and are not replaced by later normal Git pulls.

## Security notes

This remains a lightweight JSON-backed application. Before production use with sensitive ticket data, test mode should be disabled and password reset, MFA, lockout/rate limiting, CSRF protection, and stronger centralized identity controls should be considered.
