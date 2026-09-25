# Link existing accounts without moving data

This release adds **administrator-approved account linking** and a **Finances pilot**. It does not auto-link matching usernames, migrate every project, or switch live authentication on deployment. Self-service linking by proving an old password is not implemented yet.

## What changes

A protected project-owned mapping connects a permanent central user ID to an existing local account ID:

`central user ID → legacy Finances username → existing data/<legacy filename>.json`

Existing budget filenames, contents, credentials, and local identifiers are unchanged by linking. Shared login uses the mapping on every request. There is no fallback to username matching, and no empty budget is created for an unlinked central user. Even a site administrator needs their own mapping to view a personal budget.

## Pilot rollout

1. Follow [HOSTING-DATA-SAFETY.md](HOSTING-DATA-SAFETY.md) before deploying. Back up the existing Finances directory, existing `config.local.php`, any `config.private.php`, and central identity data using your hosting backup. Verify `finances/data/.htaccess` is deployed and blocks direct access to nested files. For nginx, enforce an equivalent deny rule. Backups contain private budgets and legacy credentials; do not expose them through a generic file browser or commit them.
2. Verify deployment preserves live runtime files and private configuration, then deploy the updated `user-management`, `1-Framework`, and `finances` code. Finances defaults to `authentication => legacy`; existing login remains available.
3. Sign in as a **site administrator**, register project ID `finances`, and use its actual deployed local URL (for example `/finances/` or `/github/finances/`). Create the central accounts. Linking adds missing project access; no pre-grant is required. Finances has no enforced legacy roles, so explicitly review and select `member` for editing the existing budget or `viewer` for read-only access. Existing lower central permissions win. Temporary-password accounts can be linked before first login, but must change their password before using Finances.
4. In User Management choose **Open account linking — Finances pilot**. The dry-run report inventories configured legacy accounts and flags missing/corrupt data, orphan files, filename collisions, inactive targets, and duplicate mappings. Resolve blocked rows before rollout. “Not linked” accounts will not work with shared login.
5. Explicitly choose a legacy account and the person’s central account. Click **Preview only — no changes**. Verify who owns the old budget; matching names are not proof. No credentials or budget contents are shown in this admin screen.
6. Check the ownership confirmation and choose **Back up and apply this link**. The server rechecks administrator privileges, target project access, account/data fingerprints, and mapping conflicts. A changed budget/account invalidates the preview; preview again. Confirmation is single-use and expires after ten minutes.
7. Repeat for every required account. Refresh the dry-run report. There is no reassignment/overwrite button: each legacy budget and central user can participate in only one Finances mapping.
8. Before deployment, preserve your server configuration in **ignored** `finances/config.private.php` if it does not already exist; never overwrite an existing private configuration. In that private copy, add `'authentication' => 'shared'` alongside the existing `users` array. **Keep the original `users` array.** It preserves the legacy account inventory; the old passwords are not accepted in shared mode.
9. Test in separate browsers as each user: existing figures and expenses, read-only/edit access, no access to another user's budget, and central logout/disable behavior. Then keep the shared setting enabled.

Example configuration shape (retain the real existing users; do not overwrite them with this example):

```php
return [
    'authentication' => 'shared',
    'users' => [
        'existing-local-username' => ['password_hash' => 'KEEP_THE_EXISTING_HASH'],
    ],
];
```

Central logout, required password changes, revoked project access, and disabled users apply to every shared-mode request. A pre-existing legacy session cannot bypass shared mode. All writes require both central member-or-higher permission and a linked permission ceiling of member, plus the CSRF token. Even global super administrators cannot bypass the linked ceiling. Budget ownership is resolved from the authenticated ID, never from a query parameter.

## Dry run and backup behavior

Preview and report refresh do not write mappings, budgets, credentials, or backups. Preview uses filesystem lock files to coordinate with saves; it may create a `.lock` file beside an existing budget. Read-only here means application data is unchanged, not that no coordination file can appear.

Apply creates the ignored `finances/data/identity/` directory if needed, writes a private snapshot through the framework JSON store, and only then atomically writes both the mapping and any missing project grant into the central directory. A failed/stale apply may leave an empty directory or lock file; a failure after backup creation can leave an extra backup, but cannot rewrite the legacy budget. Failures are not treated as completed mappings.

