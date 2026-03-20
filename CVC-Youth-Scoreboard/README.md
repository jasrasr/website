# CVC Youth Scoreboard

A PHP scoreboard app for tracking six youth division scores with two pages:

- `index.php` — scorekeeper admin page to update scores.
- `viewer.php` — read-only public display page for spectators.

## Features

- Six preconfigured teams/divisions with large, color-coded score cards.
- Quick buttons for `+1`, `+3`, `+5`, `+10`, `-1`, `-3`, `-5`, and `-10`.
- Custom positive or negative score entry for each team.
- Reset one team or reset all teams at once.
- Rename teams and update the scoreboard title from the admin page.
- Scores saved to `data/scores.json` after each change.
- Viewer page automatically refreshes every 2 seconds.
- Admin page polls every 10 seconds; skips re-render when an input is focused.
- Dynamic viewer grid columns that adapt to the number of teams.
- Score font scales by viewport height for large display screens.
- Responsive layout that fills large screens and adapts to phones/tablets including Safari mobile.
- Multiple scorekeepers supported via file locking.

## Demo

A live demo is available at:

- `https://jasr.me/scoreboard-demo/` — admin page (try it out)
- `https://jasr.me/scoreboard-demo/viewer.php` — viewer page

> Note: `https://jasr.me/scoreboard/` is the production scoreboard and is not open to the public.

## Deploying

Upload the contents of `CVC-Youth-Scoreboard` into your desired folder on the host. Make sure the `data` folder is writable by PHP so the app can update `data/scores.json`.
