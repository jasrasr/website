<!--
File: README.md
File Revision: 1.3.0
Modified: 2026-09-14
History:
1.3.0 - Reframed runtime storage and deployment documentation to be fork- and host-agnostic.
1.2.0 - Documented web category management, stable category IDs, and runtime category storage.
1.1.0 - Documented runtime JSON storage, sample files, and deployment preservation requirement.
1.0.0 - Documented authenticated requester/agent portals, persistent directories, and revision policy.
-->

# Ticketing

A lightweight Freshservice-style ticketing system built with PHP, JavaScript, CSS, and JSON file storage.

## Project revision

Current project revision: **1.6.1**  
Modified: **2026-09-14**

Revision policy: project and file revisions start at `1.0.0` and do not use revision numbers below `1.0.0`.

## Fork-friendly architecture

The application is intentionally independent of any specific GitHub account, repository owner, domain, or hosting provider.

The repository contains the **application source and starter/sample data**. Each deployed copy creates and maintains its own **instance-local runtime data**. A fork therefore becomes a completely separate installation with its own users, tickets, directory, categories, and uploaded avatars.

No live installation needs to connect back to the original repository or original hosting account in order to run.

## Authentication

- Email is the username for requesters and agents.
- PHP sessions enforce the signed-in role.
- Test mode currently allows sign-in without requiring a password.
- Requesters can only retrieve tickets associated with their authenticated email address.
- Private agent notes are filtered server-side and never returned to requester sessions.

## Data storage

Live mutable data is intentionally **not tracked by Git**:

- `data/tickets.json` — this installation's ticket database.
- `data/users.json` — this installation's requester and agent accounts.
- `data/directory.json` — this installation's requester and agent directory.
- `data/categories.json` — this installation's editable category hierarchy.

The repository contains starter/sample data instead:

- `data/tickets.json.sample`
- `data/users.json.sample`
- `data/directory.json.sample`
- `data/categories.json.sample` — populated starter IT-helpdesk category hierarchy.

`.gitignore` excludes the live JSON files so source updates do not overwrite instance data. PHP creates live users/directory/ticket files as data is first written. The category datastore is initialized automatically from `categories.json.sample` when categories are first needed.

`data/.htaccess` protects the data directory on Apache-compatible hosts. Other web servers should apply an equivalent rule that prevents direct HTTP access to runtime JSON files.

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
- `INSTALL.md` — generic deployment/fork instructions.
- `project.json` — project revision and per-file revision/history manifest.
- `CHANGELOG.md` — project release history.
- `TODO.md` — future feature and development backlog.

## Hosting requirements

- PHP 8.1+
- PHP sessions enabled.
- The `data/` directory writable by PHP.
- The `avatars/` directory writable by PHP when profile images are used.
- Direct web access to runtime JSON files must be blocked.
- Deployment must preserve ignored/untracked runtime files. Avoid a deployment mode that deletes the entire application directory before each update unless runtime storage is placed outside that directory.

The application does not otherwise depend on Hostinger, GitHub Pages, a particular domain name, or the original repository owner.

## First setup

1. Fork, clone, download, or otherwise copy the application source to a PHP-capable server.
2. Ensure `data/` is writable by PHP.
3. Open the application and choose **Agent**.
4. Choose **Create first agent account**.
5. The application creates the installation's own `data/users.json` and `data/directory.json` as needed.
6. The category hierarchy is automatically copied from `data/categories.json.sample` into the installation's own `data/categories.json` when first needed.
7. Live files remain untracked and should be backed up separately from source code.

See `INSTALL.md` for deployment details.

## Security notes

This remains a lightweight JSON-backed application. Before production use with sensitive ticket data, test mode should be disabled and password reset, MFA, lockout/rate limiting, CSRF protection, and stronger centralized identity controls should be considered.
