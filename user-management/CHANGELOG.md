# Changelog

## 1.1.0 — 2026-09-25

- Added administrator-approved account linking with dry-run inventory, explicit preview, stale/duplicate/replay rejection, backup-before-link and approval history.
- Added a reusable adapter contract and Finances pilot without moving legacy data or automatically enabling shared login.
- Added migration/rollback documentation and preservation/isolation HTTP tests.

## 1.0.0 — 2026-09-24

- Added shared same-host accounts and login portal, account administration, per-project roles and server-side integration.
- Added private first-admin setup, forced temporary-password changes, persisted login throttling, CSRF checks and session revocation.
- Added last-active-admin protection, ignored atomic JSON storage, framework adapter, setup and migration guide, and HTTP regression tests.
- Existing applications retain their current authentication until explicitly integrated.
