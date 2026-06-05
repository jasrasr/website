# Changelog

## 2026-06-05

### Added
- Public viewer, full admin (`enter-scores.php`), and quick entry (`enter-scores-quick.php`) pages now show a place-rank badge on every team card: `1st`, `2nd`, `3rd`, `4th`, and so on.
- Top 3 ranks use gold, silver, and bronze badges; 4th and below use a neutral badge.
- Ranks recompute live on every render (every 2 seconds on the viewer, every 10 seconds on admin/quick-entry), so they update alongside scores.
- Tied scores share the same rank (standard competition ranking: `1, 1, 3, 4`).
- Quick entry shows the rank badge on each team-select button and inline next to the selected team's name.

## 2026-06-02

### GitHub Issue Updates
- Issue #3: Added a mobile landscape viewer rule so scoreboards can show three columns wide without the prior two-column shrink.
- Issue #4: Public viewer now orders teams from highest score to lowest score, with team name as the tie-breaker.
- Issue #5: Quick-entry score buttons are now `+1`, `+10`, `+100`, and `+1000`; negative quick buttons were removed, and the manual amount area explains using `-1`, `-10`, or another negative number for minus scoring.
- Issue #6: Admin score cards now sort teams alphabetically by name, and quick-entry score text is centered.
- Issue #7: Shared button styling now centers button labels.
- Added `tests/github-issues-layout-test.php` to lock in the issue-driven UI behavior.
- Full admin score buttons now also use only `+1`, `+10`, `+100`, and `+1000`, with a note to use the custom amount field for negative scoring.
- Phone landscape viewer layout now uses normal scrolling document flow so rows do not overlap when browser chrome reduces viewport height.

### Added
- Added a web-viewable changelog page linked from the admin footer.
- Added an admin-only scoreboard navigation page for Root, Collide, Youth, and Frontlines.
- Documented where global and instance-specific scoreboard changes should be made.
- Added public-safe runtime sample files for ignored live data:
- `data/scores.sample.json`
- `data/users.sample.json`
- `data/audit.sample.json`
- `collide/data/scores.sample.json`
- `collide/data/audit.sample.json`
- `youth/data/scores.sample.json`
- `youth/data/audit.sample.json`
- `frontlines/data/scores.sample.json`
- `frontlines/data/audit.sample.json`
- Added `data/README.md` with runtime-data deployment notes.
- Added `tests/runtime-samples-test.php` to verify sample files and first-run password behavior.

### Fixed
- Fixed the Frontlines 12-team viewer layout so score boxes do not collapse at tablet-width layouts.
- Restored random first-run password generation in `auth.php` so public source no longer defines usable default passwords.

### Verified
- Audit logging records score changes, team resets, board resets, team renames, and title updates.
- Runtime sample files contain valid JSON where applicable.
- `auth.php` now uses `bin2hex(random_bytes(8))` for first-run generated passwords.

### File Revision Inventory

This inventory was built from each file's header revision notes.

#### Root PHP
- `admin-users.php` revision 1.0.0, modified 2026-04-13: Admin-only user management and merged audit log page.
- `api.php` revision 1.4.0, modified 2026-04-13: Root scoreboard API with query-param routing, team/title rename actions, negative scores, auth, and audit logging.
- `auth.php` revision 1.4.0, modified 2026-06-02: Shared auth library with sessions, roles, scoreboard access, audit helpers, signed-in password changes, and random first-run passwords.
- `change-password.php` revision 1.0.0, modified 2026-05-28: Signed-in self-service password update page.
- `changelog.php` revision 1.0.0, modified 2026-06-02: Signed-in web viewer for `CHANGELOG.md`.
- `enter-scores.php` revision 1.3.0, modified 2026-06-02: Root admin page with auth data attributes, change-password URL, changelog URL, and all-scoreboards URL.
- `enter-scores-quick.php` revision 1.1.0, modified 2026-05-28: Root compact score entry page with change-password URL.
- `index.php` revision 1.1.0, modified 2026-04-09: Root public viewer page after admin moved to `enter-scores.php`.
- `login.php` revision 1.0.0, modified 2026-04-13: Login page and session creation.
- `logout.php` revision 1.0.0, modified 2026-04-13: Session destroy and login redirect.
- `scoreboard_lib.php` revision 1.0.0, modified 2026-04-09: Root default team data and JSON read/write helpers.
- `scoreboards.php` revision 1.0.0, modified 2026-06-02: Admin-only navigation for all scoreboard instances.

