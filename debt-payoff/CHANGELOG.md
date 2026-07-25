# Changelog

## 2026-07-25
- Revision 1.0.5 added a once-per-update notice that appears after a new page version is loaded.
- The notice includes a dismiss option and a direct link to the changelog.

## 2026-07-25
- Revision 1.0.4 added a top-menu Readme link across the debt-payoff app.
- Added `readme.php` to render `README.md` in-browser.

## 2026-07-25
- Revision 1.0.3 clarified each loan's schedule as an amortization table.
- Added side-by-side standard and adjusted amortization summaries per loan.
- Added a separate standard amortization table so early payoff comparisons can be reviewed against the adjusted scenario.

## 2026-07-25
- Revision 1.0.2 added per-user audit logs with revert support for normal dashboard data changes.
- Added per-user backup snapshots with manual recover controls and automatic first-login daily backups.
- Added backup retention pruning to keep the newest 99 snapshots per user.
- Added `QUESTIONS_FOR_REVIEW.md` so future design and functionality questions can be reviewed from the project files.

## 2026-07-25
- Revision 1.0.1 added owner-controlled viewer and editor sharing between users.
- Added explicit promote and demote controls on the admin page in addition to the role edit dropdown.
- Moved project revision and modified timestamp into the top bar so it no longer overlays dashboard content.
- Confirmed registration remains active while access control is enforced for shared datasets.

## 2026-07-25
- Revision 1.0.0 created the baseline Debt Payoff Planner app.
- Added private user registration and login with JSON-backed per-user storage files.
- Seeded the `user` / `test` sample account with sample debt data for testing.
- Added private loan tracking fields for loan type, category, APR, monthly payment, current principal, payoff projection, original date, and original balance.
- Added per-loan payoff calculations for baseline payoff, extra monthly principal, annual blue moon payment, and one-time lump-sum acceleration.
- Added snowball and avalanche strategy modeling, overall debt metrics, a rendered changelog page, a rendered todo page, and an admin-only user-management page that shows only storage usage per user.
- Updated the baseline versioning to start at `1.0.0` and made the logged-in logout action more visually explicit.
