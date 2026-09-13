# CVC Youth Scoreboard

A PHP scoreboard application for tracking team scores across multiple ministry instances, each with its own teams, runtime data, viewer, and score-entry pages.

Current project version: **v1.19.1**

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

## Collide-only features

### Team motto and walk-up song

- `collide/collide-extras.js` adds a Collide-only UI layer without changing the shared scoreboard app for Default, Youth, or Frontlines.
- Each Collide team can have a short subtitle/motto stored in `collide/data/scores.json` as `motto`.
- Each Collide team can have one walk-up song stored as `walkup_song` metadata.
- The public Collide viewer shows the motto and a **Walk-up song** button when a song exists.
- The Collide full-admin page adds a per-team motto field, audio upload field, play button, and remove-song button.
- `collide/team-meta.php` handles authenticated motto saves, audio uploads, and song removal.
- Uploaded audio files are stored under `collide/media/walkup/` and are intentionally ignored by Git.
- Supported upload types: MP3, M4A/AAC, WAV, OGG, and WEBM up to 15 MB.

## Frontlines-only features

### Category scoring

- `frontlines/enter-scores-category.php` lets scorers and admins award predefined point categories with one tap.
- `frontlines/edit-categories.php` lets Frontlines admins manage category names, point values, award limits, and active status.
- User-facing navigation calls the scoring page **Add Category Score**.
- **Add Category Score** appears near the top of both Frontlines full entry and quick entry, as well as in the relevant footer/navigation areas.
- Ranked categories can be awarded in any order using fixed values from `12000` down to `1000`; after a team receives a ranked category, that category is hidden for that team.
- Category display order is controlled by the **Order** field in Edit Categories. Lower numbers appear first; ties sort by category name.

### Searchable roster

- `frontlines/teams.php` is the public roster.
- `frontlines/edit-roster.php` is the admin-only roster editor.
- The roster search filters immediately by team name, leader, member, gender/grade suffix, or sponsor.
- Multiple search words use AND matching. For example, `Alex 12` only shows cards containing both terms.
- While a search is active, matching team cards show only the matching leader/member/sponsor rows. Clearing search restores the full roster.
- Search runs entirely in the browser and does not modify the roster JSON.
- The roster navigation links are located below all team cards.

### Viewer behavior

The Frontlines viewer opts in to `data-viewer-team-limit="3"`. After sorting by score, it shows the top 3 scoring teams plus any additional teams tied with the third-place score. If every team is tied at `0`, all teams remain visible. Other scoreboard instances are unaffected.

## Appearance

- Dark mode is the default theme for all scoreboard pages.
- The shared **Light mode** / **Dark mode** button is loaded on the main viewer, login, scoreboard picker, score-entry, quick-entry, Frontlines roster, and Frontlines category-entry pages.
- The browser saves the selected theme in local storage as `cvc-scoreboard-theme`.
- Clearing that browser storage key returns the scoreboard to the default dark mode.

## Authentication and navigation

- `requireAuth($scoreboardId, $loginUrl)` requires a signed-in user with access to the requested scoreboard.
- `requireAuthJson($scoreboardId)` is the JSON/API equivalent.
- `requireSignedIn($loginUrl)` allows any authenticated user.
- `requireAdmin($loginUrl)` requires the `admin` role.
- Login preserves the requested scoreboard destination. A login or forced password-change flow started from Frontlines returns to the requested Frontlines page instead of falling back to the Default scoreboard.
- First-run and administrator-reset passwords require a password change before scoreboard access.
- The forced password-change page includes **Cancel and return to login**. Canceling signs the temporary session out first so the user does not loop back to the same page.
- `scoreboards.php` lists only the scoreboard instances the signed-in user may access.
- `changelog.php` displays `CHANGELOG.md` to signed-in users.

## Runtime data and deployment safety

Live runtime files are ignored by Git. Committed sample files are deployed, while an existing live file is left unchanged.

Important authentication files:
