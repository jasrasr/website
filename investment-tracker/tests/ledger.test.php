<?php
/**
 * File Name: ledger.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Offline synthetic split, tax-lot and performance regression tests.
 * Revision: 1.1.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.1.0 - Original/adjusted quantity, proportional P&L and exact one-year tax boundary tests. 1.0.0 - Initial deterministic accounting tests.
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
t(it_holding_period('2024-02-29','2025-03-02')['tax_term']==='long-term',
  'Leap-year boundary can eventually become long-term');
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
t(abs($demo['holdings']['NVDA']['shares']-1.732118)<1e-8,
  'Screenshot NVDA demo only tracks confirmed 1.732118 shares, without fabricated reinvestments');
$lots=$demo['holdings']['NVDA']['lots'];
t(abs($lots[0]['original_shares']-0.059679)<1e-9 && abs($lots[0]['shares']-0.59679)<1e-9,
  'NVDA 2021 purchase displays original and tenfold adjusted share counts');
t($lots[count($lots)-1]['tax_term']==='short-term',
  'October 2026 Nvidia purchase marked short-term during October 2026');
echo "All synthetic ledger tests passed.\n";
