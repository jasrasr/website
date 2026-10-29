<?php
/**
 * File Name: demo.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Read-only NVDA demonstration using clearly identified screenshot-derived entries.
 * Revision: 1.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.0.0 - Isolate incomplete NVDA example data from all private user ledgers.
 */
declare(strict_types=1);

/**
 * These known open purchases are independently visible in the supplied Robinhood screenshots.
 * Other 2020–21 trades and reinvested dividends are deliberately NOT allocated or fabricated.
 * Price observations are original trade execution prices, NOT official closing quotations.
 * This subset is for interface demonstration only, not a reconciliation of the user's holdings.
 */
function it_demo_events(): array
{
    $rows=[
        ['id'=>'demo-split-2021','date'=>'2021-07-20','type'=>'split','numerator'=>4,'denominator'=>1,'note'=>'NVDA 4:1 historical split; no demo lots yet'],
        ['id'=>'demo-buy-20211122','date'=>'2021-11-22','type'=>'buy','quantity'=>0.059679,'amount'=>20.00,'note'=>'Screenshot purchase; original fractional shares'],
        ['id'=>'demo-price-20211122','date'=>'2021-11-22','type'=>'price','price'=>335.10,'note'=>'Transaction execution price; not closing quotation'],
        ['id'=>'demo-buy-20240328','date'=>'2024-03-28','type'=>'buy','quantity'=>0.002840,'amount'=>2.55,'note'=>'Screenshot purchase; pre-2024-split shares'],
        ['id'=>'demo-price-20240328','date'=>'2024-03-28','type'=>'price','price'=>898.38,'note'=>'Transaction execution price; not closing quotation'],
        ['id'=>'demo-split-2024','date'=>'2024-06-10','type'=>'split','numerator'=>10,'denominator'=>1,'note'=>'NVDA 10:1 post-split trading date'],
        ['id'=>'demo-buy-20250212','date'=>'2025-02-12','type'=>'buy','quantity'=>1,'amount'=>129.26,'note'=>'Screenshot purchase, after both splits'],
        ['id'=>'demo-price-20250212','date'=>'2025-02-12','type'=>'price','price'=>129.26,'note'=>'Transaction execution price; not closing quotation'],
        ['id'=>'demo-buy-20261009','date'=>'2026-10-09','type'=>'buy','quantity'=>0.106928,'amount'=>25.00,'note'=>'Screenshot purchase, after both splits'],
        ['id'=>'demo-price-20261009','date'=>'2026-10-09','type'=>'price','price'=>233.80,'note'=>'Last example price is a 2026-10-09 execution price, not live'],
    ];
    return array_map(static fn(array $r):array => array_merge([
        'ticker'=>'NVDA','quantity'=>0.0,'amount'=>0.0,'price'=>0.0,
        'numerator'=>0,'denominator'=>0,'lot_id'=>'','note'=>'',
    ],$r),$rows);
}
function it_demo_exclusions(): array
{
    return [
        '2020–2021 screenshot buys and sells are not attributed to particular tax lots; their realized gains cannot be verified from the images.',
        'Screenshot dividends marked Reinvested are omitted because their acquired fractional-share quantities are not visible; value and share counts are understated.',
        'The final illustrative market value is based on the $233.80 purchase execution price on 2026-10-09, not a current quote.',
        'Demo proceeds, performance and tax classifications must not be used as actual account totals or sale instructions.',
    ];
}
