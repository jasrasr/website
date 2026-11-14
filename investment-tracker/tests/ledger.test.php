<?php
/**
 * File Name: ledger.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Offline synthetic split, tax-lot and performance regression tests.
 * Revision: 1.5.4
 * Created Date: 2026-10-29
 * Modified Date: 2026-11-13
 * Changelog: 1.5.4 - Regression-test ACB full liquidation across two source sale legs, independent partial sales, and conservative manual events. 1.5.1 - Test ABNB fully closed position ROI, 3-day holding term and genuine versus generic FIFO ambiguity. 1.2.1 - Block valuations when a price observation precedes later purchases. 1.2.0 - Verify full NVDA CSV demo share reconciliation and prevent DRIP double counting. 1.1.0 - Original/adjusted quantity, proportional P&L and exact one-year tax boundary tests. 1.0.0 - Initial deterministic accounting tests.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/ledger.php';
function t(bool $pass,string $message):void { if (!$pass) throw new RuntimeException('FAIL: '.$message); echo "OK: $message\n"; }
function event(string $id,string $date,string $ticker,string $type,float $quantity=0,float $amount=0,array $rest=[]):array {
    return array_merge(['id'=>$id,'date'=>$date,'ticker'=>$ticker,'type'=>$type,'quantity'=>$quantity,'amount'=>$amount,'price'=>0,'numerator'=>0,'denominator'=>0,'lot_id'=>'','note'=>''],$rest);
}
$events=[
    event('lot1','2020-01-01','TEST','buy',1,100),
    event('split','2024-06-10','TEST','split',0,0,['numerator'=>10,'denominator'=>1]),
    event('sale','2025-01-01','TEST','sell',4,80,['lot_id'=>'lot1']),
    event('div','2025-02-01','TEST','dividend',0,2),
    event('quote','2026-10-28','TEST','price',0,0,['price'=>12]),
];
$a=it_calculate($events);$h=$a['holdings']['TEST'];$l=$h['lots'][0];
t(abs($l['shares']-6)<1e-8,'Ten-for-one split then four shares sold leaves six');
t(abs($l['original_shares']-1)<1e-8,'Original purchase shares stay intact after a split and sale');
t(abs($l['split_factor']-10)<1e-8,'Cumulative split multiplier is visible');
t(abs($l['adjusted_acquired_shares']-10)<1e-8,'Original purchase converted to ten adjusted shares');
t(abs($l['original_unit_cost']-100)<1e-8,'Original basis per share retained');
t(abs($l['adjusted_unit_cost']-10)<1e-8,'Adjusted cost per share is basis divided by split-adjusted acquired shares');
t(abs($l['remaining_unit_cost']-10)<1e-8,'Remaining per-share basis remains proportional after sale');
t(abs($l['market_value']-72)<1e-8,'Market value equals all six remaining shares times twelve, not just per-share price');
t(abs($l['unrealized']-(6*12-60))<1e-8,'Unrealized profit includes every remaining share');

t(abs($l['remaining_cost']-60)<1e-8,'Basis apportioned across specific-lot sale');
t(abs($h['realized']-40)<1e-8,'Realized profit of $40');
t(abs($h['unrealized']-12)<1e-8,'Unrealized profit of $12');
t(abs($h['lifetime_profit']-54)<1e-8,'Lifetime profit includes $2 cash dividend');
t($a['totals']['xirr_pct']!==null,'XIRR computed from dated cash flows and terminal value');
$b=it_calculate(array_merge($events,[
    event('reinvest','2025-03-01','TEST','reinvest',0.5,6)
]));
t(abs($b['holdings']['TEST']['dividends_reinvested']-6)<1e-8,'Reinvestment recognized as new basis and income');
t(abs($b['holdings']['TEST']['lifetime_profit']-60)<1e-8,'Reinvestment does not count as a second contribution');
$c=it_calculate([
    event('a','2025-01-01','A','buy',1,25),
    event('b','2025-01-02','B','buy',1,25),
    event('p','2026-01-01','A','price',0,0,['price'=>50])
]);
t($c['totals']['market_value']===null && $c['missing_prices']===['B'],'Missing ticker price prevents incorrect total');
try {
    it_calculate([event('a','2025-01-01','A','buy',1,100),event('b','2025-01-02','A','sell',2,200,['lot_id'=>'a'])]);
    throw new RuntimeException('FAIL: oversale accepted');
} catch (InvalidArgumentException $e) {
    t(true,'Over-allocation is rejected');
}
t(it_xirr([['2025-01-01',-100],['2026-01-01',110]])!==null,'Annualized rate has solution');

$boundary=it_holding_period('2025-10-29','2026-10-29');
t($boundary['tax_term']==='short-term' && $boundary['long_term_on']==='2026-10-30',
  'Exact anniversary remains short-term, first date after anniversary is long-term');
t(it_holding_period('2025-10-29','2026-10-30')['tax_term']==='long-term',
  'Day after anniversary qualifies as long-term');
t(it_holding_period('2024-02-29','2025-02-28')['tax_term']==='short-term',
  'Leap-day purchase is not erroneously treated as long-term at one-year boundary');
t(it_holding_period('2024-02-29','2025-03-01')['tax_term']==='long-term',
  'Leap-day purchase qualifies as long-term on March 1 after the clamped anniversary');
t(it_holding_period('2024-02-29','2025-03-02')['tax_term']==='long-term',
  'Leap-year boundary can eventually become long-term');
$sameDayEvents=[
    event('buy','2024-06-10','SPLIT','buy',1,100),
    event('split','2024-06-10','SPLIT','split',0,0,['numerator'=>10,'denominator'=>1]),
];
$sameDay=it_calculate($sameDayEvents);
$sameDayReversed=it_calculate(array_reverse($sameDayEvents));
t(abs($sameDay['holdings']['SPLIT']['shares']-1)<1e-9
    && $sameDay['holdings']['SPLIT']['shares']===$sameDayReversed['holdings']['SPLIT']['shares'],
  'Same-day splits are replayed before trades regardless of insertion order');
$splitQuote=it_calculate([
    event('buy','2024-06-09','QUOTE','buy',1,100),
    event('price','2024-06-09','QUOTE','price',0,0,['price'=>100]),
    event('split','2024-06-10','QUOTE','split',0,0,['numerator'=>10,'denominator'=>1]),
]);
t($splitQuote['holdings']['QUOTE']['price']===null && $splitQuote['holdings']['QUOTE']['value']===null,
  'A split invalidates a cached pre-split quote');
$today=gmdate('Y-m-d');
$yesterday=(new DateTimeImmutable($today,new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
$extreme=it_calculate([
    event('tiny',''.$yesterday,'EXTREME','buy',1,1),
    event('spike',$today,'EXTREME','price',0,0,['price'=>10]),
]);
t($extreme['holdings']['EXTREME']['lots'][0]['annualized_pct']===null,
  'Overflowing annualized rates are returned as null');
$splitHistory=it_calculate([
    event('a','2020-01-02','TEST','buy',0.05,20),
    event('sp1','2021-07-20','TEST','split',0,0,['numerator'=>4,'denominator'=>1]),
    event('sp2','2024-06-10','TEST','split',0,0,['numerator'=>10,'denominator'=>1]),
    event('p','2026-10-09','TEST','price',0,0,['price'=>200])
]);
$splitLot=$splitHistory['holdings']['TEST']['lots'][0];
t(abs($splitLot['original_shares']-0.05)<1e-9 && abs($splitLot['adjusted_acquired_shares']-2)<1e-9,
  'Two historical splits preserve original 0.05 shares and expose current two shares');
t(abs($splitLot['remaining_cost']-20)<1e-9 && abs($splitLot['remaining_unit_cost']-10)<1e-9,
  'Two splits never change total $20 purchase basis');
require_once dirname(__DIR__).'/demo.php';
$demo=it_calculate(it_demo_events());
$nvda=$demo['holdings']['NVDA'];
t(abs($nvda['shares']-1.736881)<1e-8,
  'Complete NVDA CSV demo includes ten fractional dividend reinvestments and reconciles 1.736881 shares');
t(abs($nvda['dividends_reinvested']-0.95)<1e-8,
  'Ten reinvestments total $0.95 and do not duplicate dividend cash income');
t(abs($nvda['dividends_cash']-0.02)<1e-8,
  'Only two historical cash dividends remain outside reinvested income');
t(abs($nvda['purchases']-235.85)<1e-8,
  'External cash purchases total $235.85 excluding dividend reinvestments');
t(abs($nvda['sales_proceeds']-77.19)<1e-8,
  'Provisional FIFO demo preserves exact $77.19 proceeds of old sales');
t(abs($nvda['realized']-18.15)<1e-7,
  'Fully exited historical positions have $18.15 aggregate gains regardless of lot allocation');
t(abs($nvda['value']-406.0827778)<1e-7,
  'Share count times stale $233.80 demonstration price is $406.0827778');
t(abs($nvda['lifetime_profit']-247.4427778)<1e-7,
  'Lifetime profit avoids counting both a reinvested dividend and the shares it purchased');
$older=array_values(array_filter($nvda['lots'],static fn($l)=>$l['date']==='2021-11-22'));
t(count($older)===2 && abs(array_sum(array_column($older,'original_shares'))-0.059679)<1e-8,
  'NVDA split purchase original 2021 order is two fills totaling 0.059679 shares');
t(abs(array_sum(array_column($older,'shares'))-0.59679)<1e-8,
  'NVDA split-adjusted 2021 order becomes 0.59679 shares after 10:1 split');
$latest=end($nvda['lots']);
t($latest['date']==='2026-10-09' && $latest['tax_term']==='short-term',
  'October 2026 NVDA purchase remains short-term during October 2026');


$unpriced=it_calculate([
 event('start','2025-02-12','MSFT','buy',1,100),
 event('q','2026-01-01','MSFT','price',0,0,['price'=>200]),
 event('later','2026-02-01','MSFT','buy',1,150),
]);
t($unpriced['holdings']['MSFT']['value']===null &&
  $unpriced['holdings']['MSFT']['lots'][0]['market_value']===null &&
  $unpriced['totals']['xirr_pct']===null,
  'Quote preceding a new purchase must not produce fabricated gains or portfolio XIRR');


// Already imported Robinhood history must be recalculated correctly without
// re-uploading, changing saved events or making a tax-lot FIFO claim.
$closedAbnbEvents=[
    event('abnb-buy','2020-12-19','ABNB','buy',0.064387,10.00,
        ['allocation_estimated'=>true]),
    event('abnb-sell','2020-12-22','ABNB','sell',0.064387,11.13,
        ['lot_id'=>'abnb-buy','allocation_estimated'=>true]),
    event('abnb-price','2020-12-22','ABNB','price',0,0,['price'=>172.86]),
];
$closedAbnb=it_calculate($closedAbnbEvents)['holdings']['ABNB'];
t($closedAbnb['closed']===true && $closedAbnb['simple_closed_trade']===true,
    'ABNB with one full purchase and full sale is recognized as a simple closed position');
t(abs($closedAbnb['realized']-1.13)<0.0000001 &&
    abs($closedAbnb['closed_total_return_pct']-11.3)<0.0000001,
    'ABNB closed position displays $1.13 realized profit and 11.30% lifetime return');
t($closedAbnb['closed_holding_days']===3 &&
    $closedAbnb['closed_tax_term']==='short-term' &&
    $closedAbnb['closed_on']==='2020-12-22',
    'ABNB closed position calculates historical 3-day short-term holding period');
t($closedAbnb['allocation_estimated']===true &&
    $closedAbnb['sale_allocation_ambiguous']===false,
    'Provisional import provenance retained but a single eligible buy cannot have FIFO ambiguity');
t($closedAbnb['xirr_pct']===null && $closedAbnb['value']===0.0,
    'Closed position does not report an extreme annualized rate or positive market holding');
t($closedAbnb['shares']===0.0 && $closedAbnb['basis']===0.0,
    'Closed position retains no shares or remaining cost basis');
$ambiguous=it_calculate([
    event('earlier','2020-01-02','AMB','buy',1,10,['allocation_estimated'=>true]),
    event('later','2020-01-03','AMB','buy',1,20,['allocation_estimated'=>true]),
    event('partial','2020-01-04','AMB','sell',0.5,10,
        ['lot_id'=>'earlier','allocation_estimated'=>true]),
]);
t($ambiguous['holdings']['AMB']['closed']===false &&
    $ambiguous['holdings']['AMB']['sale_allocation_ambiguous']===true,
    'Multiple eligible open purchase lots retain FIFO warning for a partial sale');
$oneLotPartial=it_calculate([
    event('one','2020-01-02','ONE','buy',2,20,['allocation_estimated'=>true]),
    event('sold','2020-01-03','ONE','sell',1,15,
        ['lot_id'=>'one','allocation_estimated'=>true]),
]);
t($oneLotPartial['holdings']['ONE']['closed']===false &&
    $oneLotPartial['holdings']['ONE']['sale_allocation_ambiguous']===false,
    'Partial sale of a single eligible purchase lot is not flagged as FIFO ambiguous');
// ACB from the brokerage screenshots: two buy lots, both shares disposed
// in ONE Robinhood sell. The importer splits a single source sale into two
// ledger sell legs. Despite two eligible lots, NO FIFO election is possible.
$acbSource='rh'.str_repeat('a',22);
$acb=it_calculate([
 event('acb-aug','2019-08-06','ACB','buy',1,6.90,['allocation_estimated'=>true]),
 event('acb-nov','2019-11-05','ACB','buy',1,3.81,['allocation_estimated'=>true]),
 event($acbSource.'11111','2019-12-20','ACB','sell',1,2.295,
   ['lot_id'=>'acb-aug','allocation_estimated'=>true,'note'=>'Robinhood row 123 (Sell)']),
 event($acbSource.'22222','2019-12-20','ACB','sell',1,2.295,
   ['lot_id'=>'acb-nov','allocation_estimated'=>true,'note'=>'Robinhood row 123 (Sell)']),
]);
$acbHolding=$acb['holdings']['ACB'];
t($acbHolding['closed']===true && $acbHolding['shares']===0.0,
  'ACB full liquidation leaves zero shares');
t(abs($acbHolding['realized']+6.12)<0.000001 &&
  abs($acbHolding['closed_total_return_pct']+57.14285714285714)<0.000001,
  'ACB realized loss remains $6.12 and nonannualized return -57.14%');
t($acbHolding['allocation_estimated']===true &&
  $acbHolding['sale_allocation_ambiguous']===false,
  'One Robinhood execution selling all two purchased lots has no FIFO ambiguity');
t($acbHolding['simple_closed_trade']===false,
  'Multiple purchases retain correct distinction from simple one-lot closed trades');
// Two independent sales on the same day are NOT one source execution. Even
// if the stock eventually closes, choice of which lot each sale came from
// can change the gain allocated to individual transactions.
$partialTwoSales=it_calculate([
 event('lotA','2019-08-06','TWO','buy',1,6.90,['allocation_estimated'=>true]),
 event('lotB','2019-11-05','TWO','buy',1,3.81,['allocation_estimated'=>true]),
 event('rh'.str_repeat('b',22).'11111','2019-12-20','TWO','sell',1,2.30,
   ['lot_id'=>'lotA','allocation_estimated'=>true,'note'=>'Robinhood row 123 (Sell)']),
 event('rh'.str_repeat('c',22).'11111','2019-12-20','TWO','sell',1,2.29,
   ['lot_id'=>'lotB','allocation_estimated'=>true,'note'=>'Robinhood row 124 (Sell)']),
]);
t($partialTwoSales['holdings']['TWO']['closed']===true &&
  $partialTwoSales['holdings']['TWO']['sale_allocation_ambiguous']===true,
  'Separate partial sales preserve tax-lot warning even after full liquidation');
$partialMultipleLots=it_calculate([
 event('lot1','2019-08-06','PART','buy',1,6.90,['allocation_estimated'=>true]),
 event('lot2','2019-11-05','PART','buy',1,3.81,['allocation_estimated'=>true]),
 event('rh'.str_repeat('d',22).'11111','2019-12-20','PART','sell',1,2.29,
   ['lot_id'=>'lot1','allocation_estimated'=>true,'note'=>'Robinhood row 123 (Sell)']),
]);
t($partialMultipleLots['holdings']['PART']['closed']===false &&
  $partialMultipleLots['holdings']['PART']['sale_allocation_ambiguous']===true,
  'Selling only part of several eligible lots remains ambiguous');
// Two sale events with arbitrary manual IDs must never be coalesced just
// because their symbol and date match.
$manualSameDay=it_calculate([
 event('x','2019-08-06','MAN','buy',1,6.90),
 event('y','2019-11-05','MAN','buy',1,3.81),
 event('saleX','2019-12-20','MAN','sell',1,2.30,['lot_id'=>'x','allocation_estimated'=>true]),
 event('saleY','2019-12-20','MAN','sell',1,2.29,['lot_id'=>'y','allocation_estimated'=>true]),
]);
t($manualSameDay['holdings']['MAN']['sale_allocation_ambiguous']===true,
  'Independent manual sale entries on one date are never auto-grouped');

$closedIncome=it_calculate([
    event('initial','2020-12-19','INCOME','buy',1,10),
    event('cash','2020-12-20','INCOME','dividend',0,0.10),
    event('exit','2020-12-22','INCOME','sell',1,11.13,['lot_id'=>'initial']),
]);
t(abs($closedIncome['holdings']['INCOME']['closed_total_return_pct']-12.3)<0.0000001,
    'Closed lifetime return includes cash dividends without annualizing');

echo "All synthetic ledger tests passed.\n";
