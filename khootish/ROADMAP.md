# Khootish — Future Updates

Last reviewed: August 16, 2026

This file is the working backlog for planned improvements. Items can be reordered as priorities change.

## Next Up

- [ ] Add a visible team-color preview beside the color selector and selected hex value.
- [ ] Add avatar selection with simple built-in icons.
- [ ] Add configurable question duration instead of a fixed 30-second timer.
- [ ] Add configurable scoring options, including fixed points and speed-based points.
- [ ] Add confirmation prompts before destructive host actions.

## Quiz Management

- [ ] Duplicate a quiz as a starting template.
- [ ] Delete quizzes with confirmation.
- [ ] Reorder questions with move-up/move-down controls or drag-and-drop.
- [ ] Add optional question images.
- [ ] Add optional explanation text shown after revealing the correct answer.
- [ ] Import and export quizzes as JSON.
- [ ] Add categories and tags for filtering saved quizzes and Question Bank entries.
- [ ] Edit and delete Question Bank entries.
- [ ] Import and export the Question Bank as JSON.

## Gameplay and Display

- [ ] Automatically advance after all teams have answered, as an optional setting.
- [ ] Add sound effects for countdown, answer reveal, and leaderboard changes.
- [ ] Add a fullscreen projector mode.
- [ ] Add animated correct-answer and leaderboard transitions.
- [ ] Add a tie-breaker question mode.

## Reliability and Administration

- [ ] Add a game activity log for joins, answers, scoring, and host actions.
- [ ] Add stronger admin authentication and configurable credentials.
- [ ] Add CSRF protection to administrative actions.
- [ ] Add rate limiting for join and answer submissions.
- [ ] Add JSON validation and recovery for damaged data files.
- [ ] Add an installation/status diagnostic page for Hostinger permissions and PHP requirements.
- [ ] Add automated backups of quizzes before edits or deletion.

## Mobile and Accessibility

- [ ] Improve small-screen spacing and prevent browser zoom issues on form inputs.
- [ ] Add high-contrast and color-blind-friendly answer themes.
- [ ] Add keyboard navigation for all host controls.
- [ ] Add screen-reader labels and live announcements for timer/status changes.
- [ ] Test and document behavior on iPhone Safari, Android Chrome, desktop Chrome, Edge, and Firefox.

## Documentation and Versioning

- [ ] Centralize revision and Eastern Time modified timestamp so all pages display identical values.
- [ ] Add deployment and Hostinger setup instructions.
- [ ] Document the JSON file formats for quizzes, Question Bank entries, active games, and archived games.
- [ ] Add a release checklist for revision bump, modified timestamp, testing, and deployment verification.

## Completed

- [x] PHP and JSON architecture compatible with Hostinger shared hosting.
- [x] Reusable quizzes and separate active-game files.
- [x] Six-digit numeric invite codes.
- [x] Team name and team-color registration.
- [x] Four-answer questions with one correct answer.
- [x] Server-authoritative synchronized countdown.
- [x] Speed-based scoring and duplicate-answer protection.
- [x] Player, administrator, and shared display pages.
- [x] Correct-answer reveal and live leaderboard.
- [x] Example five-question general-knowledge quiz.
- [x] Example game using join code `123456`.
- [x] Visible revision and Eastern Time modified timestamp on the front page.
- [x] iPhone team-color selector display fix.
- [x] Reusable Question Bank stored separately from quizzes.
- [x] Optional **Add to Question Bank** checkbox for newly created questions.
- [x] Add existing Question Bank questions to a quiz as snapshot copies.
- [x] Search Question Bank entries by question text or any answer choice.
- [x] Preserve the correct answer when reusing a Question Bank entry.
- [x] Duplicate prevention for identical question-and-answer combinations.
- [x] Archive completed games with code, quiz, timestamps, rankings, scores, answers, and response times.
- [x] Search game history by quiz, team, invite code, or date.
- [x] Start a fresh game with the same six-digit invite code after archiving the previous session.
- [x] Preserve tied placements in archived rankings.
- [x] Show cumulative team statistics including games, wins, average score, and average placement.
- [x] Show cumulative question statistics including usage, accuracy, and average response time.
- [x] Add a host lobby showing connected teams before a game starts.
- [x] Allow the host to remove or rename a team from the lobby.
- [x] Prevent starting a game until at least one team has joined.
- [x] Show answer-submission counts to the host without revealing team choices.
- [x] Show team colors consistently in the host lobby, leaderboard, history, and final results.
- [x] Add a final podium/results screen on the host and shared display.
- [x] Rename the project folder and display name to **Khootish**.
- [x] Add an **Active Games** dashboard showing every launched game, status, team count, question progress, answer count, and expiration.
- [x] Allow a host to reopen and manage any active game from the dashboard.
- [x] Automatically close and archive games after 24 hours.
- [x] Run lightweight stale-game maintenance during normal site activity without requiring cron.
- [x] Add `CHANGELOG.md` with release entries.
- [x] Edit existing quizzes from the admin interface without creating duplicate quiz IDs.
