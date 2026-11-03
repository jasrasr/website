<!--
File Name     : DEPLOYMENT-SAFETY-TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Repository deployment safety follow-ups preserved from the former quiz-web-interactive folder.
Revision      : 1.0.0
Created Date  : 2026-11-03
Modified Date : 2026-11-03
Changelog     :
  Rev 1.0.0 - Preserved and updated the July 25 Hostinger follow-ups before removing the obsolete folder.
-->
# Deployment Safety TODO

Source: an untracked note dated 2026-07-25 in the former `quiz-web-interactive` folder. These observations came from Hostinger webhook output at that time. Recheck current server files and deployment behavior before changing ignore rules or removing any runtime data.

## Review Hostinger first

- [ ] Confirm whether server-generated data is intended to live inside each app folder and whether deployment preserves untracked or ignored runtime files.
- [ ] Review these unexpected root-level paths before ignoring or removing them: `.htaccess1`, `directory.php`, `CVC-Youth-Scoreboard/`, and `_backup/`.

## App-specific follow-ups

- [ ] `family-tracker`: Review live data under `data/` (`users`, `families`, `locations`, `trails`, `notices`, `persistent_logins`, `locks`, and `audit`). Decide ignore rules while retaining protection files and safe directory placeholders.
- [ ] `gps-eta`: Review generated `data/device-history/` files and decide whether they should be ignored. Check whether a tracked placeholder is needed.
- [ ] `weather`: Review generated `data/weather.json` and `data/history/` files. Preserve documentation and any required placeholders when setting ignore rules.
- [ ] `tv-binge-board`: Review the reported untracked `data/remember-tokens.json` and decide whether an ignore rule is needed.
- [ ] `khootish` (formerly `quiz-web-interactive`): Lock files under `data/locks/` are already ignored. Review generated `data/games/` and `data/quizzes/` JSON, as well as Question Bank and history JSON added since the original note. Decide which examples to keep tracked, which runtime files to ignore, and whether any existing tracked game JSON should be removed from Git tracking. Preserve live data and tracked examples/placeholders. See [Khootish TODO](khootish/TODO.md).

## Original ignore proposals to evaluate

These patterns came from the July 2026 note. They are proposals, not verified rules for the current apps or server.

| App | Proposed patterns |
| --- | --- |
| `family-tracker` | `data/*`, `!data/.htaccess`, `!data/*/.placeholder` |
| `gps-eta` | `data/device-history/*`, `!data/.htaccess` |
| `weather` | `data/weather.json`, `data/history/*`, `!data/history/README.md`, `!data/README.md` |
| `tv-binge-board` | `data/remember-tokens.json` |
| `khootish` | `data/games/*.json`, `data/quizzes/quiz-*.json` |

For Khootish, the original note called out `data/games/.gitkeep`, `data/quizzes/.gitkeep`, and `data/quizzes/example-general-knowledge.json` as files to retain. Check the current tracked files before applying the proposed patterns; existing tracked files remain tracked until explicitly removed from the Git index.

After reviewing the live server, update the appropriate app ignore rules and verify that deployments leave runtime files in place.
