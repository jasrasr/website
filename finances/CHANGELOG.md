# Changelog

## 1.3 — 2026-09-25

- Added opt-in shared identity with explicit central-ID to legacy-account mappings; existing budgets stay in place.
- Added the first account-linking adapter, inventory validation and protected snapshots.
- Preserved legacy login by default, blocked local-session bypass in shared mode, and enforced project roles/ownership.
- Added CSRF checks for login, logout and saves, stable budget locks and atomic writes, and missing/corrupt-data protection.
- Ignored local configuration, link storage/backups, lock files and temporary writes.
