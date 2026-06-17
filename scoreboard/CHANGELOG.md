# Changelog

Current project version: **v1.7.0**

## v1.7.0 - 2026-06-17

### User management: disable/enable (`admin-users.php` v1.3.0, `auth.php` v1.10.0)

Added a soft-disable toggle on user accounts so you can park a user (e.g., an emergency admin) without deleting their history. Useful as a self-recovery lifeline if you ever lock yourself out of your primary admin account.

- New `disabled: bool` field on user records (default `false`). `makeUser()` sets it; existing users without the field are treated as enabled.
- `attemptLogin()` rejects disabled accounts the same way it rejects wrong credentials — no information leak about which usernames exist.
- New **Status** column on `admin-users.php` shows Active / Disabled per user.
- New **Disable** (warning button) / **Enable** (positive button) toggle per row with confirm dialog.
- **Guardrails:**
  - You cannot disable your own account (avoids accidental self-lockout).
  - You cannot disable the last active admin (would leave the system without any working admin).
- Disabled rows render dimmed in the table for at-a-glance scanning.

### Styles (`public/styles.css` v1.11.0)
- Added `.user-status` active/disabled pill styles and `.row-disabled` dimming for the admin user list.

## v1.6.1 - 2026-06-17

### Auth (`auth.php` v1.9.0)
- Extended session retention so users stay signed in much longer:
  - `session.gc_maxlifetime` raised to **7 days idle** (was PHP's 24-minute default).
  - `session.cookie_lifetime` raised to **30 days** (was 0 / browser-session).
- Hardened the session cookie with `SameSite=Lax`, `HttpOnly`, and `Secure` flag on HTTPS.
- Note: server-side session files live in PHP's `session.save_path` (typically `/tmp`), outside the repo — `git pull` from the deploy webhook does not affect them.

## v1.6.0 - 2026-06-17

### Frontlines Goal Categories (new feature, Frontlines-only)

A new way to score Frontlines events: pre-define point-value goals (e.g., "Water Challenge +100", "Late to Activity -25") and award them with a single tap on the scorer page. Modeled after the existing roster feature — Frontlines-only, with separate admin-edit and scorer-entry pages.

**New pages:**
- `frontlines/enter-scores-category.php` — scorer + admin page. Pick a team, then tap a goal button to award its point value. Buttons are color-coded by sign (green for positive, red for penalty). Buttons visually disable once a team has reached the category's `maxAwardsPerTeam` cap (server-side cap enforcement is the source of truth — the API returns 409 if the client miscounts). Collapsible recent-activity log mirrors the quick-entry pattern.
- `frontlines/edit-categories.php` — admin-only page for managing the category list. Add/edit/delete categories, set point value (signed integer, negative allowed), set `maxAwardsPerTeam` (positive integer or unlimited), toggle the `active` flag.

**New data file:** `frontlines/data/categories.json` (protected by the existing `.htaccess`). Sample seed file added at `frontlines/data/categories.sample.json`.

**New REST API actions on `frontlines/api.php` (v1.3.0):**
- `GET ?action=list-categories` — any authed Frontlines user.
- `POST ?action=add-category` — admin only.
- `POST ?action=update-category&category=<id>` — admin only.
- `POST ?action=remove-category&category=<id>` — admin only.
- `POST ?action=award-category&team=<id>&category=<id>` — admin **+ scorer**. Enforces `maxAwardsPerTeam` by counting prior `award-category` audit entries; returns 409 if the cap is hit. Logs a verbose audit entry (team name, category name, signed points, new score).

**Lib (`frontlines/scoreboard_lib.php` v1.2.0):**
- `defaultCategoriesData`, `ensure/read/writeCategoriesData` (with `flock`-protected writes), `findCategoryIndex`, `countCategoryAwards`.

**Cross-page navigation:**
- `app.js` (v1.28.0) and `quick-entry.js` (v1.14.0) now render **Enter Categories** and **Edit Categories** footer links when the corresponding URLs are present on the body element. Non-Frontlines instances simply don't set the URLs, so the links don't appear there.
- `frontlines/enter-scores.php` (v1.5.0) and `frontlines/enter-scores-quick.php` (v1.4.0) expose `data-category-entry-url` for everyone and `data-edit-categories-url` only when `role === 'admin'`.
- `frontlines/teams.php` (v1.6.0) and `frontlines/edit-roster.php` (v1.1.0) now link to **Enter Categories** and **Edit Categories** from their existing admin header areas.

**Permissions:**
- **Edit** categories: admins only (server-side + UI-gated).
- **Award** categories: admins + scorers with Frontlines access.

**Tests:**
- New `scoreboard/tests/frontlines-categories-test.php` exercises the lib (read/write/find/count-from-audit), verifies the new page shells, and asserts the cross-page navigation wiring.

## v1.5.1 - 2026-06-17

### Full Admin (`enter-scores.php`)
- The **Add Score / Subtract Score** toggle now sits **above** each team card's `+1 / +10 / +100 / +1000` quick buttons (was previously below them), matching the quick-entry layout.
- Those quick buttons now flip to red `-1 / -10 / -100 / -1000` the moment **− Subtract Score** is selected on that team's card, so one tap subtracts. Mode is still tracked independently per-team across the 10-second admin re-render.
- Dropped the unused `.custom-controls-stacked` CSS class now that the mode row lives outside the custom-amount form.

## v1.5.0 - 2026-06-17

### Full Admin (`enter-scores.php`) and Quick Entry (`enter-scores-quick.php`)
- Replaced the typed "Custom +/- amount" / "Manual +/- amount" field with an **Add Score / Subtract Score** mode toggle row above a digits-only amount input. Pick the green **+ Add Score** (default) or red **− Subtract Score** button, then enter an amount. The input live-previews the negative sign (e.g. `-100`) whenever Subtract mode is active, so iOS users no longer need to type a minus sign that isn't on the iOS numeric keypad.
- The previous fix that switched the custom-amount input to `type=text inputmode=numeric pattern=-?[0-9]*` so Android keypads expose a minus key is still in place; the toggle simply makes subtracting work on every mobile platform without relying on the keypad.
- Quick entry helper text under the manual form now reads: "Pick Add or Subtract, then enter an amount. Click 'Apply' or submit or press enter."
- Quick entry's **+1 / +10 / +100 / +1000** buttons now flip to **-1 / -10 / -100 / -1000** (and turn red) the moment **− Subtract Score** is selected, so the quick-tap buttons match the chosen mode without typing.
- Quick entry's Add / Subtract toggle now sits **above** the quick-score grid (was previously between the grid and the amount input) so it's visually obvious that the toggle controls the score buttons below it.

### Tests / Mockups
- Added `tests/negative-scoring-mockup.html`, a standalone interactive mockup of three negative-scoring UI options (side-by-side mode toggle, single sign-flip toggle, and paired Add/Subtract submit buttons) for comparing layouts on phones. Kept in the repo for future-reference.

## v1.4.0 - 2026-06-13

### Changelog
- Added the current overall project version and versioned release headings alongside each changelog date.

### Default Scoreboard
- Renamed the root scoreboard's user-facing label to **Default** while keeping the internal `root` id for existing permissions and data.
- Renamed the default/root scoreboard title to **Live Scoreboard** in the default data, sample data, root page titles, and shared frontend fallbacks.
- Renamed the default team labels from grade/gender names to **Team 1** through **Team 6** in the root defaults and committed sample/live data.

### Full Admin (`enter-scores.php`)
- The **Add Team** form now shows visible labels for the new team name and color fields, and the Add Team button includes an "or press Enter" helper.

### Auth
- First-run generation now creates only two temporary users: `admin` and `scorer`, both using password `password` and both forced to change it before continuing.
- First-run generated users and admin-created/reset users now must change their password before they can continue into scoreboard pages or APIs.
- `login.php` sends forced-reset users directly to `change-password.php`.
- After a successful password change, the user's `must_change_password` flag is cleared and their line is removed from `data/first-run-credentials.txt`.

## v1.3.0 - 2026-06-12

### Frontlines Roster
- Team member rows now show a `Name - Gender/Grade` suffix (e.g., `Alex Lamb - M/HS`). Falls back gracefully when only one field is set, or shows just the name when neither is.
- Imported gender/grade for every youth in the roster from the 2026 cabin PDF (`frontlines 2026 cabins.pdf`). Cabin assignments determine grade buckets (6 / 7 / 8 / HS / GRAD); gender is now confirmed from the cabin (corrected M↔F for Grey Hileman, Chai Beard, Quinn Patton, Misha Tinter, Alden Brinkley).
- Added the 4 new kids who joined since the previous import: **Chris Banto** (Dark Green), **Vivien Banto** (Pink), **Claudia Banto** (Purple), **Olga Soljaga** (Smoke).
- Renamed `C.J Fitzgerald` → `Connor "CJ" Fitzgerald` in the CSV/PHP defaults.
- `teams.php` header now shows a `Roster last updated: ...` timestamp pulled from `data/team-roster.json`. The intro copy was collapsed into one paragraph with the random-order sentence italicized.
- README now has a "Frontlines Roster — Pending" section flagging **Andrew Johnson** (HS / Cedar House) as a kid in the PDF who still needs a team color assigned.

### Quick Entry (`enter-scores-quick.php`)
- New **Reset Score to Zero** button (with confirm dialog) for the selected team.
- New collapsible **Show Recent Activity** audit log section — mirrors the full admin view. Available to scorers as well, since the API endpoint already allowed it.
- Page no longer auto-selects the first team on load; users must explicitly pick a team before any score buttons appear. Prevents accidental score changes after refresh. A "Select a team above to enter scores." placeholder shows in the action panel until a team is chosen.
- Renamed **Viewer** footer button to **View Scoreboard**.

### Full Admin (`enter-scores.php`)
- Per-card **Reset Team** button renamed to **Reset Score to Zero** for clarity (it only zeroes the score; it never touched the team name).
- Per-card **Remove Team** button added (red `negative` button next to Reset, with confirm). Deletes the team and its score from the data file.
- New **Add Team** form between the team grid and activity log: text input + HTML5 color picker + Add Team button. Server generates a unique `team-{random}` id and appends to the teams array.
- Top banner now has **View Scoreboard** (opens viewer in new tab) and **Quick Score** (jumps to quick entry) links. The footer button previously labelled "Open Viewer Page" is now also **View Scoreboard** for consistency.
- Custom amount **Apply** button and team-rename **Rename** button now sit inline with their respective inputs instead of wrapping to their own row.

### API
- New `add-team` and `remove-team` actions on every instance (`api.php`, `collide/api.php`, `youth/api.php`, `frontlines/api.php`). Both are POST, require auth + scoreboard access, and write to the audit log. `add-team` validates the name and the `#RRGGBB` color (defaulting to `#64748b` if malformed).

### User Admin (`admin-users.php`)
- **Fixed broken Edit button.** The inline `onclick` was embedding raw `json_encode()` output containing unescaped double quotes, which silently broke the HTML attribute and killed the click handler. Refactored to `data-*` attributes + event delegation.
- Edit modal can now change the **username** (with uniqueness check). Renaming yourself also updates the active session so subsequent requests use the new name.
- New **Modified** column next to **Created**. `modified_at` is now tracked on user creation, edit, and password reset (defaults to `created_at` for new users). Existing pre-existing users show blank until their first edit.

### Auth
- Signed-in users who hit a scoreboard they don't have access to (e.g., `/youth/enter-scores.php` for a scorer without Youth) are now redirected to `scoreboards.php?denied=<id>` instead of seeing the 403 error page. `scoreboards.php` reads the param and shows a warning banner: `"You do not have access to the <Name> scoreboard. Pick one of yours below."`

## v1.2.0 - 2026-06-08

### Access & Navigation
- `changelog.php` is now accessible to any signed-in user (was gated on root-scoreboard access). The Scoreboard button in the changelog header now reads **Scoreboards** and links to `scoreboards.php` (the access-filtered nav hub) instead of the root admin page.
- `scoreboards.php` is now accessible to any signed-in user, not just admins. The list is filtered to only the scoreboard instances the signed-in user has access to. Admins still see all four; scorers see only their assigned ones. Header role label reads "Admin" or "Scorer" depending on the signed-in user.
- Scoreboards footer button on the admin page (`enter-scores.php`) is now visible to all signed-in users (was admin-only). "Manage Users" remains admin-only.
- Added a **Scoreboards** footer link to the quick entry page (`enter-scores-quick.php`) so scorers can jump between their accessible instances without going through the full admin page first.
- `scoreboards.php` now has a bottom action bar (matching the admin page pattern) with **Changelog**, **Change Password**, and **Sign Out** buttons. Header right-side buttons were removed in favor of this more visible footer bar.
- Added `requireSignedIn()` helper to `auth.php` for pages open to any authenticated user.

### Fixed
- Footer link buttons (`.au-btn`) on `enter-scores.php` and `enter-scores-quick.php` now center their text horizontally; previously Quick Entry, Changelog, Change Password, Sign Out (and all `*-quick.php` footer buttons in mobile-stretched layouts) left-aligned because the shared class was missing `justify-content: center`.
- Quick entry team buttons (`enter-scores-quick.php`) now sort A-Z by name, matching the full admin page.
- Viewer page no longer compresses the top rows on narrow-desktop layouts (e.g., 50% docked windows). The `--viewer-rows` custom property now lets the responsive breakpoint actually override row sizing; previously an inline JS style took priority over the media query and squeezed the first rows. Below 1100px width the viewer page now scrolls naturally with a 180px row minimum and a 110px score-box minimum.
- Quick entry "Last updated" timestamp no longer includes the locale comma between date and time (now renders as `6/6/26 4:25 PM`).

### Added
- Notes in `enter-scores.php` and `enter-scores-quick.php` that read "Teams are sorted A-Z by name." Note in the viewer header that reads "Teams sorted by score (1st, 2nd, 3rd...)".
- Quick entry now shows the running script revision (`v1.5.0`) directly under "Last updated" so testers can confirm which version they are looking at.

## v1.1.0 - 2026-06-05

### Added
- Public viewer, full admin (`enter-scores.php`), and quick entry (`enter-scores-quick.php`) pages now show a place-rank badge on every team card: `1st`, `2nd`, `3rd`, `4th`, and so on.
- Top 3 ranks use gold, silver, and bronze badges; 4th and below use a neutral badge.
- Ranks recompute live on every render (every 2 seconds on the viewer, every 10 seconds on admin/quick-entry), so they update alongside scores.
- Tied scores share the same rank (standard competition ranking: `1, 1, 3, 4`).
- Quick entry shows the rank badge on each team-select button and inline next to the selected team's name.

## v1.0.0 - 2026-06-02

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
- `admin-users.php` revision 1.2.0, modified 2026-06-13: Admin-only user management and merged audit log page; created/reset passwords require a change and root access displays as Default.
- `api.php` revision 1.4.0, modified 2026-04-13: Root scoreboard API with query-param routing, team/title rename actions, negative scores, auth, and audit logging.
- `auth.php` revision 1.8.0, modified 2026-06-13: Shared auth library with sessions, roles, scoreboard access, audit helpers, signed-in password changes, forced first-run/reset password changes, two temporary first-run users, and first-run credential cleanup.
- `change-password.php` revision 1.1.0, modified 2026-06-13: Signed-in self-service password update page with forced-change support.
- `changelog.php` revision 1.1.0, modified 2026-06-08: Signed-in web viewer for `CHANGELOG.md`; uses `requireSignedIn` and links back to `scoreboards.php`.
- `enter-scores.php` revision 1.4.0, modified 2026-06-13: Default admin page with auth data attributes, change-password URL, changelog URL, all-scoreboards URL, and Live Scoreboard page title.
- `enter-scores-quick.php` revision 1.3.0, modified 2026-06-13: Default compact score entry page with change-password and scoreboards URLs.
- `index.php` revision 1.2.0, modified 2026-06-13: Default public viewer page after admin moved to `enter-scores.php`.
- `login.php` revision 1.1.0, modified 2026-06-13: Login page and session creation; forced-reset users are sent to `change-password.php`.
- `logout.php` revision 1.0.0, modified 2026-04-13: Session destroy and login redirect.
- `scoreboard_lib.php` revision 1.2.0, modified 2026-06-13: Default team data and JSON read/write helpers; default title is Live Scoreboard and default team labels are Team 1 through Team 6.
- `scoreboards.php` revision 1.5.0, modified 2026-06-13: Navigation for scoreboard instances the signed-in user can access; root scoreboard displays as Default; bottom action bar with Changelog, Change Password, and Sign Out.

#### Collide Instance
- `collide/api.php` revision 1.2.0, modified 2026-04-13: Collide API with negative scores, auth, and audit logging.
- `collide/enter-scores.php` revision 1.3.0, modified 2026-06-02: Collide admin page with changelog and all-scoreboards URLs.
- `collide/enter-scores-quick.php` revision 1.2.0, modified 2026-06-08: Collide compact score entry page with change-password and scoreboards URLs.
- `collide/index.php` revision 1.0.0, modified 2026-04-09: Collide public viewer page.
- `collide/scoreboard_lib.php` revision 1.0.0, modified 2026-04-09: Collide six-team defaults and data helpers.

#### Youth Instance
- `youth/api.php` revision 1.2.0, modified 2026-04-13: Youth API with negative scores, auth, and audit logging.
- `youth/enter-scores.php` revision 1.3.0, modified 2026-06-02: Youth admin page with changelog and all-scoreboards URLs.
- `youth/enter-scores-quick.php` revision 1.2.0, modified 2026-06-08: Youth compact score entry page with change-password and scoreboards URLs.
- `youth/index.php` revision 1.0.0, modified 2026-04-09: Youth public viewer page.
- `youth/scoreboard_lib.php` revision 1.0.0, modified 2026-04-09: Youth eight-team defaults and data helpers.

#### Frontlines Instance
- `frontlines/api.php` revision 1.2.0, modified 2026-04-13: Frontlines API with negative scores, auth, and audit logging.
- `frontlines/enter-scores.php` revision 1.3.0, modified 2026-06-02: Frontlines admin page with changelog and all-scoreboards URLs.
- `frontlines/enter-scores-quick.php` revision 1.2.0, modified 2026-06-08: Frontlines compact score entry page with change-password and scoreboards URLs.
- `frontlines/index.php` revision 1.0.0, modified 2026-04-09: Frontlines public viewer page.
- `frontlines/scoreboard_lib.php` revision 1.1.0, modified 2026-04-13: Frontlines defaults updated from 10 to 12 teams.

#### Shared Frontend
- `public/app.js` revision 1.24.0, modified 2026-06-13: Shared viewer/admin logic, polling, rename/title support, dynamic viewer layout, activity log, footer actions, change-password link, changelog link, all-users scoreboards link, admin A-Z team ordering, viewer score ordering, full-admin positive quick buttons, 1st/2nd/3rd place rank badges, sort-order notes on admin and viewer pages, add/remove teams, labeled Add Team controls, and Live Scoreboard fallback title.
- `public/quick-entry.css` revision 1.4.0, modified 2026-06-08: Compact quick-entry styling with footer navigation, mobile-friendly team buttons, centered score text, compact quick score buttons, manual negative-score note, rank-badge anchoring on team buttons and inline selected-team header, and quick-status-block / quick-revision styles.
- `public/quick-entry.js` revision 1.10.0, modified 2026-06-13: Compact quick-entry behavior with footer navigation, change-password link, scoreboards link, `+1/+10/+100/+1000` quick buttons, manual negative-score note, 1st/2nd/3rd place rank badges, A-Z team button sorting, A-Z sort note, on-page revision display under last-updated, and Live Scoreboard fallback title.
- `public/styles.css` revision 1.9.2, modified 2026-06-13: Shared dark responsive styling, auth/admin/audit styles, footer actions, viewer header affordance, 12-team tablet-width layout fix, centered button labels, three-wide mobile landscape viewer layout, landscape row-flow fix, gold/silver/bronze rank-badge styles, `.sort-note` styling, `--viewer-rows` row sizing, narrow-desktop viewer scroll/min-height fix, centered `.au-btn` text, Frontlines roster styles, and labeled Add Team form styles.

#### Tests
- `tests/change-password-test.php` revision 1.1.0, modified 2026-06-13: Lightweight verification for signed-in password changes, forced-change clearing, and first-run credential cleanup.
- `tests/github-issues-layout-test.php` revision 1.2.0, modified 2026-06-13: Static verification for GitHub issue driven scoreboard layout updates and Add Team labels.
- `tests/navigation-pages-test.php` revision 1.3.0, modified 2026-06-13: Static verification for changelog project-version headings and scoreboard navigation pages with the Default label.
- `tests/runtime-samples-test.php` revision 1.3.0, modified 2026-06-13: Static verification for public-safe runtime samples, root default team labels, data-folder hardening, forced first-run password changes, and first-run admin/scorer generation.
