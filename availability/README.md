<!-- Revision 1.1.0 | 2026-09-17 | Privacy, party planning, proposed times and deadlines. History: 1.0.0 — Initial setup guide. -->
# Availability

**Project revision 1.1.0 · Updated 2026-09-17**

A shared date poll for family events, group outings, and team meetups. Built with PHP, vanilla JavaScript, CSS, and one protected JSON document per event. No database, build step, account, or external service is required.

## How to use

1. Open the app, enter an event name and organizer name, and add proposed dates and optional times people can choose from. Add an optional tentative location, an event time zone, and an optional voting expiration. These are proposals, not a confirmed event schedule. Add the same day with different times to compare slots.
2. Save the **private admin link**. It gives its holder permission to edit this event's title, description, organizer, date/time options, location, voting deadline, and open/closed status. It is the recovery method for a different browser or device; there is no password reset service.
3. Share the **invite link**. Anyone with it can see event details, **public display names**, individual availability, optional adult/kid counts, food contributions, and totals. They cannot see private names, phone numbers, or email addresses.
4. Each attendee enters a required public display name and marks proposed date/time options **Can attend**, **Can't attend**, or **Not answered**. Shortcuts set all dates to yes/no, then attendees can change exceptions. Answers only become public after saving.
5. Results refresh every five seconds while the page is visible. Bars rank dates by the number of explicit yes answers, highlight all ties, and show no/unanswered counts. No responses means no winner. Unanswered is never treated as available.
6. An attendee's browser remembers their response. Save the **private response link** to edit from another device. Public display names are required and case-insensitively unique within an event; people sharing a name should add an initial. A matching name alone cannot overwrite another person's response.

Each event has separate admin credentials. An invite link cannot edit event settings. Admin and response tokens live in link fragments, which browsers do not send to the server. The app removes fragments from the address bar after loading, stores credentials locally, and sends tokens in JSON request bodies only when saving. Tokens are hashed in storage and omitted from public API responses. Private name/phone/email values are excluded using an explicit server-side public-response allowlist, not just hidden in the UI. Only an authenticated event admin receives `adminResponses`; an attendee authenticated with their response token receives their own `myResponse`. Tokens for one event never authorize another event. Authenticated refreshes use JSON POST `view` requests so secrets are never query parameters; those reads do not write event files. Keep private links private. These are informal polls, not verified-identity elections; people can submit under different names.

## Privacy, party sizes, and food

- **Public display name:** required; shown with the availability response.
- **Private name, phone, email:** optional; field labels explicitly say that only the attendee and this event's admin can see them. The private admin table includes these fields. Invite holders receive no other attendee's private fields in JSON or page markup.
- **Adults and kids:** optional whole numbers, including yourself; blanks mean unknown and zero means none. These counts apply to every option the household marks yes. Public results show reported adult/kid headcounts for each option and identify incomplete counts. Rankings remain based on the number of available responses (one response per household/group), so large families do not silently receive extra votes.
- **Food type and details:** optional and public; choose a main dish, side dish, dessert, snack, drinks, other, or not bringing food, with a free-text description.
- Private fields are access-controlled, not encrypted at rest. The server operator and backups can access stored data; the visibility promise concerns app users.

## Proposed schedule and voting expiration

Dates use stable keys such as `2026-10-16` (time undecided) or `2026-10-16T17:00`. Multiple times on one date are distinct options with independent votes. Every displayed time uses the event's named IANA time zone (default America/New_York), not the attendee device's local zone. Date-only v1.0.0 events continue to work. The zone locks after the first response to avoid silently changing what people voted for.

Voting expiration is optional and entered in the event time zone. The server resolves it to UTC, rejects invalid/nonexistent local times, and blocks new and edited responses once its clock reaches that instant. Results remain public. The admin can extend or clear expiration and reopen a manually closed poll. The interface picks up deadline status on its next five-second refresh; the server enforces the exact deadline even if an old form is still open. During a fall-back repeated hour, PHP resolves the local deadline to one UTC instant; the displayed deadline includes its time-zone abbreviation so the resolved occurrence can be checked.

## Hosting and deployment

- PHP **8.1+**, with JSON support and a writable local filesystem.
- Copy this folder to your PHP host; the target for this repository is `https://jasr.me/github/availability/`.
- The default `data/` folder must be writable by the PHP process. The app can create it if the parent is writable.
- Optional environment variable `AVAILABILITY_DATA_DIR` points to a writable directory outside the web root (recommended if available).
- Preserve runtime data on deployment; do not delete/recreate `data/` or run `git clean -fdx` against the installation. Back it up separately. The local `.gitignore` excludes runtime files.
- Apache/LiteSpeed uses `data/.htaccess` to deny direct access. Stored documents additionally have a PHP execution guard and `.php` suffix, including temporary files; JSON is read as text by the API, never executed/included. For other servers, deny direct access to `data/` as well. PHP must be enabled; this does not run on GitHub Pages.
- Use HTTPS for private links and browser clipboard access. No host/domain is hardcoded in app links.

## Persistence and edits

One `<random-event-id>.php` file contains a PHP denial guard followed by JSON. `events.lock.php` is a stable filesystem lock shared by reads and writes. Writes hold an exclusive lock and atomically rename a temporary file on the same filesystem, preventing overlapping votes from losing data. Event IDs and tokens use cryptographically secure random bytes.

Admin edits increment the event-settings revision. Votes with an old revision fail instead of silently answering outdated dates. Existing answers survive for retained dates; newly added dates are unanswered; removed dates lose their old answers. Restoring a removed date does not restore old votes. Open clients receive an update notice. Closing a poll blocks writes server-side but keeps results readable. Polls support 60 date/time options and 500 responses each; this file-based app is intended for small groups. Use web-server rate limiting if exposed to untrusted high-volume traffic.

## Files and revision policy

- `index.php`: page and browser security headers.
- `api.php`: validation, authorization, persistence, and public event API.
- `app.js`: creation/editing, votes, share links, five-second polling, rankings, matrix.
- `styles.css`: responsive layout and accessible labeled controls.
- `data/.htaccess`, `.gitignore`: protect and preserve runtime records.
- `tests/smoke.mjs`: API integration checks against a local disposable server.
- `CHANGELOG.md`, `project.json`: release and per-file history.

Project and file revisions start at **1.0.0**.

## Validation

Start a disposable server (with isolated test data):

```sh
AVAILABILITY_DATA_DIR=/tmp/availability-test-data php -S 127.0.0.1:8090 -t availability
node availability/tests/smoke.mjs http://127.0.0.1:8090
```

The suite creates test events and checks private-field redaction, event-specific admin access, personal response recovery, counts, food types, deadline expiration/removal, DST gap rejection, backward-compatible edits, validation, both vote states, unanswered dates, editing without duplication, duplicate-name protection, private authorization, concurrent submissions, secret filtering, changed dates, stale votes, closing, reopening, and isolation between events. Test data belongs in the disposable directory, not the live installation.

Browser checklist: test private contact entry and verify contacts appear only in that event’s admin table and the attendee’s own form; check public headcounts/food, proposed times, expiration and reopening; then create an event; save the private admin link; open the invite in another browser; submit responses; check automatic updates; change answers without saving while another person votes (local edits must remain); add/remove dates as admin; close/reopen; reopen private links on another device; inspect narrow mobile widths and horizontally scroll the matrix.
