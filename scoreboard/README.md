# CVC Youth Scoreboard

A PHP scoreboard application for tracking team scores across multiple ministry instances, each with its own teams, runtime data, viewer, and score-entry pages.

Current project version: **v1.13.0**

## Versioning

The project version is maintained in `CHANGELOG.md`. Individual PHP, JavaScript, and CSS files use independent `Revision` values in their file headers. A file revision only changes when that specific file changes, so it will not normally match the overall project version.

## Instances

| Instance | Path | Teams |
|---|---|---|
| Default | `/` | Team 1–Team 6 |
| Collide | `/collide/` | 6th–8th Boys/Girls (6 teams) |
| Youth | `/youth/` | 6th–12th Grade + Grads (8 teams) |
| Frontlines | `/frontlines/` | Red, Maroon, Orange, Yellow, Light Green, Dark Green, Light Blue, Royal Blue, Navy, Pink, Purple, Smoke (12 teams) |

Each instance includes:

- `index.php` — read-only viewer for spectators; refreshes automatically.
- `enter-scores.php` — full score-entry and administration page.
- `enter-scores-quick.php` — compact score-entry page.
- `api.php` — instance-specific score API.
- `scoreboard_lib.php` — team defaults and JSON data helpers.
- `data/scores.json` — live scores, created only when missing.
- `data/audit.json` — score and administration audit history.
- `data/*.sample.json` — committed public-safe templates.

## Frontlines-only features

### Category scoring

- `frontlines/enter-scores-category.php` lets scorers and admins award predefined point categories with one tap.
- `frontlines/edit-categories.php` lets Frontlines admins manage category names, point values, award limits, and active status.
- User-facing navigation calls the scoring page **Add Category Score**.
- **Add Category Score** appears near the top of both Frontlines full entry and quick entry, as well as in the relevant footer/navigation areas.

### Searchable roster

- `frontlines/teams.php` is the public roster.
- `frontlines/edit-roster.php` is the admin-only roster editor.
- The roster search filters immediately by team name, leader, member, gender/grade suffix, or sponsor.
- Multiple search words use AND matching. For example, `Alex 12` only shows cards containing both terms.
- Search runs entirely in the browser and does not modify the roster JSON.
- The roster navigation links are located below all team cards.

### Viewer behavior

The Frontlines viewer opts in to `data-hide-bottom-teams="true"`. After sorting by score, it shows the top half of teams while keeping tied teams together. If all teams are tied, all teams remain visible. Other scoreboard instances are unaffected.

## Authentication and navigation

- `requireAuth($scoreboardId, $loginUrl)` requires a signed-in user with access to the requested scoreboard.
- `requireAuthJson($scoreboardId)` is the JSON/API equivalent.
- `requireSignedIn($loginUrl)` allows any authenticated user.
- `requireAdmin($loginUrl)` requires the `admin` role.
- Login preserves the requested scoreboard destination. A login started from Frontlines returns to the Frontlines page instead of falling back to the Default scoreboard.
- First-run and administrator-reset passwords require a password change before scoreboard access.
- The forced password-change page includes **Cancel and return to login**. Canceling signs the temporary session out first so the user does not loop back to the same page.
- `scoreboards.php` lists only the scoreboard instances the signed-in user may access.
- `changelog.php` displays `CHANGELOG.md` to signed-in users.

## Runtime data and deployment safety

Live runtime files are ignored by Git. Committed sample files are deployed, while an existing live file is left unchanged.

Important authentication files:

- `data/users-seed.sample.json` — first-run usernames, roles, and scoreboard access.
- `data/users.sample.json` — example of the persisted live users schema.
- `data/users.json` — live user database; never commit it.
- `data/users.previous.json` — one-slot backup created before user database changes.
- `data/first-run-credentials.txt` — temporary first-run credentials; never commit it.

`auth.php` creates `data/users.json` from `users-seed.sample.json` only when `users.json` does not already exist. Existing credentials are therefore not intentionally replaced during normal initialization. The temporary `admin` and `scorer` password is `password`; both accounts must change it before continuing.

Other live files that must remain uncommitted include:

