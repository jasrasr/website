<?php
/**
 * File Name: demo.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Authenticated, read-only NVDA demo reconstructed from permitted Robinhood activity export.
 * Revision: 1.1.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.1.0 - Reconcile fractional dividend reinvestments and both completed 2020-21 sales to full export; no identifiers or account metadata stored. 1.0.0 - Initial incomplete screenshot example.
 */
declare(strict_types=1);

/**
 * The owner explicitly authorized using NVDA trade history for this DEMO.
 * Source: brokerage activity report dated through October 2026.
 * Two historical sales are assigned to oldest lots for demonstration only;
 * Robinhood's activity CSV does NOT confirm the tax lots actually elected.
 * Corporate events and DRIP quantity/amount from the report are exact as shown.
 * Price observations are TRADE EXECUTIONS, not an official closing-price series.
 *
 * CDIV cash credits fully reinvested are represented by reinvest events ONLY:
 * storing both a cash dividend and reinvestment would double-count income.
 */
function it_demo_events(): array
{
    $events=[];
    $add=static function(string $id,string $date,string $type,float $shares=0,float $amount=0,
        string $note='',string $lotId='',int $num=0,int $den=0,float $price=0)use(&$events):void {
        $events[]=[
            'id'=>$id,'date'=>$date,'ticker'=>'NVDA','type'=>$type,
            'quantity'=>$shares,'amount'=>$amount,'price'=>$price,'lot_id'=>$lotId,
            'numerator'=>$num,'denominator'=>$den,'note'=>$note
        ];
    };
    // Original 2020/2021 trade history; oldest-lot sale mapping is PROVISIONAL.
    $add('nvda-buy-20200624','2020-06-24','buy',0.054350,20.00,'Robinhood purchase');
    $add('nvda-price-20200624','2020-06-24','price',0,0,'Execution price, not closing price','',0,0,367.98);
    $add('nvda-div-20200924','2020-09-24','dividend',0,0.01,'Cash dividend, not reinvested');
    $add('nvda-buy-20201022','2020-10-22','buy',0.025841,14.04,'Robinhood purchase');
    $add('nvda-price-20201022','2020-10-22','price',0,0,'Execution price, not closing price','',0,0,543.32);
    $add('nvda-sell-20201105','2020-11-05','sell',0.054349,30.85,'Provisional FIFO allocation, verify tax lot','nvda-buy-20200624');
    $add('nvda-buy-20210106','2021-01-06','buy',0.047989,25.00,'Robinhood purchase');
    $add('nvda-price-20210106','2021-01-06','price',0,0,'Execution price, not closing price','',0,0,520.95);
    $add('nvda-div-20210331','2021-03-31','dividend',0,0.01,'Cash dividend, not reinvested');
    // One Robinhood sale quantity 0.073831 is split into provisional FIFO lots.
    // $0.00/$16.22/$30.12 are estimated *allocated proceeds* totaling $46.34,
    // NOT broker-confirmed realized lot selections. Total sale proceeds are exact.
    $add('nvda-sell-20210524-a','2021-05-24','sell',0.000001,0.00,'Provisional FIFO; part of one $46.34 sale','nvda-buy-20200624');
    $add('nvda-sell-20210524-b','2021-05-24','sell',0.025841,16.22,'Provisional FIFO; allocated proceeds estimated','nvda-buy-20201022');
    $add('nvda-sell-20210524-c','2021-05-24','sell',0.047989,30.12,'Provisional FIFO; allocated proceeds estimated','nvda-buy-20210106');
    // 2021 4:1 happened when the visible remaining balance was zero.
    $add('nvda-split-20210720','2021-07-20','split',0,0,'NVDA 4-for-1; no open shares at the time','',4,1);
    // Two same-day fills, not a single 0.059679 lot.
    $add('nvda-buy-20211122-a','2021-11-22','buy',0.000238,0.08,'First partial fill of $20 order');
    $add('nvda-buy-20211122-b','2021-11-22','buy',0.059441,19.92,'Second partial fill of $20 order');
    $add('nvda-price-20211122','2021-11-22','price',0,0,'Execution price, not closing price','',0,0,335.10);
    $add('nvda-buy-20240328','2024-03-28','buy',0.002840,2.55,'Robinhood purchase');
    $add('nvda-price-20240328','2024-03-28','price',0,0,'Execution price, not closing price','',0,0,898.38);
    // Robinhood SPL credited 0.5627 additional shares (rounded); multiplier is 10:1.
    $add('nvda-split-20240610','2024-06-10','split',0,0,'10:1 split; 0.5627 credited shares on report','',10,1);
    $add('nvda-drip-20240701','2024-07-01','reinvest',0.000081,0.01,'DRIP from 2024-06-28 $0.01 dividend');
    $add('nvda-drip-20241004','2024-10-04','reinvest',0.000081,0.01,'DRIP from 2024-10-03 $0.01 dividend');
    $add('nvda-drip-20241230','2024-12-30','reinvest',0.000072,0.01,'DRIP from 2024-12-27 $0.01 dividend');
    $add('nvda-buy-20250212','2025-02-12','buy',1.0,129.26,'Robinhood purchase');
    $add('nvda-price-20250212','2025-02-12','price',0,0,'Execution price, not closing price','',0,0,129.26);
    $add('nvda-drip-20250403','2025-04-03','reinvest',0.000192,0.02,'DRIP from 2025-04-02 $0.02 dividend');
    $add('nvda-drip-20250707','2025-07-07','reinvest',0.000126,0.02,'DRIP from 2025-07-03 $0.02 dividend');
    $add('nvda-drip-20251003','2025-10-03','reinvest',0.000105,0.02,'DRIP from 2025-10-02 $0.02 dividend');
    $add('nvda-drip-20251229','2025-12-29','reinvest',0.000107,0.02,'DRIP from 2025-12-26 $0.02 dividend');
    $add('nvda-drip-20260402','2026-04-02','reinvest',0.000114,0.02,'DRIP from 2026-04-01 $0.02 dividend');
    $add('nvda-drip-20260609','2026-06-09','reinvest',0.001960,0.41,'DRIP from 2026-06-08 $0.41 dividend');
    $add('nvda-drip-20260915','2026-09-15','reinvest',0.001925,0.41,'DRIP from 2026-09-14 $0.41 dividend');
    $add('nvda-buy-20261009','2026-10-09','buy',0.106928,25.00,'Robinhood purchase');
    $add('nvda-price-20261009','2026-10-09','price',0,0,'LAST EXECUTION PRICE; NOT LIVE QUOTE','',0,0,233.80);
    return $events;
}
function it_demo_exclusions(): array
{
    return [
        'Price as of last observed execution: October 9, 2026 ($233.80), not a current market quotation.',
        'May 2021 sale is allocated across 3 purchase lots using a hypothetical FIFO election; the activity CSV does not contain the actual elected tax lots.',
        'Separate 2021 order fills remain separate lots. Fractional DRIP purchases are included, but only on their actual purchase dates.',
        'Corporate actions from OTHER stock symbols in the full CSV are excluded; this is an NVDA-only demonstration.',
        'Results are estimates for demonstration and must not be treated as independently verified brokerage/tax reporting.',
    ];
}
