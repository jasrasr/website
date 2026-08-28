# Debt Payoff Planner

Baseline PHP app for private, per-user loan tracking and payoff planning.

## Backend Storage
- Storage is plain JSON, not JSONL and not a database.
- Account records live in `data/accounts.json`.
- Each user's debt dataset lives in `data/users/<username>.json`.
- Each user's audit trail lives in `data/audit/<username>.json`.
- Each user's backups live in `data/backups/<username>/*.json`.

## Default Testing Account
- User: `user`
- Password: `test`

## Admin Setup
- The first newly registered account becomes admin if no admin exists yet.

## Baseline Features
- User registration and login
- Private per-user JSON data files
- Owner-controlled viewer and editor sharing with other users
- Loan tracking across major debt types
- Per-loan amortization and accelerated payoff tables
- Snowball and avalanche strategy comparisons
- Admin-only user management with storage-usage visibility only
- Explicit promote/demote controls for admin role changes
- Per-user audit history with revert controls
- Daily first-login backups plus manual backup/recover controls

## Backup and Recovery
- On the first successful login of a given day, the app automatically creates one backup snapshot for that user.
- Users can also create a manual backup from the dashboard.
- Recovering a backup replaces the current dataset with the snapshot contents and writes a new audit entry.
- Backup retention is capped at the newest `99` snapshots per user.

## Audit Behavior
- Loan adds, loan updates, loan deletes, strategy budget changes, share grants, share revokes, backup recovery, and audit reverts are all written to the per-user audit log.
- Each audit entry stores the full before and after dataset state so a revert can restore the prior version cleanly.
- Audit revert restores the entire dataset to the saved pre-change snapshot for that audit event.

## Revisioning Rule
- Initial baseline starts at `1.0.0`
