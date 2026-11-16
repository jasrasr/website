<?php
/**
 * File Name: corporate-actions.test.php
 * Revision: 1.0.0
 * Description: Synthetic source-paired Robinhood split, transfer, cash exit and unsupported corporate-action safeguards.
 * Author: Jason Lamb (with help from ChatGPT)
 * Created Date: 2026-11-16
 * Modified Date: 2026-11-16
 * Changelog: 1.0.0 - Safely infer original split ratios, preserve basis and reject unsupported reorganization chains.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';

function check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL: '.$label);
    echo "PASS: $label\n";
}
function fixture_ca(array $rows):array {
    $p=tempnam(sys_get_temp_dir(),'rh-corp-');
    $fh=fopen($p,'wb');
    fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach($rows as $r)fputcsv($fh,[$r[0],$r[0],$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6]]);
    fclose($fh);
    try{return it_rc_stage($p);}
    finally{unlink($p);}
}
function act(string $date,string $ticker,string $desc,string $code,string $qty='',string $price='',string $amount=''):array {
    return [$date,$ticker,$desc,$code,$qty,$price,$amount];
}
function company(string $name,string $cusip):string {
    return "$name CUSIP: $cusip";
}
function reverse_test(string $ticker,int $d,float $original,string $surrender,string $credited):void {
    $rows=[
        act('5/4/2026',$ticker,company('New Security','A00000002'),'SPR',$credited),
        act('5/4/2026',$ticker,company('Old Security','A00000001'),'SPR',$surrender.'S'),
        act('1/2/2026',$ticker,'Original acquisition','Buy',(string)$original,'$10.00','($100.00)'),
    ];
    $stage=fixture_ca($rows);
    $rev=it_rc_revision($stage);
    $auto=it_rc_auto_review($stage,$ticker);
    check($auto['ready']===true, $ticker.': unique reverse ratio accepted');
    $splits=array_values(array_filter($auto['events'],fn($e)=>$e['type']==='split'));
    check(count($splits)===1 &&
        $splits[0]['numerator']===1 && $splits[0]['denominator']===$d,
        $ticker.': inferred integer 1:'.$d.' ratio');
    $h=it_calculate($auto['events'])['holdings'][$ticker];
    check(abs($h['shares']-$original/$d)<0.0000001 &&
       abs($h['basis']-100)<0.0000001,
       $ticker.': exact adjusted shares and unchanged $100 total cost');
    check(it_rc_revision($stage)===$rev,
       $ticker.': read-time view did not modify the source draft');
    $oldStage=$stage;
    foreach($oldStage['rows'] as &$r) {
        if($r['code']==='SPR'){
            $r['kind']='unsupported';
            unset($r['credit'],$r['paired_surrender'],$r['paired_to'],$r['corporate_action'],$r['surrender_quantity']);
        }
    }
    unset($r);
    check(it_rc_auto_review($oldStage,$ticker)['ready']===true,
        $ticker.': previously staged unsupported SPR pair repaired on read');
    $batch=it_rc_batch_preview($oldStage,[]);
    check($batch['ready']===true && count($batch['eligible'])===1 &&
        count($batch['blocked'])===0,
        $ticker.': full report preview eligible without reupload');
}
reverse_test('FIFTY',50,50.0,'50','1');
reverse_test('TEN',10,10.0,'10','1');
reverse_test('TWENTY',20,.389863,'0.3899','0.0195');
reverse_test('TENTH',10,.749063,'0.7491','0.0749');
reverse_test('FRACT',20,.244246,'0.2442','0.0122');
$double=fixture_ca([
    act('12/24/2025','DOUBLE',company('Updated','Y73118112'),'SPR','0.2'),
    act('12/24/2025','DOUBLE',company('Older','Y73118104'),'SPR','1S'),
    act('7/9/2026','DOUBLE',company('Newest','Y73118120'),'SPR','0.0133'),
    act('7/9/2026','DOUBLE',company('Updated','Y73118112'),'SPR','0.2S'),
    act('1/2/2025','DOUBLE','Original acquisition','Buy','1','$100','($100)'),
]);
$auto=it_rc_auto_review($double,'DOUBLE');
check($auto['ready']===true,'Two same-ticker reverse splits import in sequence.');
$h=it_calculate($auto['events'])['holdings']['DOUBLE'];
check(abs($h['shares']-1/75)<0.000000001 && abs($h['basis']-100)<0.00001,
    'Multiple reverse splits preserve original basis and adjusted shares.');

$closed=fixture_ca([
    act('7/1/2020','CLOSED',company('Old shares','A00000001'),'SPR','10S'),
    act('7/1/2020','CLOSED',company('New shares','A00000002'),'SPR','1'),
    act('1/2/2020','CLOSED','Purchase','Buy','10','$2.00','($20.00)'),
    act('11/5/2020','CLOSED','Sale','Sell','1','$30.00','$30.00'),
]);
$auto=it_rc_auto_review($closed,'CLOSED');
check($auto['ready'],'Source SPLIT and post-split sale both reconstruct.');
$h=it_calculate($auto['events'])['holdings']['CLOSED'];
check($h['closed']===true && abs($h['realized']-10)<0.00001,
    'Post-split realized gain reflects full original purchase cost.');

$conv=fixture_ca([
    act('11/12/2018','OPEN','Account conversion','CONV','1'),
    act('7/10/2019','OPEN','Sale','Sell','1','$10.00','$10.00'),
]);
$auto=it_rc_auto_review($conv,'OPEN');
check($auto['ready']===true,'Unpriced conversion can add opening received shares.');
$h=it_calculate($auto['events'])['holdings']['OPEN'];
check(abs($h['shares'])<0.000001 && $h['basis_unknown'] &&
    $h['lifetime_profit']===null && $h['realized']===null,
    'Converted shares retain UNKNOWN original basis and no fabricated realized profit.');

$liquidation=fixture_ca([
    act('6/1/2026','FUND','Cash received thru Liquidation 1 shares at $24.50','LIQ','', '', '$24.50'),
    act('6/1/2026','FUND',company('Surrendered ETF','A00000001'),'SXCH','1S'),
    act('1/1/2026','FUND','Buy','Buy','1','$20.00','($20.00)'),
]);
$auto=it_rc_auto_review($liquidation,'FUND');
check($auto['ready']===true,'Matching LIQ/SXCH cash disposition becomes a sale.');
$h=it_calculate($auto['events'])['holdings']['FUND'];
check($h['closed'] && abs($h['realized']-4.5)<0.00001 && abs($h['sales_proceeds']-24.5)<0.00001,
    'Liquidation cash becomes realized proceeds without a ghost position.');
$merger=fixture_ca([
    act('10/31/2022','CASH','Cash received thru Merger 1.25 shares at $54.20','MRGC','','','$67.75'),
    act('10/31/2022','CASH',company('Surrendered Security','A00000001'),'MRGS','1.25S'),
    act('2/3/2022','CASH','Buy','Buy','1.25','$40.00','($50.00)'),
]);
$auto=it_rc_auto_review($merger,'CASH');
check($auto['ready']===true,'Matching MRGC/MRGS cash merger becomes a sale.');
$h=it_calculate($auto['events'])['holdings']['CASH'];
check($h['closed'] && abs($h['realized']-17.75)<0.00001,
    'Cash merger proceeds and actual original basis yield correct realized gain.');

$unpaired=fixture_ca([
    act('7/1/2026','UNPAIRED',company('New security','A00000002'),'SPR','0.1'),
    act('1/2/2026','UNPAIRED','Buy','Buy','1','$10','($10)'),
]);
check(it_rc_auto_review($unpaired,'UNPAIRED')['ready']===false,
    'Orphan SPR credit is still blocked.');
$conflicting=fixture_ca([
    act('7/1/2026','CONFLICT',company('Newest','A00000003'),'SPR','0.1'),
    act('7/1/2026','CONFLICT',company('Different','A00000002'),'SPR','0.2'),
    act('7/1/2026','CONFLICT',company('Old','A00000001'),'SPR','1S'),
    act('1/2/2026','CONFLICT','Buy','Buy','1','$10','($10)'),
]);
check(it_rc_auto_review($conflicting,'CONFLICT')['ready']===false,
    'Multiple competing same-day share credits remain blocked.');
$incorrect=fixture_ca([
    act('7/1/2026','WRONG',company('New','A00000002'),'SPR','0.08'),
    act('7/1/2026','WRONG',company('Old','A00000001'),'SPR','1S'),
    act('1/2/2026','WRONG','Buy','Buy','1','$10','($10)'),
]);
check(it_rc_auto_review($incorrect,'WRONG')['ready']===false,
    'Unsupported ratios / incorrect shares cannot be forced through.');
$cashPartial=fixture_ca([
    act('6/1/2026','CASHFAIL','Cash received thru Liquidation 1 shares at $25','LIQ','','','$25'),
    act('6/1/2026','CASHFAIL',company('Surrendered','A00000001'),'SXCH','1S'),
    act('1/2/2026','CASHFAIL','Buy','Buy','2','$10','($20)'),
]);
check(it_rc_auto_review($cashPartial,'CASHFAIL')['ready']===false,
    'A cash liquidation which does not surrender every outstanding share remains blocked.');
$spinoff=fixture_ca([
    act('1/1/2026','CHILD',company('New shares','A00000002'),'SPR','0.4'),
    act('1/1/2026','PARENT',company('Old shares','A00000001'),'SPR','10S'),
    act('1/1/2025','PARENT','Buy','Buy','10','$10','($100)'),
]);
check(it_rc_batch_preview($spinoff,[])['blocked']!==[],
    'Cross-symbol spinoff remains an explicit exception, not a fabricated same-ticker split.');
$unknownCil=fixture_ca([
    act('2/3/2026','CIL','Cash in lieu','CIL','','','$1.23'),
]);
check(it_rc_auto_review($unknownCil,'CIL')['ready']===false,
    'Unpaired cash in lieu remains blocked.');

echo "All source-paired corporate-action tests passed.\n";
