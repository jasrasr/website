<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for Warranty Tracker.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Collected migration and validation work.
-->

# Warranty Tracker TODO

## Documented work

- [ ] Map existing local users to shared identities while preserving household invitations and private/shared warranty visibility. Verify account access and record ownership before rollout. Source: `user-management/MIGRATION-TRACKER.md`.

## Code review suggestion

- [ ] Automate the README's manual date-boundary, visibility, and CRUD checks so changes to `WarrantyStore.php`, `Auth.php`, or the API can be verified without a live household dataset.
