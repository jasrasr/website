<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the photo logger.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# License Plate Photo Logger TODO

## Code-informed improvements

- [ ] Add test fixtures for duplicate files, repeated plates, scanner failures, and pending-entry reprocessing. `README.md` documents these flows; the folder has no automated tests.
- [ ] Document retention and restore for uploaded photos, active entries, and deleted-entry audit data. Relevant code exists in `process_upload.php`, `delete_entry.php`, and `deleted_audit.php`.
