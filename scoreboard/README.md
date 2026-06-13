# CVC Youth Scoreboard

A PHP scoreboard app for tracking team scores across multiple ministry instances, each with their own teams, data, and admin page.

## Instances

| Instance | Path | Teams |
|----------|------|-------|
| Default | `/` | Team 1–Team 6 |
| Collide | `/collide/` | 6th–8th Boys/Girls (6 teams) |
| Youth | `/youth/` | 6th–12th Grade + Grads (8 teams) |
| Frontlines | `/frontlines/` | Red, Maroon, Orange, Yellow, Light Green, Dark Green, Light Blue, Royal Blue, Navy, Pink, Purple, Smoke (12 teams) |

Each instance has:
- `index.php` — read-only viewer for spectators (auto-refreshes every 2 seconds)
- `enter-scores.php` — scorekeeper admin page to update scores
- `enter-scores-quick.php` — compact scorekeeper page for faster score entry
- `api.php` — REST API for score operations
- `scoreboard_lib.php` — instance-specific team definitions and data helpers
- `data/scores.json` — persisted scores (auto-created on first load)
- `data/audit.json` — audit log for score changes, resets, renames, and title updates
- `data/*.sample.json` — public-safe templates for duplicating runtime files

## Shared Assets

All instances share a single set of frontend files in `public/`:

- `public/styles.css` — all viewer/admin styling; changes here apply to every instance
- `public/app.js` — viewer/admin frontend logic; changes here apply to every instance
- `public/quick-entry.css` — quick-entry styling; changes here apply to every instance
- `public/quick-entry.js` — quick-entry frontend logic; changes here apply to every instance

## Admin Navigation

- `changelog.php` — web view of `CHANGELOG.md`; accessible to any signed-in user. Update `CHANGELOG.md` only when adding entries.
- `scoreboards.php` — navigation page listing Viewer, Full Admin, and Quick Entry links for the scoreboard instances the signed-in user can access. Admins see all four; scorers see only the instances assigned in `data/users.json`.
- The Full Admin page (`enter-scores.php`) footer shows `Changelog` and `Scoreboards` to every signed-in user; `Manage Users` is admin-only.
- The Quick Entry page (`enter-scores-quick.php`) footer shows `Scoreboards` so scorers can jump between their accessible instances without going through Full Admin first.

### Access helpers in `auth.php`

- `requireAuth($scoreboardId, $loginUrl)` — page must be signed in AND have access to that scoreboard.
- `requireAuthJson($scoreboardId)` — API equivalent; returns JSON 401/403 instead of redirecting.
- `requireSignedIn($loginUrl)` — page only needs an authenticated session; used by `changelog.php` and `scoreboards.php`.
- `requireAdmin($loginUrl)` — page requires `role === 'admin'`; used by `admin-users.php`.
- First-run and admin-reset passwords require the user to set a new password before using the scoreboards.

## Runtime Samples

Live runtime files are ignored by Git, but public-safe samples are committed so the app can be duplicated cleanly:

- `data/scores.sample.json`
- `data/users.sample.json`
- `data/audit.sample.json`
- `<instance>/data/scores.sample.json`
- `<instance>/data/audit.sample.json`
- `first-run-credentials.txt.sample`

Do not commit live `scores.json`, `users.json`, `audit.json`, or `first-run-credentials.txt` files. The app creates missing live files on first use. If `data/users.json` is missing, first use creates two temporary users: `admin` and `scorer`, both with password `password`. Both users must set a new password before they can continue.

## Where to Make Changes

Use this split when deciding whether an update is global or tied to one scoreboard:

| Change needed | Edit location | Scope |
|---------------|---------------|-------|
| Viewer/admin layout, responsive behavior, colors, spacing, score sizing | `public/styles.css` | Global |
| Viewer/admin browser behavior, polling, button handling, audit table display | `public/app.js` | Global |
| Quick-entry browser behavior and layout | `public/quick-entry.js`, `public/quick-entry.css` | Global |
| Quick-entry page shell or links for one scoreboard | `<instance>/enter-scores-quick.php` | Instance-specific |
| Score operations, audit logging payloads, API auth behavior | `<instance>/api.php` | Instance-specific |
| Team list, default title, team colors, data file helper behavior | `<instance>/scoreboard_lib.php` | Instance-specific |
| Project changelog content | `CHANGELOG.md` | Global |
| Web changelog display | `changelog.php` | Global |
| Admin-only scoreboard navigation | `scoreboards.php` | Global |
| Current live scores or renamed team display names | `<instance>/data/scores.json` | Instance-specific runtime data |
| Audit history | `<instance>/data/audit.json` | Instance-specific runtime data |

