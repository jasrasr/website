<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release notes for private tax-lot investment dashboard.
Revision: 1.9.1
Created Date: 2026-10-29
Modified Date: 2026-11-16
Changelog: 1.9.1 - Explain linked AMC/APE security actions and missing broker basis proof rather than falsely importing historical distributions. 1.9.0 - Preserve WKHS whole-share reverse split roundups, HCWC to HOST acquisition history and CHK to CHKAQ cash settlement with audited original rows. 1.8.0 - Reconcile uniquely paired simple reverse splits, cash closeouts and unknown-basis opening conversions without altering saved broker rows. 1.7.0 - Reconcile gross dividends net of withholding/ADR fees without double counting or rewriting staged reports. 1.6.0 - Add immutable broker-statement sale corrections, confirmed lot status and separate tax-basis reporting. 1.5.4 - Hide false FIFO warning for complete source sale across multiple original purchase lots; preserve real partial-sale warnings. 1.5.3 - Normalize positive cashless REC share credits in existing private Robinhood drafts on read without guessing tax basis. 1.5.2 - Apply Copilot cancellation review safeguards. Recognize canceled brokerage trades, replacement purchases and staged Tesla histories. 1.5.1 - Add closed-position summaries, realized returns and clearer FIFO ambiguity. 1.5.0 - Atomic import-all workflow and exception queue for actual Robinhood export. 1.4.0 - Auto-import supported broker transactions with explicitly provisional FIFO, split inference and unknown REC basis. 1.3.0 - Add private staged Robinhood account import with per-ticker editable allocations and validation. 1.2.1 - Route unmodified Robinhood CSV to safe read-only preview when incorrectly selected for normalized import. 1.2.0 - Read-only Robinhood activity CSV parser and full NVDA demo reconstruction. 1.1.0 - Split-adjusted original/remaining lot comparisons, tax-term horizon, isolated screenshot demo. 1.0.0 - Initial release.
-->
# Investment Tracker Changelog

## 1.9.1 — 2026-11-16

- Diagnosed the full linked 2022–2023 AMC/APE source sequence: preferred-equity distribution (`SOFF`), AMC reverse split (`SPR`), preferred-equity conversion (`MRGS`) and litigation settlement stock distribution (`SDIV`).
- Added strict unique public CUSIP/date/code matching with source-rounded quantity validation. A mismatch fails closed; the importer does not assume free shares, invent tax basis, or treat the conversion as a sale.
- Continued to **block both AMC and APE** until supported brokerage tax-basis allocations and original lot acquisition dates are available; added a specific broker-evidence checklist to the import preview for each linked ticker.
- Updated dashboard revision/cache and added synthetic CI regression tests covering full matches, mismatched/missing sources, unrelated tickers, immutable draft, and completed-ticker protection.
- No change to the permanent ledger, existing completed holdings, or private Hostinger staged report.

## 1.9.0 — 2026-11-16

- Added an auditable, publicly sourced corporate-action registry for unusual delayed reverse splits and cross-symbol records. Every rule requires specific issuer dates, predecessor/successor CUSIPs, share units, a unique source pair, verified ratio and actual outstanding shares.
- Reconstructed three issuer-documented WKHS reverse splits with whole-share rounding (1:20, 2:25, 1:12) and preserved the original cash investment cost and acquisition date. Multi-lot unverified rounded adjustments remain blocked.
- Combined original HCWC purchase lots and the later HOST ticker under one continuing security after the 1:35 reverse split; no fictitious closed position or purchased HOST shares. The source retains original tickers for audit.
- Linked qualifying CHK reverse-split fractional share entitlements to a later CHKAQ cash-in-lieu receipt and recorded the cash as proceeds on the original shares, retaining unknown original-conversion basis when applicable.
- Maintained saved draft hashes, source transaction IDs, completed ticker flags and all existing private records. Preview checks both current and former tickers to prevent duplicate imports.
- Added source-only synthetic regression coverage for delayed splits, single-lot rounding, mismatched prices/amounts, same-action duplicate ambiguity, source alias replays, and intentionally unsupported AMC/APE cost-basis transfers.
- Did not import, bypass or guess cost basis allocations for `SOFF`, `SDIV`, noncash `MRGS`, and other unsafe corporate actions.

