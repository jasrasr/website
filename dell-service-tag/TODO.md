<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for dell-service-tag.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Dell Service Tag TODO

## Code-informed follow-up

- [ ] Decide whether the shared tag history in `api.php` should be limited to authorized users; current GET and POST routes have no access check.
- [ ] Add a retention or clear-history control if the shared 100-tag list should be manageable by users.
- [ ] Verify concurrent-write and failed-write behavior for the JSON history store.
