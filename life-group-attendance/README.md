# Life Group Attendance

PHP/JSON attendance portal for multiple church life groups. It includes authenticated access, live first/last-name search, grade and gender filters, sibling links, reusable student profiles, serving and baptism fields, attendance check-in, and ministry/group/student history.

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

Version: 1.0.0

