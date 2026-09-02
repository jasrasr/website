# Life Group Attendance

PHP/JSON attendance portal for multiple church life groups. It includes authenticated access, live first/last-name search, grade and gender filters, sibling links, reusable student profiles, serving and baptism fields, attendance check-in, and ministry/group/student history.

The optional Frontlines importer reads only youth name, gender, and grade from the sibling `scoreboard/frontlines/team-roster-defaults.csv` file. It does not import teams, leaders, sponsors, colors, or scores. Duplicate names are skipped, and blank gender/grade fields on existing students may be filled.

## Roles

- **Super Admin:** manages users, life groups, roster imports, students, reports, and attendance.
- **Attendance:** takes attendance and can add, edit, or remove student profiles. It cannot manage users, groups, imports, or administrative reports.

Click any student row—or its explicit **Edit** button—to update the student.

Removing one student—or a bulk selection—is a soft delete: profiles disappear from active lists, sibling links are cleaned up, and prior attendance remains available for historical totals and audits. Siblings can be found by typing a first or last name, with matching last names suggested first. Super Admins can add and edit life groups from the dedicated **Groups** screen.

## Leader accounts and approval

New leaders select **Register for an account** on the login page (or visit `register.php`). They enter their name, email, password, and matching confirmation. New accounts are inactive and awaiting approval with the Attendance role; registration never grants access to student data or Super Admin privileges.

A Super Admin opens **Users**, selects **Review** beside an awaiting-approval account, checks **Active account**, and saves. No email notifications are sent; tell the leader when they can sign in. Existing duplicate email addresses are not modified. Public requests are limited to five per source per hour and 100 pending requests. Registration and administrator account writes share a lock to avoid overwriting one another.

All navigation tabs wrap on phones. The footer displays revision and updated date. CSS/JS URLs include a content hash to refresh cached files after deployment; if startup or data loading fails, the page shows an error instead of silently failing.

## Requirements

- PHP 8.1 or newer
- Apache with `.htaccess` support recommended
- HTTPS strongly recommended
- Write access for PHP to the `data/` directory

## Install

1. Upload the entire folder to the web server.
2. Confirm `data/` is writable by PHP but not publicly downloadable.
3. Browse to `setup.php` and create the first administrator.
4. Sign in and create life groups before adding students.
5. Delete or rename `setup.php` after setup for an extra layer of hardening (it already refuses to run after a user exists).

## Privacy and security

The portal intentionally avoids medical, counseling, and highly sensitive fields. Leader notes and guardian contact details are still personal information. Use HTTPS, unique accounts, strong passwords, limited hosting/file access, and routine encrypted backups. Do not place the `data/` directory in a location where JSON files can be downloaded. Apache protection is included; Nginx/LiteSpeed users should verify equivalent deny rules.

## Storage

JSON files are saved using temporary-file replacement and locks. This works well for a modest life-group ministry on shared hosting. For simultaneous check-ins by many campuses or leaders, migrate storage to SQLite/MySQL rather than stretching JSON until it squeaks.

## Browser support

Designed for current Chrome, Safari, Edge, and Firefox. The interface is responsive for phones and tablets.

## Backup

Back up the complete `data/` folder. It contains users, students, groups, attendance, and the audit log.

## Validation

Run `node tests/ui-regression.cjs` for dependency-free UI wiring tests. On a PHP 8.1+ machine, lint each PHP file before deployment. Test registration with a non-production account: it must not be able to sign in or retrieve API data until a Super Admin approves it. Verify duplicate registration does not change an existing account and that disabling an account revokes its existing session.

Version: 1.3.0 · Updated: 2026-09-02
