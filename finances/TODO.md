<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the budget tracker.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Finances TODO

## Code-informed improvements

- [ ] Add tests for monthly and annual projections, especially mixed expense frequencies and withholding. Calculations live in `index.php`; `AGENTS.md` confirms there is no automated test framework here.
- [ ] Document backup and restore for each user's JSON budget file without exposing private data. See `README.md` and `lib/Accounts.php`.
