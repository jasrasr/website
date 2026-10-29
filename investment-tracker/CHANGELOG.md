<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release notes for private tax-lot investment dashboard.
Revision: 1.0.0
Created Date: 2026-10-29
Modified Date: 2026-10-29
Changelog: 1.0.0 - Initial release.
-->
# Investment Tracker Changelog

## 1.0.0 — 2026-10-29

- Created authenticated, per-user, private PHP/JSON tax-lot portfolio tracker.
- Added independent purchase/reinvestment lots, split adjustments, lot-specific partial sales, realized/unrealized P&L and cash-versus-reinvested dividend accounting.
- Added dated manual market-price observations, portfolio and per-lot charts, per-ticker cash-flow XIRR, and a what-if sell simulator.
- Added normalized CSV preview/import with unique source identifiers, sale-lot references, duplicate rejection, and atomic validation.
- Added voiding with retained original records; no live brokerage integration or trade execution.
- Added responsive UI, deployment safeguards, storage deny rules, manual validation instructions and synthetic test cases.
