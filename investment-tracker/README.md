<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Setup, data contract, calculation model, privacy and test procedures.
Revision: 1.5.0
Created Date: 2026-10-29
Modified Date: 2026-10-30
Changelog: 1.5.0 - Full-report batch preview, one-click import, per-ticker exceptions and privacy-safe deduplication. 1.4.0 - Enable provisional Robinhood import without impossible manual share/lot attestations. 1.3.0 - Add private per-ticker report staging, editable sale-lot/split corrections, broker-share validation, and explicit atomic import. 1.2.1 - Clarify native Robinhood CSV upload and route misselected reports to the non-mutating analyzer. 1.2.0 - Reconcile NVDA demo using owner-authorized CSV and add preview-only Robinhood CSV parser. 1.1.0 - Document isolated NVIDIA demo, original/adjusted lots, and holding-period boundaries. 1.0.0 - Document initial private tax-lot dashboard.
-->
# Investment Tracker

Private, mobile-friendly investment performance dashboard at target `https://jasr.me/github/investment-tracker/`. Source is in the public website repository **but financial data stays in ignored, HTTP-denied, per-user server storage**.

**Deployment status:** Source and review PR only; do not assume deployed. Register project ID **`investment-tracker`** (path `/github/investment-tracker/`) in central `user-management` and grant each intended account the viewer/member role **before first access**. This is not a standalone login or open-registration app. All API routes require a valid central session; write/import/void routes require member or higher and a valid CSRF token. No shared portfolio access is implemented.

## NVIDIA example from actual Robinhood activity (v1.2)

The **NVIDIA screenshot demo** tab (`?demo=1`) is now reconstructed from the owner's authorized Robinhood activity export. It is read-only, embedded in source with no account ID, and is completely separate from each authenticated member's personal investment ledger. The demo remains under central user-management authentication.

- **1.736881 NVIDIA shares** after the 2024 10:1 split and ten dividend-reinvestment purchases are reconciled from the broker activity rows.
- 2020–2021 buys and sales are included, with $77.19 in historical sale proceeds and $18.15 in aggregate realized gains on those fully exited investments.
- Two separate purchase executions on November 22, 2021 combine to 0.059679 original shares, corresponding to 0.596790 split-adjusted shares. These remain separate tax lots, not silently merged.
- Reinvested dividends contribute $0.95 of new purchase basis and add 0.004763 shares. $0.02 of early cash dividends is distinct from the reinvested dividends. **A dividend paid and reinvested is not counted twice as income.**
- At the **October 9, 2026 transaction execution price** of **$233.80** (not a live market quote), illustrative holding value is ~$406.08, open basis $177.76, unrealized gain ~$228.32, and lifetime economic gain ~$247.44. These are historical illustrative values, not verified up-to-date market valuation.
- The activity report does **not** identify which tax lots the broker actually selected for the two earlier NVDA sales. Demo allocates historical sale proceeds to oldest acquisitions provisionally for illustration. An actual tax-lot statement should be used before using realized gains in tax decisions.

All personal user imports must still be approved and reconciled. **None of the user's 1,479-row brokerage CSV is committed to public GitHub**; only the explicitly authorized, ticker-specific NVDA demonstration entries are in source.

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
