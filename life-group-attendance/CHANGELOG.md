# Changelog

## 1.3.2 — 2026-09-02

- Keep the current section in the URL so refreshing Users, Students, Groups, Reports, or Check-in restores that section.
- Support browser Back/Forward and direct section links; unknown or unauthorized sections fall back to Dashboard.
- Add navigation regression tests, including a fresh page load on Users and role-restricted links.

## 1.3.1 — 2026-09-02

- Fetch fresh account data when opening Users and via Refresh approvals; bypass API caches and display load errors instead of an empty approval claim.
- Show Awaiting Super Admin approval (or Disabled) at login only after the account password is verified.
- Verify newly saved registrations by reading the persisted row back before issuing a registration reference.
- Return verified existing-account status without silently replacing credentials or claiming a new registration was created.
- Replace the clipped Users table with responsive account cards, pending accounts first, with matching registration references.
- Add regression tests for registrations arriving after the admin page opens and failed/retried account refreshes.

## 1.3.0 — 2026-09-02

- Versioned CSS/JavaScript URLs by revision and content hash to prevent stale-script/page mismatches after deployment.
- Added delegated Add life group handling, startup/data-load error messages, and visible revision/date.
- Made all navigation tabs visible on phones, including Users.
- Added public leader registration with password confirmation and pending Super Admin approval; registrations cannot choose a role or activate themselves.
- Added pending-registration review in Users. Existing accounts are never replaced by registration.
- Added registration limits and serialized account writes; disabled/pending accounts cannot use existing sessions.
- Fixed login CSRF enforcement and required POST for API mutations.

## 1.2.0 — 2026-08-31

- Replaced the overflowing mobile student table with compact, responsive student cards.
- Added a confirmed student Delete action for authenticated users.
- Added select-all and bulk delete controls to the student directory.
- Student deletion now preserves attendance history through an audited soft delete.
- Added a dedicated Super Admin Life Groups screen.
- Added life-group creation and editing, including leader, meeting time, and active status.
- Replaced the sibling multi-select with type-to-search selection and same-last-name suggestions.

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
