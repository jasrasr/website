<?php
/**
 * File Name: drip-withholding.test.php
 * Revision: 1.0.0
 * Description: Synthetic Robinhood gross-dividend / net-of-tax-or-ADR-fee DRIP import tests.
 * Author: Jason Lamb (with help from ChatGPT)
 * Created: 2026-11-16
 * Modified: 2026-11-16
 * Changelog: 1.0.0 - Test net DRIP, old drafts, ambiguous source groups and cancel/reversal accounting.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';

function must(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('FAILED: '.$message);
    echo 'PASS: '.$message."\n";
}
function csv_fixture(array $rows):string {
    $file=tempnam(sys_get_temp_dir(),'rh-drip-');
    if(!$file)throw new RuntimeException('Cannot make fixture.');
    $fh=fopen($file,'wb');
    if(!$fh)throw new RuntimeException('Cannot open fixture.');
    fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach($rows as $r) {
        $date=$r[0];$ticker=$r[1];$description=$r[2];$code=$r[3];
        fputcsv($fh,[$date,$date,$date,$ticker,$description,$code,$r[4],$r[5],$r[6]]);
    }
    fclose($fh);return $file;
}
function row(string $date,string $ticker,string $description,string $code,string $qty='',string $price='',string $amount=''):array {
    return [$date,$ticker,$description,$code,$qty,$price,$amount];
}
function stage_for(array $rows):array {
    $file=csv_fixture($rows);
    try {return it_rc_stage($file);} finally {unlink($file);}
}
$tax=stage_for([
    row('5/10/2026','TAX','Reinvestment','Buy','0.013','$10.00','($0.13)'),
    row('5/9/2026','TAX','Foreign dividend withholding','DTAX','','','($0.02)'),
    row('5/9/2026','TAX','Dividend','CDIV','','','$0.15'),
    row('1/2/2026','TAX','Original purchase','Buy','1','$10.00','($10.00)'),
]);
// In the source, a DRIP is identified by the full Robinhood description.
foreach($tax['rows'] as &$e) {
    if($e['code']==='Buy' && $e['date']==='2026-05-10') {
        $e['kind']='reinvest';
        unset($e['paired_to'],$e['gross_dividend'],$e['pair_issue']);
    }
}
unset($e);
$taxRows=it_rc_reconcile_cancellations($tax['rows']);
$taxDrip=array_values(array_filter($taxRows,fn($x)=>$x['kind']==='reinvest'))[0];
$taxCredit=array_values(array_filter($taxRows,fn($x)=>$x['kind']==='paired_credit'))[0];
$taxFee=array_values(array_filter($taxRows,fn($x)=>$x['kind']==='fee'))[0];
must(empty($taxDrip['pair_issue']) && abs($taxDrip['gross_dividend']-0.15)<1e-10,
    'Gross dividend matched to smaller after-tax DRIP purchase.');
must($taxDrip['paired_to']===$taxCredit['id'] && $taxFee['withheld_for']===$taxDrip['id'],
    'Dividend, tax and new shares preserve linked source identities.');
must(it_rc_reconcile_cancellations($taxRows)===$taxRows,
    'Read-time net dividend matching is idempotent.');
$tax['rows']=$taxRows;
$rev=it_rc_revision($tax);
$auto=it_rc_auto_review($tax,'TAX');
must($auto['ready']===true,'After-tax DRIP is import-ready with no manual dividend edits.');
$feeRecords=array_values(array_filter($auto['events'],fn($x)=>$x['type']==='fee'));
$dripRecords=array_values(array_filter($auto['events'],fn($x)=>$x['type']==='reinvest'));
must(count($feeRecords)===1 && !empty($feeRecords[0]['withheld_from_drip']) &&
    count($dripRecords)===1 && abs($dripRecords[0]['gross_dividend']-0.15)<1e-10,
    'Ledger retains gross DRIP and its matched withholding without inventing cash purchases.');
$hold=it_calculate($auto['events'])['holdings']['TAX'];
must(abs($hold['shares']-1.013)<1e-9 &&
     abs($hold['dividends_reinvested']-0.13)<1e-9 &&
     abs($hold['dividends_reinvested_gross']-0.15)<1e-9 &&
     abs($hold['dividend_withholding']-0.02)<1e-9 &&
     abs($hold['fees']-0.02)<1e-9,
     'Gross 15c dividend minus 2c tax yields 13c reinvested and exact share count.');
must(abs($hold['lifetime_profit']-0.13)<1e-8,
     'Withholding is not charged twice against unrealized/lifetime investment performance.');
must($rev===it_rc_revision($tax),'Preview does not modify stored staged report revisions.');
$batch=it_rc_batch_preview($tax,[]);
must($batch['ready']===true && count($batch['eligible'])===1 &&
  count($batch['blocked'])===0,'Full-report preview makes withholding DRIP eligible.');

// Old user drafts were staged while the gross dividend and net DRIP were
// unpaired, storing a stale "No matching dividend credit" source issue.
$legacy=$tax;
foreach($legacy['rows'] as &$r) {
    if($r['kind']==='reinvest'){
        unset($r['paired_to'],$r['gross_dividend'],$r['withheld_amount']);
        $r['pair_issue']='No matching dividend credit';
    }
    if($r['kind']==='paired_credit'){ $r['kind']='dividend';unset($r['paired_to']); }
    if(isset($r['withheld_for']))unset($r['withheld_for']);
}
unset($r);
$legacyRevision=it_rc_revision($legacy);
must(it_rc_auto_review($legacy,'TAX')['ready']===true &&
     it_rc_batch_preview($legacy,[])['ready']===true,
     'Original private pending draft is fixed on read, without CSV reupload.');
must(it_rc_revision($legacy)===$legacyRevision,
     'Private draft source data and its saved revision are unchanged.');

// The synthetic "ADR fee" pattern: gross dividend less fee equals net DRIP.
$adr=stage_for([
 row('2/20/2026','ADR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
 row('2/19/2026','ADR','ADR pass-through fee','DFEE','','','($0.03)'),
 row('2/19/2026','ADR','Cash dividend','CDIV','','','$0.50'),
 row('1/2/2026','ADR','Original purchase','Buy','2','$10.00','($20.00)'),
]);
$a=it_rc_auto_review($adr,'ADR');
must($a['ready']===true,'ADR fee net dividend also imports without guessing a DRIP.');
$h=it_calculate($a['events'])['holdings']['ADR'];
must(abs($h['shares']-2.047)<1e-9 &&
    abs($h['dividends_reinvested_gross']-0.50)<1e-9 &&
    abs($h['dividend_withholding']-0.03)<1e-9 &&
    abs($h['dividends_reinvested']-0.47)<1e-9,
    'ADR fee and net share purchase reconcile exactly.');

// Do not claim success if withholding proof is missing or any combination
// of dividend / fee records can produce multiple plausible pairings.
foreach([
 ['missing',[
    row('2/20/2026','ERR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
    row('2/19/2026','ERR','Dividend','CDIV','','','$0.50'),
 ]],
 ['wrong_symbol',[
    row('2/20/2026','ERR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
    row('2/19/2026','OTHER','Fee','DFEE','','','($0.03)'),
    row('2/19/2026','ERR','Dividend','CDIV','','','$0.50'),
 ]],
 ['ambiguous_fee',[
    row('2/20/2026','ERR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
    row('2/19/2026','ERR','Fee A','DTAX','','','($0.03)'),
    row('2/19/2026','ERR','Fee B','DFEE','','','($0.03)'),
    row('2/19/2026','ERR','Dividend','CDIV','','','$0.50'),
 ]],
 ['ambiguous_dividend',[
    row('2/20/2026','ERR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
    row('2/19/2026','ERR','Fee','DTAX','','','($0.03)'),
    row('2/19/2026','ERR','Dividend A','CDIV','','','$0.50'),
    row('2/19/2026','ERR','Dividend B','CDIV','','','$0.50'),
 ]],
 ['fee_before_dividend',[
    row('2/20/2026','ERR','Dividend Reinvestment','Buy','0.047','$10.00','($0.47)'),
    row('2/18/2026','ERR','Fee','DFEE','','','($0.03)'),
    row('2/19/2026','ERR','Dividend','CDIV','','','$0.50'),
 ]],
] as [$label,$records]){
 $staged=stage_for($records);
 must(it_rc_auto_review($staged,'ERR')['ready']===false,
    $label.' remains blocked, no guessed dividend or fee allocation.');
}

// A canceled dividend-reinvestment purchase restores the earned dividend;
// its tax fee becomes a normal cash charge, not a second after-tax DRIP charge.
$canceled=stage_for([
 row('5/10/2026','CNL','Cancelled reinvestment','BCXL','0.013','$10.00','$0.13'),
 row('5/10/2026','CNL','Dividend Reinvestment','Buy','0.013','$10.00','($0.13)'),
 row('5/9/2026','CNL','Withholding','DTAX','','','($0.02)'),
 row('5/9/2026','CNL','Cash dividend','CDIV','','','$0.15'),
 row('1/2/2026','CNL','Original purchase','Buy','1','$10.00','($10.00)'),
]);
$canAuto=it_rc_auto_review($canceled,'CNL');
must($canAuto['ready']===true,'Cancel/reversal no longer blocks an earned cash dividend.');
$canHold=it_calculate($canAuto['events'])['holdings']['CNL'];
must(abs($canHold['dividends_cash']-0.15)<1e-9 &&
     abs($canHold['dividends_reinvested'])<1e-9 &&
     abs($canHold['dividend_withholding'])<1e-9 &&
     abs($canHold['fees']-0.02)<1e-9 &&
     abs($canHold['shares']-1)<1e-9,
     'Canceled DRIP restores gross cash dividend and charges tax only once.');

echo "All synthetic withholding-DRIP tests passed.\n";
