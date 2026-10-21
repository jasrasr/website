<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the file manager.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# File Manager TODO

## Code-informed improvements

- [ ] Add automated coverage for upload validation, version naming, rate limits, and admin MFA. These flows are implemented in `api/upload.php` and `admin/`, but this folder has no test suite.
- [ ] Add a documented recovery procedure for the writable `uploads/`, `data/`, and `logs/` directories, including how versioned files are restored without overwriting newer uploads. See `README.md` and the directory layout.
