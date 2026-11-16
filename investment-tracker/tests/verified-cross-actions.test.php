<?php
/**
 * File Name: verified-cross-actions.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Synthetic merger-ticker, fractional-split and cross-symbol cash-settlement regression tests.
 * Revision: 1.0.0
 * Created Date: 2026-11-16
 * Modified Date: 2026-11-16
 * Changelog: 1.0.0 - Validate published issuer ratios against synthetic Robinhood source rows and protect private drafts.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';
function verified_assert(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException('FAILED: '.$message);
    echo "PASS: $message\n";
}
function verified_row(string $date,string $ticker,string $desc,string $code,string $qty='',string $price='',string $amount=''):array {
    return [$date,$date,$date,$ticker,$desc,$code,$qty,$price,$amount];
}
function verified_stage(array $rows):array {
    $f=tempnam(sys_get_temp_dir(),'rh-vca-');
    $h=fopen($f,'wb');
    fputcsv($h,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach($rows as $r)fputcsv($h,$r);
    fclose($h);
    try{return it_rc_stage($f);}finally{unlink($f);}
}
function verified_legacy(array $stage):array {
    foreach($stage['rows'] as &$r){
        if(isset($r['source_ticker'])){
            $r['ticker']=$r['source_ticker'];
            unset($r['source_ticker']);
        }
        if(($r['code']??'')==='SPR'||($r['code']??'')==='CIL'){
            if(($r['corporate_action']??'')==='cash_in_lieu') {
                $r['date']=$r['original_date'];$r['quantity']=null;$r['amount']=null;
                unset($r['original_date']);
            }
            $r['kind']='unsupported';
            unset($r['corporate_action'],$r['ratio_n'],$r['ratio_d'],$r['split_round_up'],
                  $r['paired_surrender'],$r['paired_cash_id'],$r['paired_to'],$r['credit'],
                  $r['reported_split_shares'],$r['source_cash_line'],$r['surrender_quantity']);
        }
    }
    unset($r);
    return $stage;
}
// WKHS corporate ratios and CUSIPs are independently published facts. The
// fictional holding is intentionally NOT the user's fractional share amount.
$wk=verified_stage([
 verified_row('1/7/2026','WKHS','Stock Lending','SLIP','','','$0.01'),
 verified_row('12/10/2025','WKHS','Workhorse CUSIP: 98138J503','SPR','1'),
 verified_row('12/8/2025','WKHS','Workhorse CUSIP: 98138J404','SPR','1S'),
 verified_row('3/26/2025','WKHS','Workhorse CUSIP: 98138J404','SPR','1'),
 verified_row('3/17/2025','WKHS','Workhorse CUSIP: 98138J305','SPR','1S'),
 verified_row('6/27/2024','WKHS','Workhorse CUSIP: 98138J305','SPR','1'),
 verified_row('6/17/2024','WKHS','Workhorse CUSIP: 98138J206','SPR','0.0444S'),
 verified_row('6/4/2021','WKHS','Synthetic stock acquisition CUSIP: 98138J206','Buy','0.044444','$22.50','($1.00)'),
]);
$wkRev=it_rc_revision($wk);
$wkAuto=it_rc_auto_review($wk,'WKHS');
verified_assert($wkAuto['ready'],'Three delayed, issuer-confirmed whole-share rounded splits import.');
$wkSplits=array_values(array_filter($wkAuto['events'],fn($e)=>$e['type']==='split'));
verified_assert(count($wkSplits)===3 &&
 $wkSplits[0]['numerator']===1 && $wkSplits[0]['denominator']===20 &&
 $wkSplits[1]['numerator']===2 && $wkSplits[1]['denominator']===25 &&
 $wkSplits[2]['numerator']===1 && $wkSplits[2]['denominator']===12,
 'Documented 1:20, 2:25 and 1:12 conversion ratios are retained.');
$wkCalc=it_calculate($wkAuto['events']);
$wkHolding=$wkCalc['holdings']['WKHS'];
verified_assert(abs($wkHolding['shares']-1)<1e-9 &&
 abs($wkHolding['basis']-1)<1e-9 &&
 $wkHolding['lots'][0]['date']==='2021-06-04' &&
 abs($wkHolding['lots'][0]['adjusted_acquired_shares']-1)<1e-9,
 'Three rounds of broker whole-share round-up do not create purchases or alter original tax basis.');
verified_assert(it_rc_revision($wk)===$wkRev &&
 it_rc_reconcile_cancellations($wk['rows'])===$wk['rows'],
 'WKHS source normalization is non-mutating and idempotent.');
$oldWk=verified_legacy($wk);$oldRevision=it_rc_revision($oldWk);
verified_assert(it_rc_auto_review($oldWk,'WKHS')['ready'] &&
 it_rc_revision($oldWk)===$oldRevision,
 'Previous private draft with unsupported WKHS rows works without reupload.');
$twoLots=verified_stage([
 verified_row('6/27/2024','WKHS','Workhorse CUSIP: 98138J305','SPR','1'),
 verified_row('6/17/2024','WKHS','Workhorse CUSIP: 98138J206','SPR','0.0889S'),
 verified_row('6/2/2024','WKHS','Synthetic purchase B','Buy','0.044444','$10','($0.44)'),
 verified_row('6/1/2024','WKHS','Synthetic purchase A','Buy','0.044444','$10','($0.44)'),
]);
verified_assert(it_rc_auto_review($twoLots,'WKHS')['ready']===false,
 'Whole-account split rounding cannot be assigned to multiple tax lots without verification.');

// A corporate ticker change is not a sale. Original purchase money and
// original acquisition date must continue under the successor symbol.
$host=verified_stage([
 verified_row('8/31/2026','HOST','Host Digital CUSIP: 42227T303','SPR','1'),
 verified_row('8/31/2026','HCWC','Healthy Choice CUSIP: 42227T105','SPR','35S'),
 verified_row('1/2/2025','HCWC','Original investment','Buy','35','$2.00','($70.00)'),
]);
$hostRevision=it_rc_revision($host);
$hostList=it_rc_symbols($host);
verified_assert(count($hostList)===1 && $hostList[0]['ticker']==='HOST' &&
 in_array('HCWC',$hostList[0]['source_tickers'],true),
 'Old and new ticker histories are grouped as one successor security.');
$hostAuto=it_rc_auto_review($host,'HOST');
verified_assert($hostAuto['ready'],'Cross-ticker split source validates with documented 1:35 ratio.');
$hostHold=it_calculate($hostAuto['events'])['holdings']['HOST'];
verified_assert(abs($hostHold['shares']-1)<1e-9 &&
 abs($hostHold['basis']-70)<1e-9 &&
 abs($hostHold['purchases']-70)<1e-9 &&
 $hostHold['lots'][0]['date']==='2025-01-02',
 'Corporate name change retains original purchase basis and acquisition date.');
verified_assert(!array_key_exists('HCWC',it_calculate($hostAuto['events'])['holdings']),
 'Ticker rename is not shown as a sold investment with fake losses.');
verified_assert(it_rc_revision($host)===$hostRevision &&
 it_rc_reconcile_cancellations($host['rows'])===$host['rows'],
 'Renamed source rows remain in the private draft and normalize idempotently.');
$legacyHost=verified_legacy($host);$oldHostRev=it_rc_revision($legacyHost);
verified_assert(it_rc_auto_review($legacyHost,'HOST')['ready'] &&
 it_rc_revision($legacyHost)===$oldHostRev,
 'Previously staged HCWC/HOST symbols are linked without modifying saved draft.');
$already=it_event(['date'=>'2025-01-01','ticker'=>'HCWC','type'=>'buy','quantity'=>1,'amount'=>10]);
$preview=it_rc_batch_preview($host,[$already]);
verified_assert(count($preview['eligible'])===0 && count($preview['skipped'])===1,
 'Previously imported original ticker prevents duplicate successor-ticker import.');
$hostCompleted=$host;$hostCompleted['completed']['HCWC']=['imported_at'=>'2026-09-01'];
verified_assert(count(it_rc_batch_preview($hostCompleted,[])['skipped'])===1,
 'Completed original-ticker record also blocks duplicate history.');
$incompleteHost=verified_stage([
 verified_row('8/31/2026','HOST','Host Digital CUSIP: 42227T303','SPR','1'),
 verified_row('1/2/2025','HCWC','Original investment','Buy','35','$2.00','($70.00)'),
]);
verified_assert(count(it_rc_batch_preview($incompleteHost,[])['blocked'])===1,
 'Missing predecessor source cannot fabricate the corporate action.');

// CIL is paid under a successor label but belongs to the original shares.
// Use unrelated amounts and a fictional holding, not an owner's tax lot.
$chk=verified_stage([
 verified_row('4/27/2020','CHKAQ','CIL on 0.250 @ $24.00 - CHK','CIL','','','$6.00'),
 verified_row('4/15/2020','CHK','Chesapeake CUSIP: 165167107','SPR','50S'),
 verified_row('2/19/2020','CHK','Original position','Buy','50','$1.00','($50.00)'),
]);
$chkRevision=it_rc_revision($chk);
$symbols=it_rc_symbols($chk);
verified_assert(count($symbols)===1 && $symbols[0]['ticker']==='CHK',
 'CIL cash-only alias does not create a ghost CHKAQ position.');
$chkAuto=it_rc_auto_review($chk,'CHK');
verified_assert($chkAuto['ready'],'Matched partial-share entitlement becomes a complete cash disposition.');
$chkHolding=it_calculate($chkAuto['events'])['holdings']['CHK'];
verified_assert($chkHolding['closed'] && abs($chkHolding['shares'])<1e-9 &&
 abs($chkHolding['sales_proceeds']-6)<1e-9 &&
 abs($chkHolding['realized']+44)<1e-9,
 'CIL proceeds and original basis produce historical realized loss without an extra stock purchase.');
verified_assert(it_rc_revision($chk)===$chkRevision &&
 it_rc_reconcile_cancellations($chk['rows'])===$chk['rows'],
 'CIL normalization is idempotent and does not rewrite stored data.');
$legacyChk=verified_legacy($chk);
verified_assert(it_rc_auto_review($legacyChk,'CHK')['ready'],
 'Earlier private staged CIL row maps to its original symbol on read.');
$badAmount=verified_stage([
 verified_row('4/27/2020','CHKAQ','CIL on 0.250 @ $24.00 - CHK','CIL','','','$99.00'),
 verified_row('4/15/2020','CHK','Chesapeake CUSIP: 165167107','SPR','50S'),
 verified_row('2/19/2020','CHK','Original','Buy','50','$1.00','($50.00)'),
]);
verified_assert(count(it_rc_batch_preview($badAmount,[])['blocked'])===2,
 'Cash-in-lieu amount not equal to share entitlement times price remains blocked.');
$ambiguous=verified_stage([
 verified_row('4/27/2020','CHKAQ','CIL on 0.250 @ $24.00 - CHK','CIL','','','$6.00'),
 verified_row('4/27/2020','CHKAQ','CIL on 0.250 @ $24.00 - CHK','CIL','','','$6.00'),
 verified_row('4/15/2020','CHK','Chesapeake CUSIP: 165167107','SPR','50S'),
 verified_row('2/19/2020','CHK','Original','Buy','50','$1.00','($50.00)'),
]);
verified_assert(count(it_rc_batch_preview($ambiguous,[])['blocked'])===2,
 'Ambiguous duplicated cash settlements are not silently paired.');

// Never invent AMC/APE basis allocations from a noncash merger or spinoff.
$cross=verified_stage([
 verified_row('8/25/2023','AMC','Corporate conversion','MRGS','0.1234'),
 verified_row('8/25/2023','APE','Preferred security','MRGS','1.234S'),
 verified_row('8/22/2022','APE','Spinoff','SOFF','1.234'),
 verified_row('1/2/2022','AMC','Original purchase','Buy','1.234','$10.00','($12.34)'),
]);
$remaining=it_rc_batch_preview($cross,[]);
verified_assert(count($remaining['blocked'])===2,
 'Noncash merger and distributed preferred equity stay blocked for verified basis allocation.');

echo "All verified special corporate-action tests passed.\n";
