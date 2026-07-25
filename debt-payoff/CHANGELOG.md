# Changelog

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