The central `directory.json` stores `records.accountLinks.finances.links` keyed by central user ID and an append-only `history` of approvals. Each link stores legacy ID, approving central user ID, UTC time, backup ID, source/reviewed permissions and the effective role ceiling. Mapping and project membership share one locked atomic commit, so a failed backup or commit cannot leave a half-linked grant. Every successful link invalidates the target's sessions, even if project access already existed; sign in again.

Earlier pilot `identity/links.json` files are read as a compatibility fallback until central mappings exist. Links without a reviewed role are denied shared access and flagged by validation. Before deploying over such a pilot, keep legacy login enabled and have an administrator review and migrate its mappings with a backup under a maintenance window. The UI intentionally does not reassign existing links.

`backup-<timestamp>-<random>.json` contains the exact original budget bytes, the selected legacy account configuration (possibly including its old password/hash), authentication mode, the previous mapping document, and the full previous central directory (including password hashes). Backups have restricted file permissions and inherit HTTP denial from `data/.htaccess`. They are intentionally not downloadable through the portal. Back up central configuration/directory separately before migration; per-link backups are not a whole-site disaster-recovery backup.

## Rollback and correction

To revert the pilot login switch, set Finances `authentication` back to `legacy`. Existing accounts use the same budget filenames, including edits made after linking. Do not restore an old budget snapshot merely to undo authentication: that would discard newer edits. Existing legacy sessions may resume on rollback; sign them out or clear them if needed.

Incorrect owner selected? Stop shared access to the affected account immediately (disable the central account or revoke its project grant), return Finances to legacy mode during correction, and have the server administrator correct the mapping from the verified snapshot under a maintenance window. There is intentionally no casual “reassign” control. Retain the approval history and confirm the correct owner before re-enabling. This version supplies backup evidence and login-mode rollback, not an automatic data-restore or mapping-undo UI.

## Adding other projects

`Jasr\Users\AccountLinkAdapter` is the extension contract. Implement a server-owned project slug, private mapping storage path, a credential-free inventory, and `withSnapshot()` that validates the legacy identity/data and holds the same stable lock used by project writes. Register the adapter explicitly in `user-management/linking-bootstrap.php`; never accept include paths from browser input.

`AccountLinks` supplies administrator authorization, preview fingerprints, one-to-one conflict rejection, backup-before-link, audit history, and mapping lookup. Keep group/gallery membership and fine-grained sharing in the project. Do not use this one-to-one pilot directly for several parents accessing one baby gallery or household sharing; those need resource-membership adapters rather than merging accounts.

Every adopting route must validate the central session and project role before looking up the legacy account. Missing mapping/data must fail closed. Keep machine/API credentials separate from browser accounts.

## Validation

`node --test user-management/tests/linking.test.cjs` runs an isolated HTTP deployment with deliberately different central and legacy usernames. It checks byte-for-byte preservation, stale/duplicate/replayed mappings, unauthorized requests, CSRF, private backups, existing-data reads/writes, read-only users, missing/corrupt-file protection, session revocation, and legacy-mode rollback. The existing identity and Webstats suites remain regression gates.

The historical `finances/config.local.php` is already tracked in Git. This migration intentionally does not delete it, because a deployment deletion could remove live account configuration. `config.private.php` is a new ignored override loaded last; copy your existing settings there before switching modes. Never commit real credentials. Adding the old filename to `.gitignore` does not untrack its existing copy.

## Permission preservation contract

Each adapter must declare the roles it can enforce and translate existing project permissions to a canonical role. Return null only when permission is genuinely unspecified; unknown or unsupported roles must block linking. The preview then requires explicit review, with no default. A known source permission always takes precedence over a submitted fallback. Projects with group, household or resource-specific permissions need adapters that retain those restrictions before rollout; a single role cannot replace those rules.

The effective linked role is the lowest of the source/reviewed role, the central account type's maximum, and any existing central project permission. Linking never changes account type, all-project scope or directory management, and never raises an existing project grant. Shared routes must check both central access and `AccountLinks::can()` before accessing linked data. Later source reductions apply on access; source increases cannot raise the stored ceiling. Permission changes invalidate outstanding previews.
