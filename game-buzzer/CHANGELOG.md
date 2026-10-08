# Changelog

## 1.2.1 — 2026-10-08

- Add View results navigation from voting, completing links in both directions. Counts and rankings remain on the separate results page.

## 1.2.0 — 2026-10-08

- Show a shared revision and source update date/time footer on game, voting, and results pages.
- Label tied popularity ranks explicitly and keep question IDs distinct from ranks.
- Identify results as a separate page with a direct link back to voting.

- Shuffle leader voting questions independently of popularity, preserving their order during a visit.
- Move combined counts, rankings, activity, and top-10 copying to a separate public results page.
- Preserve existing saved votes and suggestions.

## 1.1.1 — 2026-10-07

- Version JavaScript and CSS URLs by file content to prevent old cached assets from disabling newly deployed shared-login controls.

## 1.1.0 — 2026-10-07

- Add one-click shared-login setup restricted to the existing jasrasr central Super Admin.
- Register the project without account changes; preserve local configuration and game data.
- Create rooms through shared admin authentication and CSRF protection after activation.
- Keep guest invitations and existing room host capabilities working.
- Add end-to-end shared-authentication and data-preservation tests.

## 1.0.0 — 2026-10-06

- Add six-team phone buzzer, multiple phones per team, server acceptance ordering, countdown, round deadline, ranking, host scoring and projector chime.
- Add editable ten-question arrival survey, shared-device check-in, locked team majority predictions and idempotent reveal scoring.
- Add protected host/team invitations, private runtime storage, connection feedback and latency documentation.
- Add game-rule, concurrent-process and syntax validation.
