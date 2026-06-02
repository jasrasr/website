# CVC Youth Scoreboard

A PHP scoreboard app for tracking team scores across multiple ministry instances, each with their own teams, data, and admin page.

## Instances

| Instance | Path | Teams |
|----------|------|-------|
| Root | `/` | 6th–8th Grade Boys/Girls (6 teams) |
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

## Shared Assets

All instances share a single set of frontend files in `public/`:

- `public/styles.css` — all styling; changes here apply to every instance
- `public/app.js` — all frontend logic; changes here apply to every instance

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
| Current live scores or renamed team display names | `<instance>/data/scores.json` | Instance-specific runtime data |
| Audit history | `<instance>/data/audit.json` | Instance-specific runtime data |

For API behavior changes, check all four API files unless the request is only for one scoreboard:

- `api.php`
- `collide/api.php`
- `youth/api.php`
- `frontlines/api.php`

## Features

- Quick buttons for `+1`, `+3`, `+5`, `+10` and matching negatives per team.
- Custom positive or negative score entry for each team.
- Reset one team or reset all teams at once.
- Rename teams and update the scoreboard title from the admin page.
- Scores saved to `data/scores.json` after each change.
- Audit log records score changes, team resets, board resets, team renames, and title updates.
- Viewer page automatically refreshes every 2 seconds.
- Admin page polls every 10 seconds; skips re-render when an input is focused.
- Dynamic viewer grid columns that adapt to the number of teams.
- Score font scales with viewport size and shrinks for larger numbers.
- Responsive layout for large screens, tablets, and phones including Safari mobile.
- Multiple scorekeepers supported via file locking.

## Demo

A live demo is available for public testing — use this instead of the production scoreboards:

- `https://jasr.me/scoreboard-demo/` — admin page (try it out)
- `https://jasr.me/scoreboard-demo/index.php` — viewer page

> Note: `https://jasr.me/scoreboard/` is the production scoreboard and is not open to the public.

## Deploying

Upload the project folder to your host. Make sure each instance's `data/` folder is writable by PHP so scores can be saved. The `data/scores.json` file is created automatically on first load.
