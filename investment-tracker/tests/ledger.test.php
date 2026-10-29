<?php
/**
 * File Name: ledger.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Offline synthetic split, tax-lot and performance regression tests.
 * Revision: 1.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.0.0 - Initial deterministic accounting tests.
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
echo "All synthetic ledger tests passed.\n";