#### Collide Instance
- `collide/api.php` revision 1.2.0, modified 2026-04-13: Collide API with negative scores, auth, and audit logging.
- `collide/enter-scores.php` revision 1.3.0, modified 2026-06-02: Collide admin page with changelog and all-scoreboards URLs.
- `collide/enter-scores-quick.php` revision 1.1.0, modified 2026-05-28: Collide compact score entry page with change-password URL.
- `collide/index.php` revision 1.0.0, modified 2026-04-09: Collide public viewer page.
- `collide/scoreboard_lib.php` revision 1.0.0, modified 2026-04-09: Collide six-team defaults and data helpers.

#### Youth Instance
- `youth/api.php` revision 1.2.0, modified 2026-04-13: Youth API with negative scores, auth, and audit logging.
- `youth/enter-scores.php` revision 1.3.0, modified 2026-06-02: Youth admin page with changelog and all-scoreboards URLs.
- `youth/enter-scores-quick.php` revision 1.1.0, modified 2026-05-28: Youth compact score entry page with change-password URL.
- `youth/index.php` revision 1.0.0, modified 2026-04-09: Youth public viewer page.
- `youth/scoreboard_lib.php` revision 1.0.0, modified 2026-04-09: Youth eight-team defaults and data helpers.

#### Frontlines Instance
- `frontlines/api.php` revision 1.2.0, modified 2026-04-13: Frontlines API with negative scores, auth, and audit logging.
- `frontlines/enter-scores.php` revision 1.3.0, modified 2026-06-02: Frontlines admin page with changelog and all-scoreboards URLs.
- `frontlines/enter-scores-quick.php` revision 1.1.0, modified 2026-05-28: Frontlines compact score entry page with change-password URL.
- `frontlines/index.php` revision 1.0.0, modified 2026-04-09: Frontlines public viewer page.
- `frontlines/scoreboard_lib.php` revision 1.1.0, modified 2026-04-13: Frontlines defaults updated from 10 to 12 teams.

#### Shared Frontend
- `public/app.js` revision 1.16.0, modified 2026-06-05: Shared viewer/admin logic, polling, rename/title support, dynamic viewer layout, activity log, footer actions, change-password link, changelog link, admin-only scoreboards link, admin A-Z team ordering, viewer score ordering, full-admin positive quick buttons, and 1st/2nd/3rd place rank badges on viewer and admin team cards.
- `public/quick-entry.css` revision 1.3.0, modified 2026-06-05: Compact quick-entry styling with footer navigation, mobile-friendly team buttons, centered score text, compact quick score buttons, manual negative-score note, and rank-badge anchoring on team buttons and inline selected-team header.
- `public/quick-entry.js` revision 1.4.0, modified 2026-06-05: Compact quick-entry behavior with footer navigation, change-password link, `+1/+10/+100/+1000` quick buttons, manual negative-score note, and 1st/2nd/3rd place rank badges on team buttons and selected-team header.
- `public/styles.css` revision 1.7.0, modified 2026-06-05: Shared dark responsive styling, auth/admin/audit styles, footer actions, viewer header affordance, 12-team tablet-width layout fix, centered button labels, three-wide mobile landscape viewer layout, landscape row-flow fix, and gold/silver/bronze rank-badge styles.

#### Tests
- `tests/change-password-test.php` revision 1.0.0, modified 2026-05-28: Lightweight verification for signed-in password changes.
- `tests/github-issues-layout-test.php` revision 1.0.0, modified 2026-06-03: Static verification for GitHub issue driven scoreboard layout updates.
- `tests/navigation-pages-test.php` revision 1.0.0, modified 2026-06-02: Static verification for changelog and admin navigation pages.
- `tests/runtime-samples-test.php` revision 1.0.0, modified 2026-06-02: Static verification for public-safe runtime samples and random first-run password behavior.
