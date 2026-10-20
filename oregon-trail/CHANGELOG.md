<!--
File Name     : CHANGELOG.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Cumulative project changelog, verified history, revision policy, and remaining work.
Revision      : 1.0.3
Created Date  : 2026-10-19
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.3 - Recorded project Rev 1.1.0 event interruptions and completed Rev 1.0.1 release.
  Rev 1.0.2 - Recorded Rev 1.0.1 asset-cache, startup, and phone-layout fixes.
  Rev 1.0.1 - Documented framework alignment and repository-wide development requirements.
  Rev 1.0.0 - Established independent file revision tracking; Cumulative project changelog, verified history, revision policy, and remaining work.
-->
# Oregon Trail changelog

## Project Rev 1.1.0 — 2026-10-20 — Unreleased

- Added persistent, focused amber alerts next to actions; blocked Continue until emergencies and event decisions are resolved.
- Paused auto-play on events, landmarks, river choices, and endings; removed green first-choice highlighting on decisions.
- Added required named-traveler care, hunting when food is empty, two-day wheel repairs, funeral/burial and final-party memorials.
- Preserved alerts and burial records across saves/reloads; retained legacy saves and historical deaths.
- Kept same-turn landmarks from replacing unresolved events; required final river crossings before winning.
- Disabled automatic wild-game food rewards for new journeys; existing content snapshots retain their probabilities.
- Labeled finished saved journeys Won/Lost and kept restarting an explicit player choice.
- Added deterministic runtime tests for the decision and offline-restoration paths.

## Project Rev 1.0.1 — 2026-10-19 — Merged in PR #115

- Prevented new HTML from silently running older scripts/styles by using release-specific URLs and integrity hashes.
- Preserved release queries in offline cache keys; never substitute another release's assets.
- Added a self-contained update page that applies a pending offline-worker update without clearing saved journeys.
- Added visible startup failures and a static project revision fallback.
- Reset native party-button sizing/appearance to keep the traveler panel within its grid cell on phones.
- Added project-local HTTP revalidation rules for HTML, scripts, styles, configuration, and changelog.

## Project Rev 1.0.0 — 2026-10-19 — Merged in PR #113

First explicitly numbered project revision. Earlier work was unversioned; the historical dates below refer to Git changes, not invented release numbers.

- Randomized suggested family names and ages; preserved manual ages through 90.
- Added a traveler roster with individual health, illness, remaining members, and retained deceased travelers.
- Preserved individual details in autosaves and migrated legacy surviving members.
- Added distinct pixel-art scenes for forts, landmarks, mountains, towns, and perpendicular river crossings.
- Added configurable age-weighted illness targeting: younger children and older adults have greater susceptibility. Overall illness-event frequency remains controlled separately by `events[].chancePercent`.
- Added the visible project revision/date, independent file revisions, full file metadata, and this cumulative changelog.
- Documented framework alignment and added the root README development requirements and complete project/support-folder index.
- Added offline caching for new modules, project revision, and changelog.

## Earlier unversioned project history

### 2026-10-11

- Merged PR #99: separate journey saves, action autosaves, reload recovery, configurable stops/actions/events, and offline caching.

### 2026-10-08

- Created the browser game, family setup, end screens, auto-play, and games-played counter.

## Verification and remaining work

- Automated rules, runtime recovery, roster, scenery, probability, and metadata checks are included under `tests/`.
- Real-device dialog/layout and airplane-mode reload remain to be verified.
- Mandatory emergency decisions and funeral/burial actions are implemented in Rev 1.1.0. Full per-journey event journals and buying/trading remain future work.

## Revision policy

- `version.js` is the source of the project revision and updated date shown on the main page.
- Each file has its own independent revision. Increase it only when that file changes; retain earlier file changelog entries and its original created date.
- Headers were introduced with file Rev 1.0.0 on 2026-10-19. Older pre-header changes remain in Git history and the historical notes above.
- JSON uses `_header` metadata because comments would invalidate JSON. The PNG preview uses a native PNG `Comment` text chunk containing the same header fields.
