<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Curated open work from the Khootish roadmap and deployment review.
Revision      : 1.0.1
Created Date  : 2026-10-20
Modified Date : 2026-11-03
Changelog     :
  Rev 1.0.1 - Added runtime JSON and deployment preservation review from the former quiz-web-interactive note.
  Rev 1.0.0 - Summarized open roadmap work without duplicating completed features.
-->
# Khootish TODO

`ROADMAP.md` is the detailed backlog; `TASKLIST.md` contains a shorter overlapping list. Keep both synchronized as features ship.

## Documented priorities

- [ ] Add avatar selection, configurable question duration, and configurable scoring. See `ROADMAP.md` Next Up; the current `README.md` still describes a fixed 30-second timer.
- [ ] Add quiz duplication, deletion with confirmation, question ordering, and JSON import/export. See `ROADMAP.md` Quiz Management; quiz editing is already marked completed there.
- [ ] Add admin CSRF protection, rate limits for joins and answers, and damaged-JSON recovery before expanding public usage. See `ROADMAP.md` Reliability and Administration.
- [ ] Add keyboard and screen-reader support for host controls and timer updates. See `ROADMAP.md` Mobile and Accessibility.

## Deployment and runtime data

- [ ] Decide which Khootish JSON files are shipped examples and which are live runtime data. Review the currently tracked `data/games/123456.json` and `data/games/541672.json`, generated games and quizzes, and Question Bank and history files before changing tracking or ignore rules. Preserve any live Hostinger data during deployment; adding `.gitignore` rules does not untrack files already in Git. The July 2026 findings and related repository tasks are in [Deployment Safety TODO](../DEPLOYMENT-SAFETY-TODO.md).
