<!--
File: README.md
File Revision: 1.2.0
Modified: 2026-09-14
History:
1.2.0 - Documented web category management, stable category IDs, and Hostinger-only category runtime storage.
1.1.0 - Documented Hostinger-only runtime JSON storage with GitHub sample files and ignore rules.
1.0.0 - Documented authenticated requester/agent portals, persistent directories, and revision policy.
-->

# Ticketing

A lightweight Freshservice-style ticketing system built with PHP, JavaScript, CSS, and JSON file storage.

## Project revision

Current project revision: **1.6.0**  
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
- `data/categories.json` — live editable category hierarchy on Hostinger only.

GitHub contains starter/sample data instead:

- `data/tickets.json.sample`
- `data/users.json.sample`
- `data/directory.json.sample`
- `data/categories.json.sample` — populated starter IT-helpdesk category hierarchy.

`ticketing/.gitignore` excludes the live JSON files so normal Git pulls do not overwrite them. PHP creates live users/directory/ticket files when data is first written. The category datastore is initialized automatically from `categories.json.sample` when `index.php`, `csv.php`, or `categories.php` first needs categories.

`data/.htaccess` blocks direct browser access to the data directory.

## Category management

Agents can open **Manage Categories** from the agent navigation or browse directly to `categories.php`.

The category manager supports:

- Add top-level categories.
- Add child/subcategories under any category.
- Rename a category.
- Move a category to a different parent.
- Enable or disable a category.
- Move a category up or down within its sibling group.
- View the current hierarchy path and permanent category ID.

Category IDs are stable references. Existing seeded IDs are descriptive (for example `hardware-computer-laptop`). New IDs are initially generated from the name for readability and receive a short suffix if necessary to avoid duplicates. After creation, moving or renaming the category does **not** change the ID. Tickets therefore continue referencing the same category even when the visible hierarchy changes.

Disabled categories are omitted from ticket category selection but remain in the category datastore so historical ticket references are preserved.

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
- Web category management.

## Source files

- `index.php` — application markup, JSON API, sessions, authentication, authorization, persistence, and runtime category initialization.
- `app.js` — login UI, dashboards, ticket interactions, sorting/filtering, directory dropdowns, and dialogs.
- `styles.css` — responsive application styles.
- `csv.php` — CSV import/export tools.
- `categories.php` — agent-only category editor.
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
5. The category hierarchy is automatically copied from `data/categories.json.sample` into Hostinger-only `data/categories.json` when first needed.
6. Those live files remain untracked and are not replaced by later normal Git pulls.

## Security notes

This remains a lightweight JSON-backed application. Before production use with sensitive ticket data, test mode should be disabled and password reset, MFA, lockout/rate limiting, CSRF protection, and stronger centralized identity controls should be considered.
