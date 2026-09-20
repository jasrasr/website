# Changelog

## 1.0.0 — 2026-09-20

- Added JsonStore with schema validation, stable locking and atomic replacement.
- Added Response with the standard JSON response envelope.
- Extracted and strengthened PasswordSession from box/lib/auth.php without changing that app.
- Bootstrap loads new class definitions only, preserving existing initialization behavior.
- Webstats is the first consumer; no existing application is migrated and no shared identity schema is imposed.
