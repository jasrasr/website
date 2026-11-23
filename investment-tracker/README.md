<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Setup, data contract, calculation model, privacy and test procedures.
Revision: 2.0.0
Created Date: 2026-10-29
Modified Date: 2026-11-22
Changelog: 2.0.0 - Configure private Twelve Data API quotes with quota-aware caching, live valuation overlays and immutable historical transactions. 1.9.2 - Add a clickable Jasr.me project link and clarify the authenticated live deployment. 1.9.1 - Explain linked AMC/APE security actions and missing broker basis proof rather than falsely importing historical distributions. 1.9.0 - Preserve WKHS whole-share reverse split roundups, HCWC to HOST acquisition history and CHK to CHKAQ cash settlement with audited original rows. 1.8.0 - Reconcile uniquely paired simple reverse splits, cash closeouts and unknown-basis opening conversions without altering saved broker rows. 1.7.0 - Reconcile gross dividends net of withholding/ADR fees without double counting or rewriting staged reports. 1.6.0 - Separate investment-performance basis from broker-reported tax basis and add immutable 1099-B sale corrections with conservative lot confirmation. 1.5.4 - Suppress false FIFO warning on a complete brokerage sale across multiple purchase lots. 1.5.3 - Normalize positive cashless REC share credits in existing private Robinhood drafts on read without guessing tax basis. 1.5.2 - Guard exact signed cancellations, cancelled DRIPs and zero-event batches. Pair Robinhood canceled trades with original fills, correcting Tesla balances and existing drafts. 1.5.1 - Add closed-position summaries, realized returns and clear FIFO ambiguity. 1.5.0 - Full-report batch preview, one-click import, per-ticker exceptions and privacy-safe deduplication. 1.4.0 - Enable provisional Robinhood import without impossible manual share/lot attestations. 1.3.0 - Add private per-ticker report staging, editable sale-lot/split corrections, broker-share validation, and explicit atomic import. 1.2.1 - Clarify native Robinhood CSV upload and route misselected reports to the non-mutating analyzer. 1.2.0 - Reconcile NVDA demo using owner-authorized CSV and add preview-only Robinhood CSV parser. 1.1.0 - Document isolated NVIDIA demo, original/adjusted lots, and holding-period boundaries. 1.0.0 - Document initial private tax-lot dashboard.
-->
# Investment Tracker

## Automatic current stock prices (v2.0.0)

