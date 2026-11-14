<?php
/**
 * File Name: cancellations.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Synthetic brokerage cancellations must offset original trades before tax-lot reconstruction.
 * Revision: 1.1.0
 * Created Date: 2026-11-13
 * Modified Date: 2026-11-13
 * Changelog: 1.1.0 - Cover wrong-sign cancellations, exact fractional shares, canceled DRIP cash restoration, saved drafts, and zero-event no-ops. 1.0.1 - Verify original CSV analyzer counts canceled trade pairs. 1.0.0 - Test exact BCXL, corrected buy, 5:1 and 3:1 splits, staged drafts, ambiguous and unmatched cancellation, and SCXL.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';
function assertion(bool $condition,string $label):void {
    if (!$condition)throw new RuntimeException('FAILED: '.$label);
    echo 'PASS: '.$label."\n";
}
function make_csv(array $rows):string {
    $file=tempnam(sys_get_temp_dir(),'rh-cancel-test-');
    if (!$file)throw new RuntimeException('Unable to create fixture.');
    $f=fopen($file,'wb');
    fputcsv($f,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach ($rows as $r)fputcsv($f,[$r[0],$r[0],$r[0],$r[1],$r[6]??'Synthetic test trade',$r[2],$r[3],$r[4],$r[5]]);
    fclose($f);
    return $file;
}
// Reverse chronological, as exported by Robinhood. All fictional amounts/quantities.
$rows=[
 ['3/27/2025','DEMO','Buy','0.15','$300.00','($45.00)'],
 ['8/25/2022','DEMO','SPL','0.008','',''],
 ['11/9/2021','DEMO','Buy','0.004','$1,000.00','($4.00)'],
 ['3/25/2021','DEMO','Sell','0.3','$1,200.00','$360.00'],
 ['2/18/2021','DEMO','Buy','0.1','$800.00','($80.00)'],
 ['1/6/2021','DEMO','Buy','0.1','$999.00','($99.90)'], // executed replacement
 ['1/6/2021','DEMO','BCXL','0.1','$1,000.00','$100.00'], // exact cash refund
 ['1/6/2021','DEMO','Buy','0.1','$1,000.00','($100.00)'], // canceled execution
 ['8/31/2020','DEMO','SPL','0.08','',''],
 ['6/24/2020','DEMO','Buy','0.02','$1,000.00','($20.00)'],
];
$path=make_csv($rows);
try {
    $preview=it_rh_parse_csv($path);
    assertion(($preview['matched_canceled_trade_pairs']??0)===1 && ($preview['counts']['canceled_trade']??0)===2,
        'Original CSV analyzer recognizes canceled trade pair without adding an extra purchase.');
    $stage=it_rc_stage($path);
    $cancelled=array_values(array_filter($stage['rows'],fn($x)=>$x['kind']==='cancelled_trade'));
    assertion(count($cancelled)===2,'Exact BCXL refund and original purchase cancel as a pair.');
    $rebuy=array_values(array_filter($stage['rows'],fn($x)=>$x['kind']==='buy'&&$x['date']==='2021-01-06'));
    assertion(count($rebuy)===1&&abs($rebuy[0]['amount']+99.90)<0.000001,
        'Corrected same-day purchase remains with its $99.90 basis.');
    $auto=it_rc_auto_review($stage,'DEMO');
    assertion($auto['ready']===true,'Two inferred splits and canceled purchase allow automatic estimated import.');
    $result=it_calculate($auto['events']);
    $h=$result['holdings']['DEMO'];
    assertion(abs($h['shares']-0.162)<1e-9,'Position matches corrected purchases and two splits.');
    assertion(abs($h['basis']-49)<1e-7,'Remaining basis excludes canceled $100 and counts only surviving lots.');
    assertion(abs($h['basis']/$h['shares']-(49/0.162))<1e-7,
        'Average cost follows shares and purchase basis after both splits.');
    assertion(count(array_filter($auto['events'],fn($e)=>$e['type']==='split'))===2,
        'Both stock splits remain in the reconstructed event history.');
    $stageOld=$stage;
    foreach($stageOld['rows'] as &$event){
        if($event['kind']==='cancelled_trade')$event['kind']=$event['code']==='BCXL'?'unsupported':'buy';
        unset($event['cancelled_by'],$event['cancels']);
    }
    unset($event);
    assertion(it_rc_auto_review($stageOld,'DEMO')['ready']===true,
        'Previously staged raw draft repairs on read, without requiring a CSV reupload.');
    $unmatched=$stageOld;
    foreach($unmatched['rows'] as &$event){
        if($event['code']==='BCXL')$event['amount']=99.99;
    }
    unset($event);
    assertion(it_rc_auto_review($unmatched,'DEMO')['ready']===false,
        'Unmatched cancellation blocks import instead of losing a purchase or refund.');
    $ambiguous=$stageOld;
    $original=array_values(array_filter($ambiguous['rows'],fn($x)=>$x['kind']==='buy' && $x['date']==='2021-01-06' && abs($x['amount']+100)<0.001))[0];
    $original['id']='duplicate-cancel-target';$ambiguous['rows'][]=$original;
    assertion(it_rc_auto_review($ambiguous,'DEMO')['ready']===false,
        'Ambiguous cancellation with two exact original fills remains unresolved.');
    $view=it_rc_view($stageOld,'DEMO');
    assertion(count(array_filter($view['rows'],fn($x)=>$x['kind']==='cancelled_trade'))===2,
        'Existing staged draft displays matched canceled transactions as canceled.');
} finally {unlink($path);}
$path2=make_csv([
 ['1/4/2025','REV','Sell','1','$155.00','$155.00'], // actual execution
 ['1/3/2025','REV','SCXL','1','$150.00','($150.00)'],
 ['1/3/2025','REV','Sell','1','$150.00','$150.00'], // canceled
 ['1/2/2025','REV','Buy','1','$100.00','($100.00)'],
]);
try {
 $stage=it_rc_stage($path2);
 assertion(count(array_filter($stage['rows'],fn($x)=>$x['kind']==='cancelled_trade'))===2,
   'Exact sell cancellation SCXL matches a refunded sale without treating it as actual disposal.');
 $auto=it_rc_auto_review($stage,'REV');
 assertion($auto['ready'] && abs(it_calculate($auto['events'])['holdings']['REV']['realized']-55)<1e-7,
   'Actual later sell, not canceled sell, determines realized return.');
} finally {unlink($path2);}

/**
 * Invalid cash-sign cancellations MUST NOT hide a purchase or sale.
 * Matching opposite amounts without checking direction can erase genuine
 * investment basis, even when amounts seem to offset.
 */
