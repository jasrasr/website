# Changelog

## 1.2.0 — 2026-09-25

- Separated User/Admin/Super Admin account roles, selected/all-project scope and central directory management; retained existing explicit administrator rights.
- Added scoped demo and jasrasr provisioning with random temporary passwords and existing-account preservation.
- Added a shared editable display name/contact-email profile, project super-admin gates and role-scope regression tests.
- Demo accounts cannot manage the central directory or receive all-project scope; application demo datasets remain separate migration work.

## 1.1.0 — 2026-09-25

- Added administrator-approved account linking with dry-run inventory, explicit preview, stale/duplicate/replay rejection, backup-before-link and approval history.
- Added a reusable adapter contract and Finances pilot without moving legacy data or automatically enabling shared login.
- Added migration/rollback documentation and preservation/isolation HTTP tests.

## 1.0.0 — 2026-09-24

- Added shared same-host accounts and login portal, account administration, per-project roles and server-side integration.
- Added private first-admin setup, forced temporary-password changes, persisted login throttling, CSRF checks and session revocation.
- Added last-active-admin protection, ignored atomic JSON storage, framework adapter, setup and migration guide, and HTTP regression tests.
- Existing applications retain their current authentication until explicitly integrated.

### Linking permission preservation
- Add missing project membership during linking without promoting account roles or existing grants.
- Require explicit review for unspecified source permissions; enforce linked permission ceilings, including for global super admins.
- Commit mappings and membership atomically in the central directory after private backup.

### Hosting data preservation
- Keep samples outside runtime folders; ignore the entire Finances runtime tree except HTTP protection.
- Add CI guards against tracked live data/private settings and editing/deleting the historical Finances configuration.
- Back up the previous central directory alongside exact budget bytes before linking.
- Document pre-deployment hosting backups, no-overwrite configuration handling, and separate server deployment verification.