**Live project:** [Investment Tracker on Jasr.me](https://jasr.me/github/investment-tracker/). This release optionally connects to [Twelve Data](https://twelvedata.com/), an external **stock-price provider**. No quote API key is committed to GitHub or sent to the browser. The tracking application still works normally without a provider, using historical brokerage execution observations or manually entered prices.

### One-time private Hostinger setup (required)

1. Create a free Twelve Data account and obtain an API key. As of November 2026 the free Basic tier documents **8 credits per minute / 800 credits per day**, with US equity/ETF prices but not premium extended-hours feeds. Each requested ticker uses one credit; batching symbols into one HTTP request does *not* make those credits free.
2. On Hostinger, copy `investment-tracker/config.local.example.php` to `investment-tracker/config.local.php` (only remove `.example`, preserve the full filename). Edit this **ignored and HTTP-denied** file and set `'twelve_data_api_key' => 'YOUR_PRIVATE_KEY'`. Keep it outside Git and do not upload it to a screenshot or support ticket. The tracker already protects `config.local.php` with its `.htaccess` rule; verify direct HTTP access returns 403, and confirm that Hostinger executes PHP and enables outgoing HTTPS/cURL.
3. Open the private portfolio dashboard. It will request **only ticker symbols currently held**, not the user's share counts, tax lots, purchases, account numbers, or the saved report. Tap **Refresh Prices** to request eligible quotes immediately (cache and quotas still apply).

### Frequency, price quality and provider limits

- On page load, the dashboard uses server-cached quotes immediately, then performs an authenticated background refresh. While the page is visible, it checks every **five minutes**. It does **not run a background server cron job when no dashboard is open**.
- Shared server cache (`investment-tracker/storage/market-quotes.json`) is protected by the project's `storage/.htaccess`. It holds only market **symbols, quote prices, source timestamps and provider usage/attempt metadata**; no private portfolio holdings, quantity or cost basis. Existing private per-user ledger JSON is never modified during a refresh. Browser clients never see the provider API key.
- Normal per-symbol cache duration is **30 minutes**. The user can force a refresh using the button, but no more often than every **five minutes** for a given symbol. Defaults cap provider spending at **8 ticker credits per minute and 700 credits per UTC day**. At most eight outstanding symbols are fetched per API call; if the portfolio exceeds eight open tickers, successive five-minute polls pick up the remaining symbols. Other authorized viewers benefit from the same cache. The daily rate cap fails closed instead of silently incurring extra charges.
- The provider response must contain the **requested symbol, USD currency, a positive share price, and a credible source timestamp**. A missing ticker, unknown currency, API outage, unsupported or delisted symbol, premium-only feature, bad timestamp, or rate limit does **not** create a fake current value. Historical/manual values remain as a fallback. When a provider quote is earlier than the last trade or another manually recorded observation, the newer observation wins.
- Prices are **not guaranteed to be live/exchange-certified**; regular trading hours, exchange pauses, holidays and the provider's plan control availability. Quotes older than **72 hours** are visibly marked *stale*; quotes more than **seven days old** are not overlaid at all. A market may be closed over a long holiday, so staleness does not automatically mean an API error. Prices and their provider/source timestamps are shown on individual holding cards.
- The service recalculates all open-lot valuation, unrealized P/L, portfolio totals, and money-weighted XIRR **in memory from existing historical transactions**. A one-off current quote point can appear on the chart, but it is not written to the permanent ledger, private draft, sale-lot records, tax basis or historical transaction timeline. Closed positions remain valued from sales/cost basis history only. Holdings with unknown original tax/performance basis continue to suppress unsupported tax/gain calculations.
- User-triggered refresh uses the existing authenticated **member-level CSRF-protected** API action. No unauthenticated public quote proxy or externally callable server cron job is introduced. Rate limits and an atomic file lock protect concurrent sessions.

### Validation / troubleshooting

Run `php investment-tracker/tests/market-quotes.test.php` and the existing Investment Tracker CI suite. Validate live Hostinger permission boundaries, no provider key in HTML/JSON/network response, quote timestamps, manual/recorded fallback, stale warnings, accounts with only sold stocks, and missing provider access. If the banner says **Not configured**, install the private API key; this is not a failure of the stock importer. If only some ticker prices refresh, consider the per-minute/daily API limits or unsupported/delisted symbols before assuming portfolio corruption.

Official documentation: [Twelve Data authentication and errors](https://twelvedata.com/docs/introduction/libraries), [batch requests](https://support.twelvedata.com/en/articles/5203360-batch-api-requests), and [pricing/credit limits](https://twelvedata.com/pricing).


Private, mobile-friendly investment performance dashboard. **Live project:** [Open Investment Tracker on Jasr.me](https://jasr.me/github/investment-tracker/). Source code is in the public website repository, **but financial data stays in ignored, HTTP-denied, per-user server storage**.

**Access:** The project is hosted on Jasr.me and requires central authentication. Register project ID **`investment-tracker`** (path `/github/investment-tracker/`) in central `user-management` and grant each intended account the viewer/member role **before first access**. This is not a standalone login or open-registration app. All API routes require a valid central session; write/import/void routes require member or higher and a valid CSRF token. No shared portfolio access is implemented.

## Broker statements, tax basis and FIFO warnings (v1.6.0)

Investment performance and tax reporting are now separate calculations. **Performance basis** continues to reflect cash paid for purchases and therefore keeps lifetime return and XIRR comparable. **Broker-reported tax basis** may differ because of nondividend distributions, wash-sale adjustments or a basis reported for shares sold. The dashboard shows both only when they differ; a tax-basis correction does not rewrite cash invested or investment performance.

Use **Correct an existing sale from a broker statement** to attach a later 1099-B basis to an already-imported sale. This saves a new immutable correction record and leaves the Robinhood source row intact. Check **Broker statement confirms this exact purchase lot** only when the statement identifies the acquisition date or lot represented by that sale row. Detailed acquisition lines can therefore clear an estimated FIFO warning after every leg of the partial sale is matched. A statement showing only **Various** or an aggregate basis can support tax gain/loss, but it does not prove which open shares remain; the FIFO warning stays and remaining tax basis is withheld.

Use **Adjust remaining tax basis** for a documented increase or decrease that applies to a specific open lot. The tracker rejects reductions below zero and rejects a reported sale basis above the selected lot's available tax basis unless a documented increase is recorded first. It never infers an open-lot adjustment merely because a sold lot's reported basis differs.

Annual 1099s resolve only the stocks whose sale detail identifies acquisition lots. They do not necessarily resolve transfers, uncovered basis, missing acquisition dates, or aggregate **Various** entries. The private statements themselves and their values are not committed to this repository. Regression coverage uses fictional symbols, dates and amounts.

Validation: run `php investment-tracker/tests/broker-basis.test.php`, `php investment-tracker/tests/ledger.test.php`, and `node investment-tracker/tests/closed-position-ui.test.cjs`.

## NVIDIA example from actual Robinhood activity (v1.2)

The **NVIDIA screenshot demo** tab (`?demo=1`) is now reconstructed from the owner's authorized Robinhood activity export. It is read-only, embedded in source with no account ID, and is completely separate from each authenticated member's personal investment ledger. The demo remains under central user-management authentication.

- **1.736881 NVIDIA shares** after the 2024 10:1 split and ten dividend-reinvestment purchases are reconciled from the broker activity rows.
- 2020–2021 buys and sales are included, with $77.19 in historical sale proceeds and $18.15 in aggregate realized gains on those fully exited investments.
- Two separate purchase executions on November 22, 2021 combine to 0.059679 original shares, corresponding to 0.596790 split-adjusted shares. These remain separate tax lots, not silently merged.
- Reinvested dividends contribute $0.95 of new purchase basis and add 0.004763 shares. $0.02 of early cash dividends is distinct from the reinvested dividends. **A dividend paid and reinvested is not counted twice as income.**
- At the **October 9, 2026 transaction execution price** of **$233.80** (not a live market quote), illustrative holding value is ~$406.08, open basis $177.76, unrealized gain ~$228.32, and lifetime economic gain ~$247.44. These are historical illustrative values, not verified up-to-date market valuation.
- The activity report does **not** identify which tax lots the broker actually selected for the two earlier NVDA sales. Demo allocates historical sale proceeds to oldest acquisitions provisionally for illustration. An actual tax-lot statement should be used before using realized gains in tax decisions.

All personal user imports must still be approved and reconciled. **None of the user's 1,479-row brokerage CSV is committed to public GitHub**; only the explicitly authorized, ticker-specific NVDA demonstration entries are in source.

## Linked AMC/APE distribution and conversion: basis-evidence review (v1.9.1)

The remaining unsupported August 2022–August 2023 AMC/APE rows cannot be imported as unrelated purchases or sales. AMC's issuer filings document (1) one APE preferred-equity unit per eligible AMC common share in August 2022, (2) AMC's 1:10 reverse split on August 24, 2023, (3) the conversion of every 10 APE units into one AMC common share on August 25, 2023, and (4) the AMC share settlement payment to pre-conversion AMC holders on August 28, 2023 (one additional AMC share per 7.5 eligible post-split AMC shares). **The original CSV has dates, source quantities, CUSIPs and transaction codes, but does not establish how a broker allocated the cost basis of distributed APE shares or the litigation settlement stock to original acquisition lots.** These cannot be assumed to have zero basis.

The preview now recognizes this complete six-row issuer-action chain **only** when all expected source codes, dates and CUSIPs are uniquely present and the split, conversion and settlement quantities reconcile at source rounding precision. If it does, both stocks remain *blocked*, but each reports the linked history and the exact missing broker tax-basis evidence rather than only listing `MRGS`, `SOFF` and `SDIV` as unfamiliar codes. A missing/mismatched event fails closed to the original unsupported-action message. The preview includes a targeted evidence checklist for both AMC and APE.

To implement complete lot-accurate historical import in a later release, obtain the broker's 2022 APE distribution basis allocation per originating AMC lot, the corresponding APE basis carried through its 2023 conversion, and the allocation of the August 2023 AMC litigation-settlement distribution to eligible original AMC lots. Distinguish independently purchased APE shares (with their own cash purchase amounts/dates) from distributed APE units (with inherited basis and acquisition dates). Protect previous realized gains, source references and current shares. Do not guess allocations from raw stock counts or substitute an unverified zero-cost purchase.

Issuer documents: https://www.sec.gov/Archives/edgar/data/1411579/000110465922092397/tm2223780d1_ex99-1.htm and https://investor.amctheatres.com/sec-filings/all-sec-filings/content/0001104659-23-090981/tm2323643d1_8k.htm . AMC also lists relevant IRS Form 8937 disclosures at https://investor.amctheatres.com/resources/tax-documents .

**Privacy/safety:** No brokerage CSV contents or owner-specific transaction amounts are committed; only public issuer identifiers appear in the source pattern. The module neither changes the original saved report nor the permanent portfolio, nor makes an unsupported AMC/APE ticker eligible. Previously imported stocks stay skipped. All tests use fictional quantities and cost amounts.

## Documented delayed splits and symbol changes (v1.9.0)

A Robinhood stock reorganization does not always appear as two simultaneous rows under one ticker. This version introduces a small registry of independently published issuer-action terms in `corporate-action-evidence.php`. Public issuer dates, CUSIPs, ratios and rounding rules are not private brokerage information. A rule applies only when the *private source report* uniquely matches the expected old and new records, and the reconstructed shares exactly match a real ledger position. **This is not a generic permission to ignore unsupported actions.**

- **Workhorse (WKHS):** Three source-delayed credits match issuer-reported reverse split ratios of 1:20 (June 2024), 2:25 (March 2025, equivalent to 1:12.5), and 1:12 (December 2025). Each issuer document explicitly rounds fractional interests up to a full share. A verified ratio and one outstanding intact purchase lot are required before a broker-reported rounded share can be carried forward; no shares or cost basis are invented.
- **Healthy Choice Wellness → Host Digital (HCWC/HOST):** An 8/31/2026 source surrender from old CUSIP 42227T105 and credit under successor CUSIP 42227T303 is evaluated as one 1:35 reverse split / later ticker renaming. The entire *original* purchase and income history is represented under the surviving HOST symbol, with original HCWC source ticker noted in immutable imported events. All total purchase costs, original lot dates and tax basis carry forward. A renaming does not count as a taxable sale or create a fictitious closed loss.
- **Chesapeake → CHKAQ cash in lieu (CHK/CHKAQ):** A documented 1:200 reverse split may produce a fractional entitlement instead of a continuing holding. A unique later same-symbol-referenced `CIL on ... - CHK` cash receipt under `CHKAQ` is tied to the original CHK surrender only if the stated fractional share count, unit price, source cash, split date and the actual outstanding old shares reconcile. It becomes the original CHK position's **cash disposition**, never a separately purchased CHKAQ asset. Where opening conversion basis is unknown, realized taxable gain stays **UNKNOWN**, even though the original cash proceeds are recorded.

Reconstruction is **read-only for original private draft records**; source IDs, uploaded CSV hash, saved report revisions and already-imported events remain unchanged. Previously staged unsupported rows are reconsidered on read without re-upload. The importer checks **both** a historical ticker and a successor alias against existing ledger and `completed` identifiers, preventing accidental second imports. A single authenticated batch can include all necessary related transactions atomically. Source share credits rounded to four decimals are preserved on the single intact lot rather than replaced by the infinitely precise theoretical split quantity.

**Remaining blocked:** AMC/APE is a multi-security 2022-2023 spinoff, conversion and settlement requiring broker/documented **basis allocation** and acquisition-date treatment. This release does **not** auto-convert `SOFF`, `SDIV` or noncash `MRGS` into purchases, does not infer missing tax basis, and does not add historical events to previously imported symbols. Continue to review exceptions after deployment rather than forcing a zero-cost import.

Synthetic tests verify 3 delayed WKHS splits, one-lot rounded credits, two-lot uncertainty, original HCWC to HOST basis/date transfer, CHK/CHKAQ cash-in-lieu proceeds and mismatch rejection, old staged drafts, duplicate prevention across ticker aliases, and conservative AMC/APE blocking. No real user brokerage amounts or statements are included in repository files.

## Reconstructing safely paired corporate actions (v1.8.0)

The source file contains several distinct types of broker corporate-action records. A code alone does **not** guarantee a split or taxable sale; the importer uses the original instrument, date, quantity, cash amount, source descriptions and old/new security identifiers before classifying it.

- **Same-symbol reverse splits (SPR).** Two records must show exactly one surrendered prior CUSIP and one credited new CUSIP on the same day, with no cash or execution price. A **unique denominator** of 2–200 must reproduce the credited quantity from the actual outstanding share count, allowing only the four-decimal broker share-credit rounding. New uploads additionally check the surrendered share count. The ledger records one 1-for-N reverse split of the original purchase lots: basis and acquisition dates carry forward, original versus adjusted shares remain visible. Ratios inferred from the activity report are **estimated**, not broker-confirmed tax treatment.
- **Opening conversions (CONV).** An unpriced, cashless positive stock credit without original purchase history becomes a **received-share lot with UNKNOWN original tax/performance basis and original holding date**. It is never treated as a zero-cost investment. Historic proceeds and shares can be reconstructed, but taxable or overall dollar gains that depend on the missing basis stay withheld.
- **Cash settlements (LIQ + SXCH and MRGC + MRGS).** A matching same-symbol, same-day surrender and positive settlement, with the number of shares specified in the cash description, is recorded as an **estimated sale** only when the quantity matches **the entire outstanding position**. Both original source rows remain in the staged private draft. Sale proceeds are actual source cash, and historical lot basis comes from earlier purchases; estimated FIFO remains labeled as unverified where genuinely ambiguous.
- **Never auto-fix cross-symbol transfers or unbalanced events.** \`SPR\` credits without a unique surrendered predecessor or a unique ratio, cross-security spinoffs, noncash mergers, cash-in-lieu, and unpaired liquidation or merger cash remain blocked. In particular \`SOFF\`, \`SDIV\`, \`CIL\`, unsupported \`SPR\`, and noncash \`MRGS\` do not become harmless buys or sells.

**Compatibility:** All conversion rules run when a previously saved private Robinhood draft is **read**, so older drafts marked \`unsupported\` are reconsidered without uploading the CSV again. Original source rows, report hashes, immutable investment history, existing user corrections and completed imports are left untouched. Already imported tickers stay **skipped**. Re-preview the draft after deployment and import only the newly eligible stocks that pass all combined portfolio validation. The original CSV records may round the shown corporate-action shares; actual tracked shares are calculated from original purchase lots and the inferred split multiplier.

**Scope limitation:** These rules do not reconstruct complex *multiple-security* basis allocations such as AMC/APE and HCWC/HOST, nor asynchronous/delayed split credits (WKHS) or unresolved conversions involving cash-in-lieu (CHK/CHKAQ). Those need authoritative allocation/effective-date documentation and targeted review. They are intentionally not discarded or guessed.

Synthetic regression tests cover consecutive reverse splits, fractional-share source rounding, full liquidation, cash merger, missing-original-basis conversions, mismatched positions, ambiguous actions, cross-symbol spinoffs and existing staged drafts. No personal statements or exact personal transactions are committed to the public repository.

## Robinhood gross dividends, withholding and net DRIP purchases (v1.7.0)

Some Robinhood activity exports show three distinct rows for a single dividend reinvestment: a positive cash dividend (`CDIV`), a negative same-symbol withholding tax (`DTAX`) or ADR fee (`DFEE`), and a later fractional-share dividend-reinvestment purchase (`Buy` with a Dividend Reinvestment description). The net purchase is smaller than the gross dividend, **but it is not an unrelated purchase or missing cash dividend**.

The source-preserving importer now matches these transactions *only* where `gross dividend − documented withholding/ADR fees = net DRIP purchase` to the nearest recorded cent. It requires the **same ticker**, dividend no more than 14 days before reinvestment, fee dates within that interval, and an **unambiguous** combination of original dividend and fee rows. It refuses to invent fees, pair cross-ticker activity, accept fractional-dollar mismatches, or guess a choice among multiple equally plausible combinations.

The resulting ledger records the net reinvestment shares and their actual purchase cost basis, **gross reinvested dividend** and the tax/fee withheld. Withheld amounts are shown as fees for reporting, but are not subtracted a *second time* from returns: the withholding already reduced the number of shares purchased. Unrelated cash fees still reduce return normally. `dividends_cash` remains cash dividends *not reinvested*; `dividends_reinvested` remains the net amount actually used for new shares, and `dividends_reinvested_gross` reports the original gross distribution. Existing dividend matches with no withholding retain previous behavior. Stock-price dates and XIRR limitations are unchanged.

**Previously staged private Robinhood CSV reports** with old `pair_issue: No matching dividend credit` are normalized **on read**. The staged source records, source IDs, report revision, completed symbols, and existing user ledger records do not change. Re-run **Preview import of all stocks** after deploying this release; qualifying previously blocked symbols may become eligible. **Do not re-import completed symbols**, and do not assume every complex corporate action has been solved. Users do not need to upload the same historical report again.

Synthetic tests cover net-of-tax and net-of-ADR-fee DRIPs, accurate remaining shares, cash income, withholding and gain, replay of older staged drafts, ambiguous candidates and canceled purchases. Do not commit actual Robinhood exports or personal brokerage amounts to this public repository.

## No FIFO uncertainty for a complete multi-lot liquidation (v1.5.4)

The ACB Robinhood source history has two buys (2019-08-06 one share at $6.90 and 2019-11-05 one share at $3.81) and one executed **two-share sale** (2019-12-20 for $4.59). Robinhood also displays two canceled sell orders. The importer correctly recorded a **$6.12 realized loss** and **-57.14% lifetime return** but had misleadingly warned that the sale's assumed FIFO tax lots were ambiguous.

This fix considers **the whole underlying sale**, not merely each per-lot ledger row into which the importer allocates it. For recognized imported Robinhood sales, the source-derived event prefix identifies multiple lot legs belonging to a single original SELL transaction. If that single sale liquidated **all shares outstanding at that moment**, no lot-selection ambiguity exists: all available purchase lots necessarily sold. The dashboard no longer displays the generic estimated FIFO warning for this case. It preserves the original purchase dates, realized profit, closed status, and estimated allocation audit provenance.

**Important:** Separate partial sell transactions remain potentially lot-ambiguous, even if their combined sales later fully close a position. The importer does not group unrelated trades simply because they have the same ticker and date. Manually entered partial-sale records still retain conservative warnings unless unambiguously identified. The dashboard does not claim broker-confirmed lot choices.

The calculation is performed **on every authenticated ledger read**; no saved event, cost basis, existing import, stored private draft or Hostinger personal data is rewritten. Once deployed, refresh the dashboard. Regression tests use fictional imported-event IDs to verify ACB's complete multi-lot sale, plus separate partial and same-day manual transactions.

## Existing Robinhood reports with Apple REC share credits (v1.5.3)

If a Robinhood report was staged before `REC` share credits were implemented, the private draft may still contain `kind: unsupported` even though a fresh CSV parse would classify the same record as `receipt`. For example, the owner's original brokerage export has an **August 14, 2023 AAPL REC credit of 0.0281 shares**, no dollar cash flow, and no execution price; the older draft produces the **row 773 unsupported REC** error during **Preview import of all stocks**.

The importer now normalizes this specific **positive, cashless and unpriced REC record** to a share receipt *when reading the existing private draft*. This applies consistently to the ticker list, ticker review, per-ticker build and full-report batch preview. It does **not** change the saved draft, its revision, its source IDs or any already-imported ledger transactions. No reupload is required. Malformed, negative, priced, cash-bearing or other unsupported corporate-action records remain blocked; they are never silently assumed to be valid shares.

A `REC` event is recorded as shares received with **unknown acquisition cost and holding-period origin**. The ticker's taxable gains, unrealized return and XIRR that depend on this unknown basis remain unavailable rather than assuming a zero cost. A full importer preview may still reject the stock for **other** unresolved anomalies; it should no longer report a valid cashless REC row itself as unsupported.

Regression tests replay a synthetic previously staged report that intentionally marks a REC row as unsupported, check the new receipt and unknown-basis behavior, and verify the saved source/revision are unchanged. No personal brokerage records were added to the repository.

## Canceled Robinhood orders and Tesla reconciliation (v1.5.2)

Robinhood's full activity CSV includes a January 6, 2021 Tesla execution for $100, a matching +$100 cancellation (code `BCXL`) and a separate corrected buy for $99.94. The original and reversal must not create an active share lot, invested cash or realized proceeds; the corrected buy remains a purchase.

The source CSV analyzer and staged importer now pair `BCXL` canceled buys and `SCXL` canceled sells to their original trade only when **ticker, date, share quantity, per-share execution price and opposite cash amount all match uniquely**. The two raw rows remain visible as `cancelled_trade` for audit, but have no impact on shares, basis, sale allocation, or market price. An unmatched or ambiguous reversal remains a blocker.

**Previously staged private reports are supported without reupload.** Normalization runs when saved ticker data is loaded, previewed and replayed, while preserving source IDs, drafts and existing portfolio records. Tesla can become eligible in a new full-report import preview; a ticker already present in a personal ledger is not silently overwritten.

The 2020 Tesla 5:1 and 2022 3:1 split-adjusted history now gives 0.012780 shares from the November 9, 2021 $5 purchase and 0.152855 shares from the March 27, 2025 $41.90 purchase. This totals 0.165635 shares with remaining cost $46.90 and average $283.15/share, matching the Robinhood screenshot. At its *screenshot* price of $389.87/share (not live), this is $64.58 market value, $17.68 unrealized gain, or +37.69%. Closed historical transactions still have provisional tax-lot attribution.

### Canceled order safeguards (Copilot review)

The cancellation matcher now rejects economically impossible cash directions: **BCXL must have positive refunded cash** and **SCXL must have negative reversed sale proceeds**, while original buys/reinvestments must have negative spending and original sales positive proceeds. It also requires **exact equality of the raw parsed share quantities**, rather than applying a tolerance suited to split-adjusted share arithmetic. Ticker, date, execution price and opposite cash amount must also match uniquely.

If Robinhood cancels a **dividend-reinvestment Buy**, the canceled purchase and refund create no shares or cost basis. Its previously paired **positive CDIV credit is returned to an ordinary cash dividend** so earned income is not lost. If a separate dividend reversal exists, or the credit is ambiguous, the stock remains blocked rather than inventing dividend income.

A ticker whose only source activity consists of fully matched canceled transactions is classified as **skipped / nothing to import**. Such a ticker cannot make an otherwise empty batch import-ready. This behavior is shared by a new CSV upload and a previously saved authenticated draft.

Regression tests use **fictional** purchase quantities and execution amounts, with a canceled original, refund, corrected buy, 5:1 and 3:1 splits, canceled sale, old staged draft, and ambiguous/unmatched reversals. No personal Robinhood CSV is committed.

## Closed positions and realized performance (v1.5.1)

The dashboard's **By ticker** section now separates fully sold (closed) holdings from open positions. Closed holdings display a neutral **Closed** indicator, the final sale date, **$0 remaining market value**, realized profit or loss, and the lifetime return on original cash purchases; these do not show stale execution quotes as current prices and do not show an extrapolated money-weighted XIRR for short completed trades.

For a **single purchase and a single full sale**, the dashboard also shows the holding duration and short-term/long-term category measured **at the sale date**, not today. Example with the owner's ABNB screenshots: buy 0.064387 shares on 2020-12-19 for $10.00, then sell 0.064387 shares on 2020-12-22 for $11.13, resulting in **0 shares, $1.13 realized gain, +11.30% total return, three calendar days held**.

The existence of `allocation_estimated=true` on imported rows is not itself proof of an ambiguous FIFO election. A sale from the **only available purchase lot** has no competing lot allocation, so no generic FIFO warning is displayed. When multiple purchase lots were available at a provisional sale, the warning remains. The ledger retains estimated-import provenance for audit purposes regardless of how the warning is presented.

For multi-purchase or dividend-paying closed positions, **lifetime return on purchases** equals (sale proceeds + cash dividends + other income − fees − external cash purchases) divided by external cash purchases. It is not time-weighted or annualized, and should not be interpreted as a broker-certified taxable return. For positions with unknown share-receipt basis, amounts requiring that basis remain unavailable.

### Impact on existing imports and pending drafts

**No user data migration or Robinhood re-upload is required** for this release. The server calculates the new closed-position and ambiguity fields whenever it replays existing ledger events for an authenticated page load. It does **not** rewrite or delete imported transaction records. Existing open reconciliation drafts remain unchanged; they are not imported automatically. Any ticker imported later from an existing draft also receives the same display logic. After the PR is merged and the Hostinger deployment is verified, a refresh will apply the new view to all eligible historical holdings.

Validation: run `php investment-tracker/tests/ledger.test.php` and `node investment-tracker/tests/closed-position-ui.test.cjs`. Confirm ABNB no longer shows the generic FIFO warning, retains $1.13 realized profit, and does not foreground the old $172.86 execution-price observation.

## Import the full Robinhood report with one preview and one confirmation (v1.5)

The report format has been confirmed against the owner's actual full CSV, containing 1,479 transaction rows and 104 ticker symbols. The original unedited source has nine columns in this exact order:

```csv
Activity Date,Process Date,Settle Date,Instrument,Description,Trans Code,Quantity,Price,Amount
```

The parser uses a CSV reader that understands **quoted multiline descriptions**, commas inside currency amounts, negative dollars in parentheses, fractional share quantities, optional byte-order marks, blank lines, and the final non-transaction Robinhood disclaimer row.

From an authenticated **My private portfolio** page, stage the existing original report (if previously staged, reuse that existing saved draft). Then select **Preview import of all stocks**, review counts and the ticker-specific exception queue, and select **Import eligible stocks** to import all supported histories in a single atomic write. The user does not need to repeat these steps for every ticker, nor retype the broker's recorded transaction fields.

- Supported tickers import together with source-derived purchase date, shares, cash outlay, reinvested dividends, distributions, common inferred splits and **assumed FIFO** allocations for earlier sales, explicitly labeled **estimated**.
- Complex, insufficient or ambiguous corporate actions are **not silently omitted**. They are listed as per-symbol exceptions; those affected symbols stay out of this batch and can be handled later without blocking other imports.
- Symbols already present in a user's private ledger are skipped rather than overwritten or duplicated. A preview fingerprint invalidates the import authorization if the underlying draft or personal portfolio changed between preview and commit.
- The complete prepared ledger is replayed and tested before a single atomic account update. Source CSV is not committed or retained; only the private user-bound parsed draft and imported events are stored.
- For `REC` share credits with no documented cost basis, share quantity is tracked as a receipt and taxable P&L and annualized return are **withheld**. Inferred FIFO is not a certified broker lot election. You can still inspect historical trades, share quantities and charts where priced.
- The activity CSV is **not a market-price history**; the dashboard's current market value and unrealized gain still require a fresh market quote. Historical trade execution prices are not equivalent to end-of-day prices.

The **Import selected stock as estimated** and optional advanced manual review remain available for inspecting or fixing individual exceptions. No transactions are submitted to Robinhood.

## Import directly from Robinhood as an estimated history (v1.4)

The original broker account-activity CSV is the source of record for **dates, share quantities, purchase spending, sale proceeds and cash distributions**. There is no requirement for a user to retype these values or check a box pretending the activity CSV contains tax-lot elections.

1. Upload the unchanged activity CSV, select **Create private import draft**, and select any ticker.
2. Use **Import selected stock as estimated**, shown directly below the ticker selector. A single explicit confirmation imports supported activity into that user's existing private portfolio. It does **not** place orders or require a separate share-count entry, manually assigned lots, or a tax-lot certification checkbox.
3. The importer builds **provisional FIFO allocations** for past sales. It identifies mathematically unique common split ratios from Robinhood's credited share quantities and starting positions, with rounding tolerance; the recorded event's ratio is marked **inferred**, not independently verified. All generated events retain source identifiers and estimated status.
4. `REC` share credits are preserved as **received shares with unknown cost basis and unknown acquisition date**, rather than ignored or treated as free zero-basis shares. The ticker's lifetime profit, unrealized gain and XIRR are **not calculated** when an unknown-basis receipt remains outstanding or was sold. Its share quantity can still be tracked.
5. Unknown or ambiguous corporate-action codes, an unresolvable stock split, missing purchases needed to cover sales, or unsupported reinvestments prevent automatic import **for that ticker only**. They are shown as specific exceptions and are not omitted. Other stocks remain independently importable.
6. Original screenshots and broker dividend share quantities may be rounded. The source-derived balance is labeled a **calculated share count**, not a Robinhood-confirmed current position. Imported trade execution prices are historical observations, **not current quotations**.
7. The existing **Advanced: verify tax lots and correct corporate actions** panel remains available for users who have tax documentation and want to replace provisional assumptions. It is optional for estimated activity import.

A provisional import is suitable for exploring historical holdings and trends; it is **not an accurate tax-lot election record** or a substitute for corrected broker basis and tax documents. The application must not present an estimated gain as a tax figure for unknown-basis holdings or a confirmed FIFO election. The raw CSV is not committed to GitHub and parsed drafts remain under the private, web-denied storage directory.

## Review and import Robinhood activity (v1.3)

This release changes the original Robinhood uploader from **preview only** to a two-stage, explicitly authorized review-and-import workflow.

1. Sign in using central user management, with at least **member** access to the investment-tracker project. Under **Analyze original Robinhood CSV**, choose the file downloaded directly from Robinhood.
2. Tap **Create private import draft**. This creates or replaces an **ignored, HTTP-denied per-user draft** under `investment-tracker/storage/<hashed-central-user-id>.rh-stage.json`. Staging does **not** import or change the permanent portfolio. The original CSV file is not saved on the server, but parsed stock events/limited descriptions remain in private draft storage for correction and later review.
3. Select a symbol in **Correct and import brokerage activity**. Enter the exact broker-held shares as of **the report's final activity date**. Record the source of that balance, especially if transactions occurred after the exported reporting period.
4. **Sales:** each sale must be allocated to the actual purchase lot(s) by share count. The UI lists dated source purchase lots; use **Allocate another purchase lot** when a sale spans multiple lots. You must verify elections with Robinhood tax lots, cost-basis documents or trade confirmations. The app does not guess FIFO or specific identification.
5. **Splits:** Robinhood `SPL` quantities represent shares **credited**, not the actual split ratio. Enter the verified new:old ratio, for example 10:1. The importer replays historical positions and validates that the credited quantity approximately matches the ratio and pre-split holdings. Reverse splits, share conversions and other unsupported actions remain blocked until specialized rules are implemented.
6. Check the explicit source confirmation and select **Save corrections & validate**. This saves the private review only. It checks all allocated sold shares against the original lots, current share quantity and split credits; failures are listed individually without writing the permanent ledger.
7. Once the selected ticker is ready, select **Import this verified stock**. Confirm the action. The app appends only that ticker's reviewed transactions to your **own** central-user-associated portfolio using the framework's atomic JSON store. It does not place or suggest a brokerage trade. One symbol can be imported independently from other symbols with complex unresolved events.
8. Review open lots, historical prices and dividends. The importer may use purchase/sale **execution prices** as dated chart observations: these are *not closing prices and not live quotes*. Add a current dated market-price observation manually to calculate a valuation at that date. Verify actual shares and basis against the broker; review your 1099-B for tax-specific realized gains.

### Safety, supported events and blockers

The import converts stock purchases, reinvested dividend purchases, separately retained cash dividends, manually allocated sales, verified splits, stock-lending income and related fees. Matched DRIP dividend credits are not double counted. Each imported event uses a deterministic source-derived external ID, and subsequent overlapping exports cannot silently import identical events twice. Existing personal ticker histories are **not** automatically overwritten or combined with a new import: they require explicit reconciliation.

The importer **blocks**, rather than guesses, unsupported share-affecting actions such as `CONV`, `SPR`, `MRGS`, `REC`, `SXCH`, `LIQ`, `SDIV`, and cancellations. This affects several tickers in the original report. These require separate basis-carryover and replacement-share handling; falsely marking them as ordinary purchases could produce incorrect tax gains. The UI supports editing **sale allocations** and **stock split ratios**. **Suggest FIFO allocations** can populate provisional oldest-first choices (including split-adjusted quantities) to reduce manual entry; every suggestion must be checked against brokerage records, and the action deliberately clears the verification checkbox. FIFO cannot establish actual tax-lot elections. It does not yet permit arbitrary reclassification of unsupported corporate actions.

Per-user stage writes and ticker commits are serialized with a work lock; permanent ledger writes use `JsonStore::update` for atomic replacement and duplicate checks. The app records the original Robinhood row number and code in each generated event; stage corrections cannot silently rewrite source CSV amounts, dates or share quantities. On commit, stage status becomes imported. Reuploading a newer file creates a new draft and **replaces uncommitted review progress**, but does not delete existing portfolio data; matching tickers with already-imported history are rejected until reconciled.

**Important limitations:** activity CSV alone cannot prove tax-lot elections, transfers, tax-basis adjustments, or current holdings. The stage uses floating-point amounts and stock-quantity tolerances; confirm statement-level figures before using results to choose a taxable sale. Reporting covered tax lots and multi-account position reconciliation remain future work. Do not upload the CSV to the public source repository, and confirm that Hostinger denies direct HTTP access to the storage folder before allowing private brokerage data.

### Tests and rollout

Run `php investment-tracker/tests/reconciliation.test.php` and the existing ledger/parser suites, and `node investment-tracker/tests/upload-routing.test.cjs`. Verify live login/CSRF, viewer-versus-member roles, private staging and storage HTTP 403, duplicate import prevention, and mobile per-lot controls. No merge or live deployment is implied by this README.


## Choosing the correct import (v1.2.1)

The direct Robinhood export is already valid. The app has two modes: **Analyze original Robinhood CSV** parses the unmodified broker report and shows transaction classifications and unresolved tax-lot issues **without saving any holdings**; **Import normalized tracker CSV** is an advanced import of a specially prepared internal ledger with explicit lot IDs.

On the mobile page, the Robinhood upload is just before the internal import. If a user chooses the original Robinhood report under the advanced normalized upload by mistake and taps **Validate & preview**, the browser reads its header, keeps the normalized import button disabled, passes the same file to the original Robinhood analysis endpoint, and scrolls to that section. No brokerage records are written during this handoff. The file remains unchanged.

The original Robinhood CSV cannot yet populate a real user's portfolio directly: its activity report does not contain tax-lot assignments for sales or all corporate-action basis instructions. This limitation is intentional until reconciliation can produce defensible per-lot results. Never tell users the preview saved transactions.

Validation: `node investment-tracker/tests/upload-routing.test.cjs`. Check on iPhone Safari and Chrome that both picker paths go to the brokerage preview and that the advanced import button cannot commit a raw report.

## Original Robinhood CSV analysis (v1.2)

The dashboard now includes **Preview brokerage history**, which accepts the actual 9-column Robinhood account-activity format (`Activity Date, Process Date, Settle Date, Instrument, Description, Trans Code, Quantity, Price, Amount`). Uploading requires member access and CSRF validation. The server processes the file in memory and immediately returns a compact classification/reconciliation summary; no raw CSV or transactions are saved.

The parser recognizes buys, sells, dividend credits, dividend reinvestments, split share credits, lending income, account cash movements, interest, fees/taxes, and unsupported corporate actions. It links corresponding DRIP purchases to their dividend credits where same-ticker, same-dollar, shortly succeeding records exist, to avoid double-counting reinvestments. The report distinguishes split **credited share quantities** from split ratios and explicitly reports unresolved purchase-lot elections on sells. Tickers with potentially unsupported corporate actions, fee adjustments or cancellations remain flagged.

This first mapping is **preview-only**, by design. The full export includes sales, stock splits and corporate-action events which cannot safely be converted into actual cost-basis lot events without a verified broker lot-election record and corporate-action treatment. The importer does not silently choose FIFO or omit challenging rows; it never imports a portion and then pretends portfolio totals are complete. The prior normalized CSV import remains available after reconciliation.

## Original and adjusted tax lots (v1.1)

- The ledger preserves **`original_shares`**, **`original_cost`** and the original per-share *basis* forever. The original per-share basis uses total cost ÷ share quantity and can differ slightly from the broker's rounded execution price.
- Splits update **`split_factor`**, **`adjusted_acquired_shares`**, **`remaining_unit_cost`** and **`shares`** (after allocated sales), without rewriting the original quantity or initial total dollars.
- Each open lot's **market value = all remaining shares × recorded price**; **unrealized gain = market value − remaining total basis**. Percentage gain is shown alongside dollar amount so that a 1-share holding is not confused with 100 shares.
- The first potentially long-term sale date is the day after the purchase's one-year calendar anniversary; on the anniversary itself a holding is still generally **short-term**. A split retains original purchase date and a dividend reinvestment starts a new lot on its reinvestment date. Holding period, elapsed duration, current term and days until long term are exposed per lot and in the simulator.
- The term classification is informational, based on a simple taxable U.S. stock purchase. Wash-sale basis adjustments, transfers, employee options, gifts, inherited shares, and other exceptions require actual brokerage/tax evidence.

## Main features

- Each `buy` or `reinvest` creates a distinct tax lot with its own acquisition date, share quantity and cost basis.
- Each `sell` must specify the **exact originating purchase lot ID**, ensuring realized cost and remaining basis are accurate for that election. A sale spanning multiple lots needs one sell row per lot with proceeds allocated across rows.
- A `split` (e.g. `10:1` or a reverse ratio `1:10`) multiplies all shares held at that event time without changing total dollar basis. **Historical splits must be entered before trusting share counts.** Multiple splits apply multiplicatively.
- `dividend` counts as actual cash received. `reinvest` creates a new lot with purchase basis and counts as reinvested income for reporting, but *not* a second cash contribution or withdrawal.
- `price` stores a dated manual **per-share market-price observation**. Charts show only observed dates. The most recent recorded quote values open lots; quote age is displayed. There is **no automatic price feed** in this version, so the latest values and portfolio XIRR may be stale. If any open ticker has no quote, the portfolio totals and XIRR are withheld.
- Holdings show open shares, remaining basis, unrealized gain, realized gain, cash dividends, reinvestment income, net lifetime profit, and annualized money-weighted return (XIRR). Lot rows show split-adjusted shares, basis/share, cost, value, gain and annualized remaining-lot return. The sale simulator estimates net proceeds, proportional lot basis and gain; it **does not submit orders**.
- Activity can be voided but never deleted. Voiding a purchase with dependent sales is rejected, and no calculation is committed if the transaction history becomes invalid.

## Accounting definitions and known limits

- **Unrealized gain** = open shares × last observed per-share market price − remaining open-lot cost basis.
- **Realized gain** = allocated net sale proceeds − cost basis removed from the selected lot.
- **Lifetime gain** = current value of holdings + historical sale proceeds + **cash** dividends − all external buy cash outflows. Reinvestments are capitalized into lots but are not external flows. Reinvested dividends are counted as income separately.
- **XIRR** annualizes dated buys (negative), sells and cash dividends (positive), plus current open holding market value (positive terminal value). Results require sufficient cash-flow structure and do not measure investment risk or forecast returns. This is **money-weighted return**, not time-weighted return. The displayed "annualized lot return" is an approximate CAGR on **remaining basis**, not XIRR of earlier partial sales.
- The current prototype uses share precision as PHP floating point; the input stores up to eight displayed fractional-share decimal places in the browser, and values may show cent-level rounding. This is **not** a brokerage-grade decimal ledger. Reconcile statement totals after import. It does not calculate wash sales, return of capital, spin-offs, mergers, withholding, currency exchange, transfer basis, options, tax-withholding effects, or lot reassignments. "Potentially long-term" is a *holding-period indicator*, not a tax conclusion.
- For complete accuracy, import the **full history**. A Robinhood CSV format has not been inspected yet and must not be assumed to match this normalized schema. The importer deliberately **rejects raw Robinhood CSV headers** until a verified mapping is built. Existing screenshot transcriptions have **not** been seeded to live storage, because they omit some reinvestment quantities and lot elections.
- Quotes must be expressed in the currency of the total dollars for that ticker; initial release assumes **USD only**. Account transfers, FX and dividends in other currencies need future work. No market-price lookup, tax advice, automatic sale recommendations or trade execution is included.

## CSV import

`sample.csv` is **synthetic demonstration data** — do not import it as genuine holdings. Required header and exact order:

```csv
date,ticker,type,quantity,amount,price,numerator,denominator,lot_id,note,external_id
```

`date` is YYYY-MM-DD. `type` is `buy`, `sell`, `dividend`, `reinvest`, `split`, or `price`. `quantity` is shares, `amount` is **total all-in** buy/reinvestment cost or **net** sales proceeds / dividend dollars, `price` is market observation price per share, `numerator`/`denominator` are split ratios. Unused numeric columns can be empty or zero. Every `external_id` must be unique across imports (letters/digits/dash/underscore/period/colon, max 64 characters). A `sell` references the related buy/reinvest row's `external_id` in its `lot_id`; this may also reference a previously imported lot. A manual sell can pick an internal lot ID from the dashboard. CSV sale proceeds spanning several lots require preallocated rows.

**Preview** parses all rows (max 2 MB, 2,000 rows), resolves lot references, checks duplicate IDs, and replays every event against existing records without committing. **Import** repeats validation while the framework's dedicated JSON file lock is held; failure writes nothing. Imports never silently skip duplicates. No raw brokerage CSV is converted automatically. Keep the original file securely in case import needs reconciliation.

## Hostinger setup and protection

1. Deploy alongside `1-Framework/` and `user-management/` using PHP 8.1+ on HTTPS.
2. In the central user-management portal register `investment-tracker` and grant **member** to people who can write (viewer = read only). Verify role denial, expired sessions, and logout. A super admin **without a project grant** must not bypass the project gate.
3. If host-only configuration is later needed, copy **`config.local.example.php` → `config.local.php`**, removing only `.example`. The app needs no API key today. The private settings file is Git-ignored and HTTP-denied by project `.htaccess`.
4. Verify `investment-tracker/storage/.htaccess` is served by Apache/LiteSpeed and responds HTTP **403** for a known temporary test file (then delete that file). For nginx, configure an equivalent deny rule or move storage out of web root. An ignored file is **not** protected from direct HTTP access by Git ignore rules.
5. Before enabling imports, verify the webhook/sync **never deletes or overwrites** ignored `storage/*.json`, lock files or `config.local.php`. Back up per-user JSON and the central identity folder in an encrypted/private backup outside the public web root. Verify restore on staging.
6. Do not commit downloaded Robinhood CSVs, screenshots of account statements, production JSON, quotes, credentials or personal account identifiers to this **public GitHub repository**.

## Validation / acceptance

- `php -l investment-tracker/index.php` and `php -l investment-tracker/ledger.php`
- `php investment-tracker/tests/ledger.test.php` with synthetic trades, splits, reinvestments and price samples.
- From the hosted app: anonymous HTML redirects to central login and anonymous JSON fails; a viewer can read but cannot save, import or void; a member can save, and a forged/missing CSRF token fails with 403.
- A 1 share buy at $100 followed by 10:1 split becomes 10 shares at $10 basis each. A sale of 4 shares for $80 yields $40 realized gain and leaves 6 shares with $60 basis. A later price of $12 gives $72 market value and $12 unrealized gain. A cash dividend of $2 yields $54 lifetime profit (`72+80+2−100`).
- Add a 2nd ticker and show independent purchase lots; verify chart dates and a different quote date. Leave one ticker without a price: portfolio value/gain/XIRR must be unavailable, not silently treated as zero.
- Upload the synthetic CSV to preview and verify **no records changed**. Import once, refresh, check lot counts, then re-import same file: should reject duplicate IDs and leave the ledger unchanged.
- Simulate selling fractional shares of a profitable lot and a loss-making lot. Neither action may mutate storage. Void an independent price or dividend then verify recalculation. Trying to void a buy referenced by a sale must fail closed.
- Check phone widths 320/375/430 px; verify scrollable tables, no horizontal overflow in controls, chart labels, accessible form focus and copy of private data not displayed in HTML source before authentication.

## Roadmap

1. Validate actual Robinhood activity-report CSV once provided, build a **preview-first mapper** for broker event types, splits, reinvestment legs, fees and stable transaction IDs. Require explicit lot mapping when a broker CSV lacks lot identification.
2. Cross-check total open shares/cost against broker statement; provide exceptions queue for transfers, splits, corporate actions and unallocated sales.
3. Implement opt-in, host-side market-price history provider with caching, timestamp provenance and failed/stale quote warnings; never place secret API keys in JS or Git.
4. Add exact decimal arithmetic for audit-grade reconciliation, multi-account support, performance benchmarks, historical portfolio TWR and tax-lot election summaries with wash-sale warnings.