foreach ([
 ['BADBUY','Buy','BCXL','0.10000000','$100.00','$100.00','($100.00)'],
 ['BADSELL','Sell','SCXL','0.10000000','$100.00','($100.00)','$100.00'],
] as [$ticker,$tradeCode,$cancelCode,$q,$price,$tradeAmount,$cancelAmount]) {
 $file=make_csv([
  ['1/2/2025',$ticker,$cancelCode,$q,$price,$cancelAmount],
  ['1/2/2025',$ticker,$tradeCode,$q,$price,$tradeAmount],
 ]);
 try {
  $parsed=it_rh_parse_csv($file);
  assertion(($parsed['matched_canceled_trade_pairs']??0)===0,
   $cancelCode.' with reversed cash direction never matches in original CSV analyzer.');
  $stage=it_rc_stage($file);
  assertion(count(array_filter($stage['rows'],fn($r)=>$r['kind']==='cancelled_trade'))===0,
   $cancelCode.' with reversed cash direction never hides the original trade.');
  assertion(it_rc_auto_review($stage,$ticker)['ready']===false,
   $cancelCode.' wrong-sign pair cannot be imported provisionally.');
 } finally {unlink($file);}
}

/**
 * A 0.00000004-share mismatch MUST remain a blocker even though the old
 * cancellation matcher accepted differences less than 0.00000005.
 */
$file=make_csv([
 ['1/2/2025','NEAR','BCXL','0.10000004','$100.00','$10.00'],
 ['1/2/2025','NEAR','Buy','0.10000000','$100.00','($10.00)'],
]);
try {
 $parsed=it_rh_parse_csv($file);
 assertion(($parsed['matched_canceled_trade_pairs']??0)===0,
  'Fractional share near-match is not a canceled order in CSV preview.');
 $stage=it_rc_stage($file);
 assertion(!count(array_filter($stage['rows'],fn($r)=>$r['kind']==='cancelled_trade')),
  'Fractional share mismatch retains original records in staged importer.');
 assertion(it_rc_auto_review($stage,'NEAR')['ready']===false,
  'Mismatched fractional shares block provisional import.');
} finally {unlink($file);}

/**
 * Canceling only the DRIP purchase MUST restore its cash dividend. The
 * dividend itself was earned even when the reinvestment order was canceled.
 */
