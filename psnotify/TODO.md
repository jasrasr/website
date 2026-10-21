<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the notification viewer.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented push option and test follow-up.
-->
# PSNotify TODO

## Documented option

- [ ] Evaluate real background Web Push if notifications must arrive when the viewer is closed. `README.md` says the current page is a polling viewer and Web Push is a later upgrade.

## Code-informed improvement

- [ ] Add endpoint tests for publish authentication, request limits, and feed access. `publish.php`, `fetch.php`, and `common.php` implement these flows; this folder has no tests.
