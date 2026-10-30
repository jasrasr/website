<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release notes for private tax-lot investment dashboard.
Revision: 1.2.0
Created Date: 2026-10-29
Modified Date: 2026-10-29
Changelog: 1.2.0 - Read-only Robinhood activity CSV parser and full NVDA demo reconstruction. 1.1.0 - Split-adjusted original/remaining lot comparisons, tax-term horizon, isolated screenshot demo. 1.0.0 - Initial release.
-->
# Investment Tracker Changelog

## 1.2.0 — 2026-10-29

- Added authenticated preview-only parser for the original nine-column Robinhood CSV, transaction classification, ticker-level blockers, and dividend-reinvestment matching.
- Reconciled NVIDIA demonstration share count to 1.736881, including ten dividend reinvestments, the 2024 split, and completed historic sales; kept the source file private and excluded from Git.
- Flagged unverified past sale tax-lot allocations, stock split credited-share records and unsupported corporate actions, rather than guessing or silently importing incomplete data.
- Expanded parser/ledger tests and protected brokerage uploads from persistent storage.

## 1.1.0 — 2026-10-29

- Preserve original purchase share quantity and unit basis across all stock splits; present side by side with current split-adjusted shares, current unit basis, full remaining cost basis, and quantity-weighted dollar/percent gains.
- Display exact holding duration, short/long-term status and the first possible long-term sale date; use calendar-year boundaries rather than simply 365 days.
- Add an **opt-in read-only NVIDIA screenshot demo**, isolated from personal investment data and clearly flagged as unreconciled because earlier lot allocations and dividend reinvestment shares are missing.
- Expand synthetic ledger tests to catch split attribution errors, one-year boundaries, multiple splits and incomplete demo record confusion.

## 1.0.0 — 2026-10-29

- Created authenticated, per-user, private PHP/JSON tax-lot portfolio tracker.
- Added independent purchase/reinvestment lots, split adjustments, lot-specific partial sales, realized/unrealized P&L and cash-versus-reinvested dividend accounting.
- Added dated manual market-price observations, portfolio and per-lot charts, per-ticker cash-flow XIRR, and a what-if sell simulator.
- Added normalized CSV preview/import with unique source identifiers, sale-lot references, duplicate rejection, and atomic validation.
- Added voiding with retained original records; no live brokerage integration or trade execution.
- Added responsive UI, deployment safeguards, storage deny rules, manual validation instructions and synthetic test cases.
