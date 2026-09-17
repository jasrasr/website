<!-- File revision 1.1.2 | Modified 2026-09-17 | Invite/public mode separated from remembered admin sessions. -->
# Changelog

## 1.1.2 — 2026-09-17

- Generate invite links with an explicit attendee/public mode so they do not inherit locally remembered admin access.
- Keep the private admin token stored for recovery while ignoring it for the active invite session.
- Keep response-link recovery working in invite mode and hide event editing/private admin contacts unless the admin link is explicitly opened.
- Bump the client script cache version so deployed browsers receive the corrected behavior.

## 1.1.1 — 2026-09-17

- Use a single required public attendee name; remove the redundant private-name field without exposing legacy private values. Phone/email stay private.
- Fix Copilot’s valid Unicode uniqueness finding using canonical normalization and full Unicode case folding, preserving display spelling and accents.
- Require PHP intl/mbstring with an explicit setup error if missing.
- Add accented, decomposed, German, Greek, Cyrillic, and authorized-edit regression coverage.

## 1.1.0 — 2026-09-17

- Separate required public display names from optional private names, phone numbers, and email addresses, with explicit privacy labels.
- Enforce event-specific admin/response-token access and exclude private fields from public API responses.
- Add optional public adult/kid counts and food contributions, plus per-option reported headcounts.
- Add a tentative location, proposed date/time slots, and an explicit event time zone; no schedule is marked confirmed.
- Add optional voting expiration enforced by the server, with admin extension/removal and live expired-state updates.
- Preserve date-only events and private details when older clients omit the new fields.
- Extend API/browser coverage for privacy, authorization, counts, time options, expiry, and recovery.

## 1.0.0 — 2026-09-17

- Create independent events with required organizer names and admin-selected dates.
- Share invite links and protect event edits with private admin links.
- Require attendee names; save explicit yes/no answers and preserve unanswered dates.
- Edit responses using remembered browser credentials or private response links.
- Show ranked bars, all best-date ties, response counts, and a name-by-date matrix; refresh every five seconds.
- Edit dates while preserving retained answers, reject stale settings revisions, and close/reopen polls.
- Store protected event JSON with locking, atomic writes, token hashes, and excluded runtime data.
- Add mobile layout, labeled inputs, keyboard focus states, deployment instructions, and API integration coverage.
