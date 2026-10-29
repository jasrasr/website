<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Setup, data contract, calculation model, privacy and test procedures.
Revision: 1.1.0
Created Date: 2026-10-29
Modified Date: 2026-10-29
Changelog: 1.1.0 - Document isolated NVIDIA demo, original/adjusted lots, and holding-period boundaries. 1.0.0 - Document initial private tax-lot dashboard.
-->
# Investment Tracker

Private, mobile-friendly investment performance dashboard at target `https://jasr.me/github/investment-tracker/`. Source is in the public website repository **but financial data stays in ignored, HTTP-denied, per-user server storage**.

**Deployment status:** Source and review PR only; do not assume deployed. Register project ID **`investment-tracker`** (path `/github/investment-tracker/`) in central `user-management` and grant each intended account the viewer/member role **before first access**. This is not a standalone login or open-registration app. All API routes require a valid central session; write/import/void routes require member or higher and a valid CSRF token. No shared portfolio access is implemented.

## Isolated NVIDIA screenshot demo (v1.1)

After an account has been authorized for this project, open `?demo=1` (the **NVIDIA screenshot demo** tab). The data is **read-only** and **embedded as demonstration source only**, not inserted in the member's personal JSON or a shared user profile. `?api=1&demo=1` returns that source, and ALL demo POST attempts are rejected. Switch back to **My private portfolio** for normal user-linked records. The central project role is still required even for the demo, so it cannot bypass authentication.

Four screenshot-confirmed, currently open purchase rows are represented:

| Acquisition | Original shares | Original total cost | Post-split shares before any sales |
|---|---:|---:|---:|
| 2021-11-22 | 0.059679 | $20.00 | 0.596790 after 2024 10:1 |
| 2024-03-28 | 0.002840 | $2.55 | 0.028400 after 2024 10:1 |
| 2025-02-12 | 1.000000 | $129.26 | 1.000000 |
| 2026-10-09 | 0.106928 | $25.00 | 0.106928 |

**1.732118 shares are from only the screenshot-confirmed purchase rows, excluding subsequent dividend reinvestments.** This is NOT a reconciled current balance. Historical 2020–2021 buys/sells are not included because specific sold-lot allocations could not be proven from screenshots. Multiple 2024–2026 reinvested dividend payments were shown but their share quantities were not; the demo therefore omits them rather than silently inventing shares. Its visual market-price points are based on screenshot **execution prices** from purchase dates, and the final 2026-10-09 $233.80 observation is not a current quote or forecast. The demo can make the portfolio look profitable or unprofitable incorrectly and **must not guide actual trading**.

The personal portfolio remains empty until each account imports or creates its own data. Actual CSV import must reconcile old sales, reinvested fractional shares, fees and broker tax-lot identification before showing authoritative returns.

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
