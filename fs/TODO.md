<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the Freshservice dashboard and review tool.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured documented review limits and follow-up work.
-->
# Freshservice Tracker TODO

## Documented limitation

- [ ] Partition large ticket histories by date in the private review collector. `review/README.md` says histories at or above the 10,000-result ceiling currently fail visibly; preserve that failure behavior until complete pagination is verified.
- [ ] Design bounded review batches or resumable progress for large queues. `review/README.md` notes that sequential AI requests can exceed shared-host request timeouts; do not show partial queues as complete.

## Code-informed improvement

- [ ] Add a regression case for collector coverage at the result ceiling and interrupted batches in `tests/` before changing the review collector.
