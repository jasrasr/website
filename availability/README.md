<!-- Revision 1.3.1 | 2026-09-17 | Attendee-added options, optional password protection, privacy, party planning, proposed times and deadlines. -->
# Availability

**Project revision 1.3.1 · Updated 2026-09-17**

A shared date poll for family events, group outings, and team meetups. Built with PHP, vanilla JavaScript, CSS, and one protected JSON document per event. No database, build step, account, or external service is required.

## How to use

1. Open the app, enter an event name and organizer name, and add proposed dates and optional times people can choose from. Add an optional tentative location, an event time zone, and an optional voting expiration. These are proposals, not a confirmed event schedule.
2. Optionally set an **admin password**, an **event/invite password**, or both. Passwords must be at least 4 characters.
3. Optionally enable **Allow attendees to add additional date/time options**. When enabled, anyone who can access the poll can add another option. Existing responses remain unchanged and the new option starts unanswered for everyone.
4. Save the **private admin link**. It gives its holder permission to edit this event. If an admin password is configured, both the private admin link and that password are required before event settings or private attendee details can be accessed.
5. Share the **invite link**. If an event/invite password is configured, attendees must enter it before they can view event details/results, add an option, or submit a response.
6. Each attendee enters a required name and marks proposed date/time options **Can attend**, **Can't attend**, or **Not answered**. Phone/email are optional and private to that attendee and the event admin; adult/kid counts and food contributions are visible to people who can access the event.
7. An attendee's browser remembers their response. Save the **private response link** to edit from another device. Password-protected events still require the event password.

Admin and event passwords are independent. They are stored only as PHP `password_hash()` values in protected event storage and are never returned by the API. Entered passwords are kept only in browser `sessionStorage`, so a new browser/session asks again. There is no password-reset service; retain any password you configure.

Invite links use an explicit attendee mode, so opening an invite link in a browser that previously opened the private admin link does not expose event editing controls.

## Attendee-added date/time options

The organizer controls this feature with a checkbox during event creation or from **Edit event** later. Existing events default to disabled until explicitly enabled.

When enabled, the attendee page shows an **Add another option** area with a date and optional time. The new option is inserted into the same shared list used by organizer-created options. It becomes visible to all open clients on refresh and begins as **Not answered** for every existing response. Existing yes/no answers are preserved.

Adding an option increments the event revision. If another person or the organizer changes the event before an attendee submits a new option, the stale request is rejected and the attendee must use the refreshed event state. Duplicate date/time options are rejected. The same calendar date can still appear more than once when the times differ. No additions are accepted after the poll is closed, after voting expires, or after the poll reaches 60 total options.

If an event/invite password is configured, it also protects attendee-added options. A person cannot bypass the event password by calling the add-option endpoint directly.

## Privacy and authorization

Each event has separate admin credentials. Admin and response tokens live in link fragments, which browsers do not send to the server. The app removes fragments from the address bar after loading, stores credentials locally, and sends tokens in JSON request bodies for authenticated reads and saves. Tokens are hashed in storage and omitted from public API responses. Phone/email values are excluded using a server-side allowlist. Only an authenticated event admin receives private attendee contact details.

Passwords add a second gate where configured: an admin password is required in addition to the admin token; an event password gates public viewing, results, voting, response-link access, and attendee-added date/time options. Private password hashes are not included in public event JSON.

## Hosting and deployment

- PHP **8.1+**, with **intl** and **mbstring** enabled, JSON support, password hashing support, and a writable local filesystem.
- Target deployment: `https://jasr.me/github/availability/`.
- The default `data/` folder must be writable by PHP. Prefer `AVAILABILITY_DATA_DIR` pointing to a writable directory outside the deployed web root when Hostinger supports it.
- Preserve runtime data on deployment; do not delete/recreate `data/` or run `git clean -fdx` against the installation. Back it up separately.
- Apache/LiteSpeed uses `data/.htaccess` to deny direct access. Stored documents also use a PHP execution guard and `.php` suffix.
- Use HTTPS. Passwords and private tokens are sent only in JSON request bodies, never query strings.

## Files

- `index.php`: page, browser security headers, password creation fields, organizer attendee-option setting, and attendee add-option controls.
- `api.php`: validation, password/token authorization, persistence, public event API, and attendee `suggest_date` action.
- `app.js`: creation/editing, voting, attendee-added options, invite/admin link modes, polling, rankings, and response matrix.
- `password-ui.js`: password prompts, session-only password handling, and protected API retries.
- `styles.css`: responsive layout and accessible labeled controls.
- `data/.htaccess`, `.gitignore`: protect and preserve runtime records.
- `tests/smoke.mjs`: general API integration checks.
- `tests/passwords.mjs`: admin/event password regression checks.
- `tests/suggestions.mjs`: attendee-added option regression checks.
- `CHANGELOG.md`, `project.json`: release and per-file history.

Project and file revisions start at **1.0.0**.

## Validation

Start a disposable server with isolated test data:

```sh
AVAILABILITY_DATA_DIR=/tmp/availability-test-data php -S 127.0.0.1:8090 -t availability
node availability/tests/smoke.mjs http://127.0.0.1:8090
node availability/tests/passwords.mjs http://127.0.0.1:8090
node availability/tests/suggestions.mjs http://127.0.0.1:8090
```

Browser checklist: create events with attendee-added options disabled and enabled; verify the attendee add-option area appears only when enabled; add date-only and date/time options; verify duplicate and stale additions are rejected; confirm existing answers remain intact and new options start unanswered; disable the feature again as admin; then repeat on an event protected with an event password. Also test no-password, admin-only, invite-only, and dual-password events; confirm invite mode never exposes admin editing; verify wrong/missing passwords cannot read protected data; verify the admin password plus private admin link reveals private attendee details; verify event-password attendees can vote and edit their own response; and verify a new browser/session asks for the password again.

GitHub Actions validates PHP syntax, JavaScript syntax, project JSON, and committed conflict markers on Availability changes. A deployment must use source that passes these checks; never commit unresolved stash/merge markers.
