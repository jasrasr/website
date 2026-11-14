<?php
/**
 * File Name: broker-basis.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Regression tests for broker-confirmed lots and tax basis kept separate from performance basis.
 * Revision: 1.0.0
 * Created Date: 2026-11-14
 * Modified Date: 2026-11-14
 * Changelog: 1.0.0 - Initial confirmed-lot, aggregate-only 1099-B, basis-adjustment and validation coverage.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/ledger.php';

function broker_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
    echo 'OK: '.$message."\n";
}
function broker_event(string $id,string $date,string $ticker,string $type,float $quantity,float $amount,array $extra=[]): array
{
    return array_merge([
        'id'=>$id,'date'=>$date,'ticker'=>$ticker,'type'=>$type,
        'quantity'=>$quantity,'amount'=>$amount,'price'=>0.0,
        'numerator'=>0,'denominator'=>0,'lot_id'=>'','note'=>'',
    ],$extra);
}

// A detailed broker statement identifies the exact acquisition lot and reports
// its sale basis. A prior nondividend distribution reduces tax basis only.
$confirmed=it_calculate([
    broker_event('older','2024-01-02','DETAIL', 'buy',2,20),
    broker_event('newer','2024-02-02','DETAIL', 'buy',2,40),
    broker_event('adjust','2024-03-01','DETAIL','basis_adjustment',0,2,[
        'lot_id'=>'older','basis_direction'=>'decrease',
    ]),
    broker_event('sale','2024-04-01','DETAIL','sell',1,15,[
        'lot_id'=>'older','allocation_estimated'=>true,'allocation_confirmed'=>true,
        'reported_cost_basis'=>9.0,
    ]),
    broker_event('quote','2026-11-01','DETAIL','price',0,0,['price'=>20]),
]);
$detail=$confirmed['holdings']['DETAIL'];
$older=array_values(array_filter($detail['lots'],static fn(array $lot):bool=>$lot['id']==='older'))[0];
broker_assert(abs($detail['basis']-50)<0.0000001 && abs($detail['tax_basis']-49)<0.0000001,
    'Performance basis remains $50 while adjusted tax basis is $49');
broker_assert(abs($detail['realized']-5)<0.0000001 && abs($detail['tax_realized']-6)<0.0000001,
    'Performance gain remains $5 while broker-reported tax gain is $6');
broker_assert(abs($older['remaining_cost']-10)<0.0000001 && abs($older['remaining_tax_cost']-9)<0.0000001,
    'The open portion retains separate performance and tax basis');
broker_assert($detail['sale_allocation_ambiguous']===false && $detail['confirmed_sale_allocation']===true,
    'Detailed broker lot confirmation clears the estimated FIFO warning');
$taxOnlyAfterQuote=it_calculate([
    broker_event('priced-lot','2024-01-02','PRICED','buy',1,10),
    broker_event('priced-quote','2026-10-01','PRICED','price',0,0,['price'=>20]),
    broker_event('later-tax-only','2026-11-01','PRICED','basis_adjustment',0,1,[
        'lot_id'=>'priced-lot','basis_direction'=>'decrease',
    ]),
])['holdings']['PRICED'];
broker_assert(abs($taxOnlyAfterQuote['value']-20)<0.0000001,
    'A later tax-only basis adjustment does not invalidate the current market quote');

// Aggregate 1099-B basis can improve tax gain without proving which purchase
// was sold. The warning must remain when multiple lots were available.
$buyOne=it_event(['date'=>'2024-05-01','ticker'=>'AGG','type'=>'buy','quantity'=>1,'amount'=>10]);
$buyTwo=it_event(['date'=>'2024-05-02','ticker'=>'AGG','type'=>'buy','quantity'=>1,'amount'=>20]);
$aggregateSale=it_event([
    'date'=>'2024-06-01','ticker'=>'AGG','type'=>'sell','quantity'=>0.5,'amount'=>8,
    'lot_id'=>$buyOne['id'],'reported_cost_basis'=>5,'allocation_confirmed'=>'0',
]);
$aggregate=it_calculate([$buyOne,$buyTwo,$aggregateSale])['holdings']['AGG'];
broker_assert($aggregateSale['allocation_estimated']===true && $aggregate['sale_allocation_ambiguous']===true,
    'Aggregate-only reported basis preserves multi-lot uncertainty');
broker_assert(abs($aggregate['tax_realized']-3)<0.0000001 && $aggregate['broker_basis_reported']===true,
    'Aggregate reported basis calculates tax gain without claiming a confirmed lot');
broker_assert($aggregate['tax_basis']===null && $aggregate['tax_unrealized']===null && $aggregate['tax_basis_uncertain']===true,
    'Aggregate-only basis does not invent the tax basis of shares still held');

// Already-imported rows stay immutable. A later correction record overlays the
// broker basis and confirmation only while replaying the ledger.
$importedSaleId=str_repeat('a',24);
$importedSale=broker_event($importedSaleId,'2024-08-01','HISTORY','sell',0.5,8,[
    'lot_id'=>'history-old','allocation_estimated'=>true,
]);
$saleCorrection=it_event([
    'date'=>'2024-09-01','ticker'=>'HISTORY','type'=>'sale_basis','amount'=>4.5,
    'sale_id'=>$importedSaleId,'allocation_confirmed'=>'1',
]);
$history=it_calculate([
    broker_event('history-old','2024-07-01','HISTORY','buy',1,10),
    broker_event('history-new','2024-07-02','HISTORY','buy',1,20),
    $importedSale,$saleCorrection,
])['holdings']['HISTORY'];
broker_assert($history['sale_allocation_ambiguous']===false && abs($history['tax_realized']-3.5)<0.0000001,
    'An immutable correction confirms an existing sale and applies its reported basis');
broker_assert(!array_key_exists('reported_cost_basis',$importedSale),
    'Replaying a correction does not mutate the original imported sale record');

try {
    it_calculate([
        broker_event('lot','2024-01-01','BAD','buy',1,10),
        broker_event('bad-sale','2024-02-01','BAD','sell',0.5,8,[
            'lot_id'=>'lot','reported_cost_basis'=>11,
        ]),
    ]);
    broker_assert(false,'Reported sale basis above available lot basis is rejected');
} catch(InvalidArgumentException $error) {
    broker_assert(str_contains($error->getMessage(),'exceeds'),'Reported sale basis above available lot basis is rejected');
}
try {
    it_calculate([
        broker_event('lot','2024-01-01','NEG','buy',1,10),
        broker_event('bad-adjust','2024-02-01','NEG','basis_adjustment',0,11,[
            'lot_id'=>'lot','basis_direction'=>'decrease',
        ]),
    ]);
    broker_assert(false,'Tax basis cannot be adjusted below zero');
} catch(InvalidArgumentException $error) {
    broker_assert(str_contains($error->getMessage(),'below zero'),'Tax basis cannot be adjusted below zero');
}
try {
    it_calculate([
        broker_event('lot','2024-01-01','CLOSED','buy',1,10),
        broker_event('exit','2024-02-01','CLOSED','sell',1,12,['lot_id'=>'lot']),
        broker_event('late-adjust','2024-03-01','CLOSED','basis_adjustment',0,1,[
            'lot_id'=>'lot','basis_direction'=>'increase',
        ]),
    ]);
    broker_assert(false,'A fully sold lot cannot receive a remaining-basis adjustment');
} catch(InvalidArgumentException $error) {
    broker_assert(str_contains($error->getMessage(),'does not match'),'A fully sold lot cannot receive a remaining-basis adjustment');
}

echo "All broker-basis regression tests passed.\n";