$file=make_csv([
 ['5/2/2025','DIV','BCXL','0.005','$80.00','$0.40'],
 ['5/2/2025','DIV','Buy','0.005','$80.00','($0.40)','Dividend Reinvestment'],
 ['5/1/2025','DIV','CDIV','','','$0.40','Cash Dividend'],
 ['4/1/2025','DIV','Buy','1','$10.00','($10.00)'],
]);
try {
 $parsed=it_rh_parse_csv($file);
 assertion(($parsed['matched_canceled_trade_pairs']??0)===1,
  'Original CSV analyzer recognizes canceled dividend-reinvestment buy.');
 $stage=it_rc_stage($file);
 assertion(count(array_filter($stage['rows'],fn($r)=>$r['kind']==='cancelled_trade'))===2,
  'Reinvested buy and its reversal are both excluded.');
 $cash=array_values(array_filter($stage['rows'],fn($r)=>$r['code']==='CDIV'));
 assertion(count($cash)===1 && $cash[0]['kind']==='dividend' &&
  abs($cash[0]['amount']-0.4)<1e-12,
  'Earned dividend restored from paired credit after cancellation.');
 $again=it_rc_reconcile_cancellations($stage['rows']);
 assertion($again===$stage['rows'],'Cancellation normalization is idempotent for staged drafts.');
 $auto=it_rc_auto_review($stage,'DIV');
 assertion($auto['ready']===true,'Canceled DRIP no longer blocks valid dividend stock.');
 $holding=it_calculate($auto['events'])['holdings']['DIV'];
 assertion(abs($holding['shares']-1.0)<1e-12 &&
  abs($holding['dividends_cash']-0.4)<1e-10 &&
  abs($holding['dividends_reinvested'])<1e-10 &&
  abs($holding['basis']-10)<1e-10,
  'Cash dividend counted exactly once; canceled DRIP creates no shares or basis.');
 // Saved draft from the previous importer: credit was already paired and
 // the canceled purchase had not been classified at first staging.
 $old=$stage;
 foreach($old['rows'] as &$row) {
  if($row['code']==='CDIV') {
   $row['kind']='paired_credit';
   $drip=array_values(array_filter($stage['rows'],
      fn($x)=>$x['code']==='Buy' && str_contains($x['description'],'Dividend Reinvestment')))[0];
   $row['paired_to']=$drip['id'];
  } else if($row['code']==='BCXL') {
   $row['kind']='unsupported';unset($row['cancels']);
  } else if($row['code']==='Buy' && str_contains($row['description'],'Dividend Reinvestment')) {
   $row['kind']='reinvest';unset($row['cancelled_by']);
  }
 }
 unset($row);
 $restored=it_rc_auto_review($old,'DIV');
 assertion($restored['ready']===true &&
   abs(it_calculate($restored['events'])['holdings']['DIV']['dividends_cash']-0.4)<1e-10,
  'Existing private draft also restores the original earned dividend.');
 // If the dividend is separately reversed, it must NOT be counted as income:
 // absent a known reversal code the ticker is deliberately rejected.
 $reversed=$old;
 $reverse=array_values(array_filter($reversed['rows'],fn($r)=>$r['code']==='CDIV'))[0];
 $reverse['id']='negative-cdiv-reversal';$reverse['kind']='dividend';$reverse['amount']=-0.4;
 $reversed['rows'][]=$reverse;
 assertion(it_rc_auto_review($reversed,'DIV')['ready']===false,
  'Separately reversed dividend blocks import rather than counting false income.');
} finally {unlink($file);}

/**
 * A report consisting entirely of canceled orders has zero portfolio events.
 * Report as a no-op, not as an eligible stock with a misleading green button.
 */
$file=make_csv([
 ['2/1/2025','EMPTY','BCXL','0.25','$20.00','$5.00'],
 ['2/1/2025','EMPTY','Buy','0.25','$20.00','($5.00)'],
]);
try {
 $stage=it_rc_stage($file);
 $auto=it_rc_auto_review($stage,'EMPTY');
 assertion($auto['ready']===false && ($auto['noop']??false)===true &&
  count($auto['issues'])>0,
  'Canceled-only ticker is explicitly a no-op.');
 $plan=it_rc_batch_preview($stage,[]);
 assertion($plan['ready']===false && $plan['import_count']===0 &&
  count($plan['eligible'])===0 && count($plan['skipped'])===1 &&
  count($plan['blocked'])===0,
  'Empty canceled-only import batch cannot be marked import-ready.');
} finally {unlink($file);}

echo "All brokerage cancellation reconciliation tests passed.\n";
