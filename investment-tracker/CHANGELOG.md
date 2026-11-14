<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release notes for private tax-lot investment dashboard.
Revision: 1.5.3
Created Date: 2026-10-29
Modified Date: 2026-11-13
Changelog: 1.5.3 - Normalize positive cashless REC share credits in existing private Robinhood drafts on read without guessing tax basis. 1.5.2 - Apply Copilot cancellation review safeguards. Recognize canceled brokerage trades, replacement purchases and staged Tesla histories. 1.5.1 - Add closed-position summaries, realized returns and clearer FIFO ambiguity. 1.5.0 - Atomic import-all workflow and exception queue for actual Robinhood export. 1.4.0 - Auto-import supported broker transactions with explicitly provisional FIFO, split inference and unknown REC basis. 1.3.0 - Add private staged Robinhood account import with per-ticker editable allocations and validation. 1.2.1 - Route unmodified Robinhood CSV to safe read-only preview when incorrectly selected for normalized import. 1.2.0 - Read-only Robinhood activity CSV parser and full NVDA demo reconstruction. 1.1.0 - Split-adjusted original/remaining lot comparisons, tax-term horizon, isolated screenshot demo. 1.0.0 - Initial release.
-->
# Investment Tracker Changelog

## 1.5.3 — 2026-11-13

- Fixed compatibility with older private Robinhood CSV drafts that saved valid cashless positive `REC` share credits as unsupported.
- Reclassified eligible old `REC` rows on read across ticker previews, manual validation and full-report batch import, retaining the original report, event ID, and staged revision.
- Preserved **unknown** cost basis and original acquisition date for received shares. Did not invent purchase spending, unrealized gain or tax holding period.
- Added regression tests for the existing-draft Apple `REC` blocker, no reupload, malformed/negative/cash-bearing/price-bearing `REC` rows, and non-mutation of the private draft.
- No impact on Robinhood canceled trades, Tesla fixes, protected private ledgers or already imported tickers.

## 1.5.2 — 2026-11-13

- Pair uniquely matching `BCXL` buy and `SCXL` sell cancellations with their original fills, requiring exact ticker, date, quantity, price and refund amount.
- Exclude both sides of a canceled order from holdings, basis, proceeds and inferred FIFO; keep corrected replacement trades and audit source rows.
- Reconcile previously staged activity without reuploading or overwriting ledger data. Unmatched/ambiguous cancellations remain blocked.
- Verify Tesla's 0.165635 open shares and $46.90 remaining basis after the 2020 5:1 and 2022 3:1 splits.
- Add synthetic reversal, canceled-order and old-draft regression tests without user brokerage data.

- Followed up on four Copilot review findings: enforce BCXL/SCXL cash direction, exact raw cancellation share quantities (no approximate equality), correct canceled dividend-reinvestment purchases while restoring earned cash dividends, and skip canceled-only zero-event stocks instead of marking the batch ready.
- Added regression tests covering invalid cash signs, fractional near-matches, canceled DRIPs, separately reversed dividends, older drafts and empty import batches.

## 1.5.1 — 2026-11-13

- Identified fully sold holdings from their existing transaction history and rendered **Closed**, historical exit date, realized gain/loss, and nonannualized return on original cash purchases.
- For simple one-purchase/one-sale histories, exposed historical holding duration and short-term versus long-term classification at the sale date.
- Removed the blanket estimated FIFO warning when there was only one eligible purchase lot at sale time; retained it for genuinely multiple eligible open lots.
- Avoided displaying stale execution quotes as current values and suppressed misleading annualized XIRR on closed position cards.
- Added backend and UI regression tests using a synthetic ABNB history: $10.00 in, $11.13 out, $1.13 realized, +11.30% over 3 days.
- The calculations replay **already-imported personal records** on page load, without rewriting imported data or affecting uncommitted Robinhood draft corrections.

## 1.5.0 — 2026-10-30

- Verified the original nine-column Robinhood CSV shape using the complete uploaded 1,479-row account activity report, including quoted multiline descriptions, split credits and corporate-action codes.
- Added preview-first full-report importer: identify eligible, blocked and previously imported tickers; show detailed exception reasons; import all eligible stock histories in one confirmed, atomic account write.
- Added preview fingerprints, per-user locks and duplicate prevention so a stale or repeated import cannot silently corrupt account history.
- Preserved provisional FIFO and unknown-basis safeguards; unsupported corporate actions are excluded from import rather than guessed.

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
