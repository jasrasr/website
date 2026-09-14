<!--
File: INSTALL.md
File Revision: 1.0.0
Modified: 2026-09-14
History:
1.0.0 - Added generic host-agnostic installation and fork deployment instructions.
-->

# Ticketing Installation

This application is designed so each fork or deployment is an independent instance. Git is used to distribute source code and starter/sample data; live runtime data belongs only to the deployed instance.

## Requirements

- PHP 8.1 or newer
- PHP sessions enabled
- Write access for PHP to `data/`
- Write access for PHP to `avatars/` when profile pictures are used
- A web server configured to prevent direct HTTP access to runtime JSON files

Apache-compatible hosts can use the included `data/.htaccess`. For nginx, IIS, or another web server, create an equivalent deny rule for the `data/` directory.

## Install from a fork or clone

1. Fork or clone the repository to your own account or server.
2. Deploy the `ticketing/` directory to a PHP-capable web root.
3. Confirm PHP can write to `ticketing/data/`.
4. Open `index.php` in a browser.
5. Choose **Agent** and create the first agent account.
6. The installation creates its own runtime JSON files as needed.
7. The starter category hierarchy is copied from `data/categories.json.sample` into the instance-local `data/categories.json` automatically.

## Runtime data

These files belong to the deployed installation and are intentionally ignored by Git:

- `data/users.json`
- `data/directory.json`
- `data/tickets.json`
- `data/categories.json`
- uploaded files under `avatars/`

These files should be backed up separately from source control.

## Repository starter files

The repository contains:

- `data/users.json.sample`
- `data/directory.json.sample`
- `data/tickets.json.sample`
- `data/categories.json.sample`

They provide empty/default data for a new instance without tying that instance to the original repository owner's live data.

## Updating the application

A normal Git pull or source-code update should leave ignored runtime files untouched.

Do not use a deployment method that deletes the entire destination directory before every deployment unless runtime data has first been moved to persistent storage outside that directory. A destructive redeploy could otherwise erase tickets, accounts, categories, and avatars.

## Independence from the original repository

A fork does not need access to the original GitHub account, original domain, or original hosting provider after the source is copied. All application URLs and runtime file paths are local/relative to the deployed instance.

Each installation therefore has its own:

- first agent and subsequent users
- requester/agent directory
- tickets and replies
- category hierarchy
- profile avatars
- CSV imports and exports

## Production notes

Before exposing an installation to untrusted users, disable passwordless test mode and review authentication hardening, CSRF coverage, rate limiting, backups, file permissions, and web-server protections for runtime data.