## 1.8.0 — 2026-11-16

- Added conservative reconstruction of **same-day, same-symbol, unique-CUSIP reverse stock splits** when the original shares surrendered and new shares credited are paired and a unique split denominator matches the existing portfolio. Preserve original tax lots, acquisition dates, split-adjusted quantities and total basis.
- Recognized source-only, cashless positive \`CONV\` share receipts as **unknown original cost basis/holding date**, never as zero-cost purchases. Suppress unsupported taxable/lifetime P&L instead of inventing returns.
- Recognized fully reconciled \`LIQ + SXCH\` cash liquidations and \`MRGC + MRGS\` cash-only mergers as source-priced exits, retaining exact settled proceeds and conservatively estimated FIFO lots.
- Re-evaluate existing private staged reports on read without modifying source events, completed tickers or broker-corrected sale basis; block non-unique, mismatched or cross-ticker corporate-action chains.
- Retained explicit blocking for \`SOFF\`, \`SDIV\`, \`CIL\`, unpaired \`SPR\`, noncash merger conversions, and all source actions lacking safe reconstruction evidence.
- Added synthetic CI tests for reversed stock splits, rounded source credits, sequential corporate actions, unknown-basis opening receipt, cash exits, old drafts and ambiguous unsupported cases.

## 1.7.0 — 2026-11-16

- Added conservative, general same-symbol `CDIV` + `DTAX`/`DFEE` + fractional `Buy` (DRIP) matching: the documented dividend less withheld tax/ADR fees must exactly equal the net purchase at source-cent precision.
- Repaired the read-time interpretation of **previously staged drafts** containing unmatched reinvestments, without reuploading the report, changing saved revisions or overwriting already imported tickers.
- Preserved gross dividend, net reinvestment cost, withheld fees and new purchase-lot share quantity as distinct auditable facts. Prevented a second deduction of fees already subtracted before a DRIP share purchase.
- Matched only unique candidate distributions and withholding subsets; missing or ambiguous source evidence still blocks the affected symbol.
- Added a ticker-card explanation of gross versus net reinvestment and withholding.
- Added synthetic CI regression tests for net-of-tax and net-of-ADR-fee purchases, old drafts, missing/ambiguous matches, canceled DRIPs, no duplicate income and current share balances.
- Corporate-action codes `SPR`, `SOFF`, `CONV`, `MRGS`, `MRGC`, `LIQ`, `SXCH` and `CIL` remain separately blocked until their terms and basis can be reconciled. Incremental importing into existing ticker histories remains future work; no saved portfolio or Hostinger data was modified.

## 1.6.0 — 2026-11-14

- Separated cash-investment performance basis from broker-reported tax basis so tax adjustments do not distort lifetime return or XIRR.
- Added immutable corrections that attach a later broker-reported basis and optional exact-lot confirmation to an existing sale without rewriting its imported source record.
- Added per-lot remaining-basis increases and decreases for documented nondividend distributions, wash-sale adjustments and similar broker corrections, with below-zero and over-allocation guards.
- Cleared estimated FIFO warnings only when a broker statement confirms the specific sale lot; retained the warning for aggregate or **Various** 1099-B basis.
- Displayed performance and tax basis, unrealized gain and realized gain separately when they differ.
- Added synthetic backend and UI regressions for detailed confirmed lots, aggregate-only basis, immutable corrections and invalid basis values. No personal statements or Hostinger data are included.

## 1.5.4 — 2026-11-13

- Fixed a false **Estimated FIFO** alert for already-imported ACB: two one-share purchases, one two-share liquidation, $10.71 invested, $4.59 proceeds, $6.12 realized loss and -57.14% return.
- Grouped the per-lot ledger legs of a **single original Robinhood sale execution** using its stable source-event identifier, and suppressed allocation warnings only when that execution sold all shares outstanding at the time.
- Retained the estimated-source audit marker and ambiguity warnings for genuinely partial or separate sell orders, including independent same-day orders and closed histories with earlier partial sales.
- Recomputed holdings at page load without reimporting, modifying a staged report, changing stored personal data or revising realized P&L.
- Added PHP ledger and UI regressions for ACB, ambiguous partial sales, two independent sales on the same day, and a single-purchase partial sale.

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
