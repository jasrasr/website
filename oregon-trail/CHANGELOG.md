<!--
File Name     : CHANGELOG.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Cumulative project changelog, verified history, revision policy, and remaining work.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Established independent file revision tracking; Cumulative project changelog, verified history, revision policy, and remaining work.
-->
# Oregon Trail changelog

## Project Rev 1.0.0 — 2026-10-19 — Unreleased (PR #113)

First explicitly numbered project revision. Earlier work was unversioned; the historical dates below refer to Git changes, not invented release numbers.

- Randomized suggested family names and ages; preserved manual ages through 90.
- Added a traveler roster with individual health, illness, remaining members, and retained deceased travelers.
- Preserved individual details in autosaves and migrated legacy surviving members.
- Added distinct pixel-art scenes for forts, landmarks, mountains, towns, and perpendicular river crossings.
- Added configurable age-weighted illness targeting: younger children and older adults have greater susceptibility. Overall illness-event frequency remains controlled separately by `events[].chancePercent`.
- Added the visible project revision/date, independent file revisions, full file metadata, and this cumulative changelog.
- Added offline caching for new modules, project revision, and changelog.

## Earlier unversioned project history

### 2026-10-11

- Merged PR #99: separate journey saves, action autosaves, reload recovery, configurable stops/actions/events, and offline caching.

### 2026-10-08

- Created the browser game, family setup, end screens, auto-play, and games-played counter.

## Verification and remaining work

- Automated rules, runtime recovery, roster, scenery, probability, and metadata checks are included under `tests/`.
- Real-device dialog/layout and airplane-mode reload remain to be verified.
- Mandatory emergency decisions, funeral/burial actions, and full per-journey event journals are not implemented in this revision.

## Revision policy

- `version.js` is the source of the project revision and updated date shown on the main page.
- Each file has its own independent revision. Increase it only when that file changes; retain earlier file changelog entries and its original created date.
- Headers were introduced with file Rev 1.0.0 on 2026-10-19. Older pre-header changes remain in Git history and the historical notes above.
- JSON uses `_header` metadata because comments would invalidate JSON. The PNG preview uses a native PNG `Comment` text chunk containing the same header fields.
