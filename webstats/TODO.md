<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Current open work for Webstats.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Summarized the existing roadmap and identity migration.
-->

# Webstats TODO

## Documented work

- [ ] Add server-side YOURLS redirect events; browser JavaScript cannot observe bare HTTP redirects. Source: `ROADMAP.md`.
- [ ] Add CSV export and configurable report widgets. Source: `ROADMAP.md`.
- [ ] Evaluate privacy-preserving browser/device aggregation and automated SPA route tracking with deduplication. Source: `ROADMAP.md`.
- [ ] Migrate dashboard authentication to shared identity while leaving public event collection working and preserving events. The shared framework exists, but Webstats still uses its own dashboard admin flow. Sources: `ROADMAP.md`, `user-management/MIGRATION-TRACKER.md`.
- [ ] Consider an indexed storage adapter only if measured traffic outgrows bounded JSON files. Source: `ROADMAP.md`.