- `<instance>/data/scores.json`
- `<instance>/data/audit.json`
- `<instance>/data/categories.json` where applicable
- Snapshot and previous-state files under each `data/` directory

The committed `.htaccess` files block direct web access to runtime data on Apache-compatible hosting.

## Backup and recovery

| Action | Backup | Recovery |
|---|---|---|
| Reset All Teams | `<instance>/data/scores.previous.json` | Click **Undo Reset All**, or restore the file manually. |
| Reset one team | `<instance>/data/scores.previous-single.json` | Restore the saved team record manually. |
| Remove Team | `<instance>/data/removed-teams.json` | Re-add the team and recover the previous value from the log. |
| Change users | `data/users.previous.json` | Copy it over `data/users.json`. |
| Scheduled snapshot | `<instance>/data/snapshots/YYYY-MM-DD-HH.json` | Restore the selected snapshot over `scores.json`. |

The optional snapshot command is:

```cron
0 3 * * * cd /home/<youruser>/public_html/github/scoreboard && /usr/bin/php tools/take-scores-snapshot.php >> data/snapshot.log 2>&1
```

## Shared assets

All instances share frontend files under `public/`:

- `public/styles.css` — viewer, admin, roster, and shared responsive styling.
- `public/app.js` — viewer and full-admin behavior.
- `public/quick-entry.css` — compact entry styling.
- `public/quick-entry.js` — compact entry behavior.

Frontlines-specific browser helpers live inside `frontlines/`, including:

- `category-navigation.js`
- `roster-search.js`
- `roster-search.css`

## Core features

- Positive and negative score adjustments.
- Add/Subtract mode for mobile-friendly scoring.
- Quick score buttons for `1`, `10`, `100`, and `1000` points.
- Per-team reset and Reset All with confirmation and recovery support.
- Add, rename, and remove teams.
- Rename scoreboard titles.
- File locking for concurrent scorekeepers.
- Audit logging for score and administration actions.
- Viewer ranking with tie handling and score-change-time tie-breaking.
- Gold, silver, and bronze styling for the top three ranks.
- Responsive phone, tablet, widescreen, portrait, and landscape layouts.
- Scroll-position preservation during automatic refreshes.
- Full Admin and Quick Entry navigation between viewer, scoreboards, roster, categories, password change, and sign-out pages.

## Where to make changes

| Change | Location | Scope |
|---|---|---|
| Viewer/admin layout and shared styles | `public/styles.css` | Global |
| Viewer/full-admin behavior | `public/app.js` | Global |
| Quick-entry behavior and layout | `public/quick-entry.js`, `public/quick-entry.css` | Global |
| Instance page shell and links | `<instance>/*.php` | Instance-specific |
| Score API behavior | `<instance>/api.php` | Instance-specific |
| Team defaults and data helpers | `<instance>/scoreboard_lib.php` | Instance-specific |
| Frontlines roster search | `frontlines/teams.php`, `frontlines/roster-search.js`, `frontlines/roster-search.css` | Frontlines only |
| Frontlines category navigation | `frontlines/category-navigation.js` | Frontlines only |
| Project release history | `CHANGELOG.md` | Global |
| Live scores and users | `data/*.json` and `<instance>/data/*.json` | Runtime only; do not commit |

For global API changes, review all four APIs:

- `api.php`
- `collide/api.php`
- `youth/api.php`
- `frontlines/api.php`

## Testing

Static PHP tests are under `scoreboard/tests/`. Relevant recent tests include:

- `frontlines-categories-test.php`
- `frontlines-roster-search-test.php`
- `navigation-pages-test.php`
- `runtime-samples-test.php`
- `change-password-test.php`

Example from the `scoreboard` directory:

```powershell
php .\tests\frontlines-roster-search-test.php
php .\tests\navigation-pages-test.php
```

## Production URL

Hostinger deploys the repository under `/github`:

- `https://jasr.me/github/scoreboard/`

Make sure all runtime `data/` directories are writable by PHP. Git deployments should update committed code and sample files without replacing existing ignored live JSON files.

## Frontlines roster pending item

- **Andrew Johnson** — listed in the 2026 Frontlines cabin PDF but not yet assigned to a team color. Add him to the roster defaults after a team is selected.
