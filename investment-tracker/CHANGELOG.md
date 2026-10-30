<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release notes for private tax-lot investment dashboard.
Revision: 1.4.0
Created Date: 2026-10-29
Modified Date: 2026-10-30
Changelog: 1.4.0 - Auto-import supported broker transactions with explicitly provisional FIFO, split inference and unknown REC basis. 1.3.0 - Add private staged Robinhood account import with per-ticker editable allocations and validation. 1.2.1 - Route unmodified Robinhood CSV to safe read-only preview when incorrectly selected for normalized import. 1.2.0 - Read-only Robinhood activity CSV parser and full NVDA demo reconstruction. 1.1.0 - Split-adjusted original/remaining lot comparisons, tax-term horizon, isolated screenshot demo. 1.0.0 - Initial release.
-->
# Investment Tracker Changelog

## 1.4.0 — 2026-10-30

- Added user-authorized, one-click provisional import from an unchanged Robinhood activity CSV, without requiring invented broker lot confirmations or a manually entered portfolio balance.
- Automatically derive FIFO **estimates** for past sales and infer unique common splits from credited shares with rounding tolerance; preserve whether an election is unverified.
- Recognize Robinhood `REC` stock credits as shares with **unknown tax basis and acquisition period**. Suppress unrealized gains, lifetime profit and XIRR where basis is incomplete.
- Continue to reject unsupported/ambiguous corporate actions rather than silently treating them as purchases or ignoring them.
- Added prominent estimated-basis warnings and blocked misleading partial-sale simulations on received shares with unknown basis.
- Moved advanced manual verification behind a collapsible section; raw CSV remains private and user-bound.

## 1.3.0 — 2026-10-30

- Added private, persisted per-user Robinhood activity review drafts and a ticker selector with original purchase records, editable sale lot allocations and verified split-ratio inputs.
- Added optional unverified FIFO allocation suggestions for eligible historical purchases, with mandatory broker verification and no automatic save.
- Required source-record attestation and an exact broker-reported share balance before an explicit per-ticker import; block unsupported corporate actions and unresolved share-affecting records rather than guessing.
- Added atomic portfolio append with deterministic source IDs and existing-ticker duplicate prevention; marked imported ticker drafts without modifying the original uploaded brokerage report.
- Retained separate dividend reinvestment, cash dividend, stock-lending income and fee categories, and recorded dated transaction execution prices for historical graphs without claiming they are live quotes.
- Added broker-source reconstruction and accounting tests for splits, sold lots, dividend pairing, fee/lending cash flows, share reconciliation and blocked corporate actions.

## 1.2.1 — 2026-10-30

- Clarified Robinhood original CSV analyzer versus advanced internal normalized CSV importer, with an in-page link to the correct upload section.
- Automatically recognize the original brokerage CSV by its header when a user selects it under normalized import; redirect the file to brokerage analysis without enabling a save or changing the source file.
- Added browser-side regression tests confirming BOM/CRLF handling and disabled normalized import for an original Robinhood file.

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
