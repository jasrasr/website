<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for attendance management.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented approval gap and test follow-up.
-->
# Life Group Attendance TODO

## Documented gap

- [ ] Consider approval notifications or a clear manual notification workflow. `README.md` says no email is sent when a leader account is approved.

## Code-informed improvement

- [ ] Add server-side regression tests for pending-account access, concurrent registration and admin writes, and soft-delete history preservation. `README.md` documents these behaviors; `tests/` currently contains a browser UI regression test only.
