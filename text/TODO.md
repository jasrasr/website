<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Shared Text.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected documented migration work and code review suggestions.
-->

# Shared Text TODO

## Documented work

- [ ] Decide who may load text, save changes, and view raw JSON, then migrate the password gates on both `index.php` and `index-rev2.php` to explicit shared-identity permissions. Preserve existing data and verify write authorization and CSRF. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestions

- [ ] Choose one supported entry point or make the alternate revision a non-deployed archive; both PHP pages currently contain independent copies of the save/load implementation.
- [ ] Add a restore path for the ten stored snapshots, or document the manual restore procedure in the README; history files are written and pruned, but no user-facing restore flow is documented.
