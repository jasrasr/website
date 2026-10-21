<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Smart 404.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected documented migration work and code review suggestions.
-->

# Smart 404 TODO

## Documented work

- [ ] Plan and implement shared-identity access for `smart-404-admin.php` while keeping public missing-page handling available; preserve live mapping, malicious-pattern, and request-log files during rollout. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestions

- [ ] Add automated checks for exact/manual mappings, malicious pattern precedence, and fuzzy redirect decisions in `smart-404.php` before changing redirect rules.
- [ ] Review the deployment procedure for `create-password-hash.php` and remove or block the temporary helper after administrator setup; the README currently instructs operators to keep it protected.
