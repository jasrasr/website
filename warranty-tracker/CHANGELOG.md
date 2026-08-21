# Changelog

## 1.2.0 - 2026-08-21

- Added separate password-protected household accounts.
- Added private and shared-household warranty visibility.
- Added first-user administrator setup and expiring one-time invite codes.
- Added secure session cookies, CSRF checks, password hashing, and login throttling.
- Kept legacy warranty records visible so existing data is not stranded.

## 1.1.0 - 2026-08-21

- Renamed the displayed product cost to item cost.
- Added a separate warranty cost field to the form, cards, API validation, and JSON records.
- Preserved compatibility with records using the original `cost` field.

## 1.0.0 - 2026-08-21

- Added the responsive warranty dashboard and record editor.
- Added PHP/JSON create, read, update, and delete operations.
- Added atomic storage, locking, validation, and standard API envelopes.
- Added live expiration status and green-to-red visual countdown cues.
