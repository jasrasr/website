# Preserve hosting data during account migration

Account linking preserves existing Finances budget files and legacy credentials. It updates central account mappings and adds missing project membership. It does not combine, replace, rename or create budgets. A duplicate mapping is rejected. Other projects must be reviewed individually before enabling migration.

## Live files versus examples

| Path | Purpose | Deployment rule |
| --- | --- | --- |
| `user-management/data/` | Real users, password hashes, project permissions, links, rate limits, locks | Preserve all live contents; Git tracks only `.htaccess` |
| `user-management/config.local.php` | Server-specific identity settings | Never replace from a template |
| `finances/data/` | Existing budgets, link backups, locks and older mappings | Preserve all live contents; Git tracks only `.htaccess` |
| `finances/config.private.php` | Live credentials/configuration override, loaded last | Ignored by Git; preserve on hosting |
| `finances/config.local.php` | Historical configuration already tracked by Git | Legacy hazard: preserve on server; repository guard prohibits editing/deleting it in this migration |
| `1-Framework/config/config.php` | Live framework/provider settings | Ignored by Git; preserve on hosting |
| `user-management/examples/` and `*.example.php` | Documentation-only samples | Safe to deploy under their example names; never automatically copy over runtime files |

The tracked `.htaccess` files are also folder placeholders and deny HTTP access. Empty `directory.json`, `users.json`, budgets and backup files are deliberately absent from Git. Sample JSON files are outside the runtime directories. New central installations use `setup.php`, which refuses to replace an existing user directory. Provisioning/linking requires an explicit administrator action; deployment does not run either operation.

## Before the next hosting deployment

1. Back up the current central data, Finances data and live configuration **before deploying any code**. Store a restorable hosting backup outside the publicly served directory. If `data_path` is customized, include that actual location. Keep the original backup until each migrated user and their permissions/data are verified.
2. Inspect the actual Hostinger deployment/webhook configuration. The repository's `deploy-notify.yml` only checks HTTP responses after a delay; it neither deploys files nor verifies preservation. A successful notification is not proof that data survived.
3. Preserve the runtime paths above in the transfer mechanism. Do not use a clean/recreate deployment, `git clean -fdx`, mirror deletion or an FTP “delete files missing from source” option against the live installation. `.gitignore` governs Git tracking, not those external deletion behaviors. For a release-directory deployment, attach persistent storage/configuration before serving the new release.
4. Protect the historical tracked Finances configuration. Make a private backup first. If `config.private.php` does not exist, copy the **current server's** `config.local.php` there using a no-overwrite operation. If the destination already exists, inspect and retain its contents; do not overwrite it. Preserve all real `users` entries and other settings. The private file is loaded last. Do not delete/untrack the historical file as a shortcut: deployment may propagate that deletion.
5. Run `node --test user-management/tests/deployment-safety.test.cjs` in the checkout. This checks repository contents/ignore rules and prevents changing the historical config. It cannot inspect Hostinger settings or enforce server-side exclusions. Keep these checks required before merging deployment changes.
6. Compare private before/after file inventories and hashes after the code deploy, while account/data writes are paused. Central directory, budgets and live configuration should be unchanged by code deployment. Lock/rate-limit/session activity may vary when traffic is running. Verify the protected paths cannot be downloaded over HTTP.
7. Preview and apply **one** verified account link. Then test that person's existing records and exact project permissions. Repeat separately for each account/project. Enable shared login only after these checks pass.

## Backups created by linking

Before committing a link, the application saves the exact selected budget bytes, legacy account configuration, previous mapping history, and the complete previous central directory in a unique private backup under `finances/data/identity/`. This includes password hashes and potentially legacy passwords: backups must remain server-private. The central directory snapshot makes changes to membership and account links reviewable/recoverable; it is not a complete hosting backup.

If backup creation fails, linking stops without committing the mapping or membership. Mapping and membership then commit together as one atomic central-directory replacement. Tests compare budget bytes and the previous directory exactly. A failure after creating a backup may leave that backup behind; it does not mean a link completed.

Do not restore a whole old central directory over newer account changes casually. Stop writes, preserve the current state, inspect the relevant snapshot and perform a targeted recovery under maintenance. Returning Finances to legacy login does not restore old budgets or erase edits made since linking.

## Current verification boundary

The repository checks and isolated migration tests cover the central service and Finances pilot. Hostinger's transfer/delete settings and live file contents have not been inspected from this workspace. No live migration or hosting backup has been performed here. Each additional project needs its own runtime-file inventory, sample separation, backup and preservation tests before migration is enabled.
