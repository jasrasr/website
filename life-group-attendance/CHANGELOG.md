# Changelog

## 1.1.0 — 2026-08-22

- Added Super Admin and Attendance roles.
- Added Super Admin user management with account activation, role changes, and optional password resets.
- Attendance users can take attendance and add or edit complete student profiles.
- Restricted user management, group creation, roster imports, and reports to Super Admin.
- Added explicit Edit buttons to the student directory.
- Preserved compatibility with existing legacy `admin` accounts.

## 1.0.1 — 2026-08-22

- Added an administrator-only Frontlines roster importer.
- Import is limited to youth name, gender, and grade.
- Excluded Frontlines teams, leaders, sponsors, colors, and scores.
- Added duplicate-name protection and support for the broad `HS` grade value.
- New installations seed the available Frontlines youth roster during setup.

## 1.0.0 — 2026-08-22

- Added first-run administrator setup and session authentication.
- Added multiple life groups, student profiles, sibling linking, serving locations, and baptism status/date.
- Added live name search plus group, grade, and gender filters.
- Added attendance check-in and ministry, group, and individual history totals.
- Added JSON persistence, audit records, CSRF protection, secure session cookies, and Apache data-file protection.
- Added responsive phone, tablet, and desktop layouts.