For API behavior changes, check all four API files unless the request is only for one scoreboard:

- `api.php`
- `collide/api.php`
- `youth/api.php`
- `frontlines/api.php`

## Features

- Quick buttons use `+1`, `+10`, `+100`, and `+1000`; use the custom/manual amount box for negative scoring.
- Custom positive or negative score entry for each team.
- Reset a single team's score to zero (per-card on full admin, and for the selected team on quick entry) or reset all teams at once.
- Rename teams and update the scoreboard title from the admin page. Apply/Rename buttons sit inline with their text inputs on the full admin.
- **Add and remove teams** from the full admin page. Each card has a Remove Team button (with confirm). The Add Team form between the team grid and activity log has labeled name/color fields and can be submitted with the Add Team button or Enter; the server generates a unique `team-{random}` id.
- Scores saved to `data/scores.json` after each change.
- Audit log records score changes, team resets, board resets, team add/remove, team renames, and title updates. The audit log is visible both on the full admin page and on quick entry (collapsible "Show Recent Activity" section).
- Quick entry no longer auto-selects a team on load — a placeholder prompts the user to pick a team first, preventing accidental score changes after refresh.
- Top-banner shortcuts on the full admin (`View Scoreboard`, `Quick Score`) jump between viewer, quick entry, and full admin without scrolling to the footer.
- Viewer page automatically refreshes every 2 seconds.
- Admin page polls every 10 seconds; skips re-render when an input is focused.
- Dynamic viewer grid columns that adapt to the number of teams.
- Viewer page orders teams from highest score to lowest score; viewer header shows a "Teams sorted by score (1st, 2nd, 3rd...)" note.
- Admin and quick-entry pages order teams alphabetically A-Z by team name and show a "Teams are sorted A-Z by name." note.
- Place-rank badges (`1st`, `2nd`, `3rd`, ...) appear on every team card on the viewer, admin, and quick-entry pages; top 3 use gold/silver/bronze styling and ties share rank.
- Quick-entry page shows the running script revision (`v1.x.x`) directly under "Last updated" so it is obvious which version is loaded.
- Score font scales with viewport size and shrinks for larger numbers.
- Responsive layout for large screens, tablets, and phones including Safari mobile portrait and landscape.
- Multiple scorekeepers supported via file locking.

## User Management

`admin-users.php` (admin-only) lists every user with **Username**, **Role**, **Scoreboards**, **Created**, **Modified**, and per-row Edit / Reset PW / Delete actions.

- **Edit** opens a modal where admins can change the username, role, and per-scoreboard access. Renaming yourself also updates the active session so subsequent requests use the new name.
- **`modified_at`** is tracked on user creation, edit, and password reset; the Modified column shows the date of the last change. Pre-existing users without a `modified_at` value show blank until their first edit.
- **Created/reset passwords** are treated as temporary. The user must change the password on next sign-in before continuing.
- **Frontlines Roster** access: the roster pages (`teams.php`, `edit-roster.php`) gate `Edit Roster` to admins; viewers and scorers see the public roster only.

## Access Control

- Signed-in users who hit a scoreboard they don't have access to (e.g., `/youth/enter-scores.php` for a scorer without Youth) are redirected to `scoreboards.php?denied=<id>` instead of seeing a 403 error page. The Scoreboards page reads the param and shows a banner explaining which scoreboard was off-limits.
- Public GETs on each instance's `api.php?action=scores` remain open (so the viewer doesn't need a login). All write actions and the audit endpoint require `requireAuthJson()`.

## Public URLs

Hostinger deploys this repository under `/github`, and this project folder is named `scoreboard`.

- `https://jasr.me/github/scoreboard/` — production scoreboard after `main` deploys
- `https://jasr.me/github-test/scoreboard/` — staging scoreboard from the `change-scoreboard-url` branch

The production scoreboard is not open to the public.

## Deploying

Upload the `scoreboard` project folder to your host. Make sure each instance's `data/` folder is writable by PHP so scores can be saved. The `data/scores.json` file is created automatically on first load.

The committed `data/.htaccess` files block direct public web access to runtime data folders on Apache-compatible hosts. If your host does not honor `.htaccess`, move runtime data outside the web root or add equivalent server rules. First-run users are written to `data/first-run-credentials.txt`; both use the temporary password `password`, must change it before continuing, and each used line is removed after that user changes their password. Delete the file after all first-run credentials are used.

## Frontlines Roster — Pending

- **Andrew Johnson** — listed in 2026 Frontlines cabin PDF (HS Boys / Cedar House, M/HS) but not yet assigned to a team color. Add to `frontlines/team_roster.php` and `frontlines/team-roster-defaults.csv` once a team is chosen.
