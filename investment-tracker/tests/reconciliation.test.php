<?php
/**
 * File Name: reconciliation.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Synthetic editable brokerage staging and tax-lot import checks.
 * Revision: 1.0.0
 * Created Date: 2026-10-30
 * Modified Date: 2026-10-30
 * Changelog: 1.0.0 - Protect unassigned sales, verify splits, DRIPs, lending, fees, share totals and unsupported actions.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';
function ok(bool $condition,string $message):void {
    if (!$condition) throw new RuntimeException('FAILED: '.$message);
    echo "PASS: $message\n";
}
function fixture(array $rows):string {
    $file=tempnam(sys_get_temp_dir(),'broker-');
    if (!$file) throw new RuntimeException('Unable to create temporary CSV.');
    $fh=fopen($file,'wb');
    fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach($rows as $row)fputcsv($fh,$row);
    fclose($fh);return $file;
}
$rows=[
 ['2/3/2026','2/3/2026','2/3/2026','TEST','Test Corporation','AFEE','','','($0.01)'],
 ['2/1/2026','2/1/2026','2/1/2026','TEST','Stock Lending','SLIP','','','$0.25'],
 ['1/2/2026','1/2/2026','1/6/2026','TEST','Test Corporation','Sell','2','$125.00','$250.00'],
 ['6/1/2025','6/1/2025','6/2/2025','TEST','Test Corporation','SPL','9.009','',''],
 ['2/3/2025','2/3/2025','2/4/2025','TEST',"Test Corporation\nDividend Reinvestment",'Buy','0.001','$100.00','($0.10)'],
 ['2/2/2025','2/2/2025','2/2/2025','TEST','Cash Dividend','CDIV','','','$0.10'],
 ['1/2/2025','1/2/2025','1/3/2025','TEST','Test Corporation','Buy','1','$100.00','($100.00)'],
];
$file=fixture($rows);
try{
    $stage=it_rc_stage($file);
    ok(count($stage['rows'])===7,'Stage preserves all symbol activity including paired dividends.');
    ok(count(it_rc_symbols($stage))===1,'Staged ticker available for correction.');
    ok(count(array_filter($stage['rows'],fn($r)=>$r['kind']==='paired_credit'))===1,'Paired dividend credit is not double counted.');
    $buy=array_values(array_filter($stage['rows'],fn($r)=>$r['kind']==='buy'))[0];
    $sale=array_values(array_filter($stage['rows'],fn($r)=>$r['kind']==='sell'))[0];
    $split=array_values(array_filter($stage['rows'],fn($r)=>$r['kind']==='split_credit'))[0];
    $stage=it_rc_review($stage,'TEST',[
        'expected_shares'=>8.01,'ratios'=>[],'sales'=>[],'source_confirmed'=>false
    ]);
    $incomplete=it_rc_build($stage,'TEST');
    ok(!$incomplete['ready'] && count($incomplete['issues'])>=3,'Blocked before lot election, ratio and broker confirmation.');
    $stage=it_rc_review($stage,'TEST',[
        'expected_shares'=>8.01,'ratios'=>[$split['id']=>['n'=>10,'d'=>1]],
        'sales'=>[$sale['id']=>[$buy['id']=>2]],
        'source_confirmed'=>true
    ]);
    $good=it_rc_build($stage,'TEST');
    ok($good['ready'],'Verified split ratio, lot allocation and share balance pass.');
    $calc=it_calculate($good['events']);
    $h=$calc['holdings']['TEST'];
    ok(abs($h['shares']-8.01)<1e-8,'Split and sale leave 8.01 shares.');
    ok(abs($h['dividends_reinvested']-0.10)<1e-8 && abs($h['dividends_cash'])<1e-8,
        'Reinvested dividend is a new lot without duplicated cash income.');
    ok(abs($h['other_income']-0.25)<1e-8 && abs($h['fees']-0.01)<1e-8,'Stock lending and broker fees remain separate from dividends.');
    ok(abs($h['sales_proceeds']-250)<1e-8 && abs($h['purchases']-100)<1e-8,
        'Cash purchases and sale proceeds are recorded.');
    ok($h['value']===null,'No stock price observation means no fabricated market value.');
    $bad=$stage;$bad['reviews']['TEST']['expected_shares']=8.02;
    ok(!it_rc_build($bad,'TEST')['ready'],'Mismatch against broker-confirmed share quantity blocks import.');
    $bad=$stage;$bad['reviews']['TEST']['ratios'][$split['id']]=['n'=>5,'d'=>1];
    ok(!it_rc_build($bad,'TEST')['ready'],'Incorrect corporate split credit blocks import.');
    $bad=$stage;$bad['reviews']['TEST']['sales'][$sale['id']]=[$buy['id']=>12];
    ok(!it_rc_build($bad,'TEST')['ready'],'Oversold purchase lot blocks import.');
    $rev=it_rc_revision($stage);
    ok(strlen($rev)===64 && $rev!==it_rc_revision($bad),'Revision changes when user adjustments change.');
    // Completely separate unsupported ticker cannot be imported by editing other tickers.
    $file2=fixture([['4/15/2026','4/15/2026','4/15/2026','OTHER','Conversion','CONV','1','','']]);
    try {
        $blocked=it_rc_stage($file2);
        $blocked=it_rc_review($blocked,'OTHER',['expected_shares'=>0,'source_confirmed'=>true]);
        ok(!it_rc_build($blocked,'OTHER')['ready'],'Unsupported conversion never silently disappears.');
    } finally {unlink($file2);}
    $file3=fixture($rows);
    try {
        ok(array_column(it_rc_stage($file3)['rows'],'id')===array_column($stage['rows'],'id'),
            'Overlapping exports with identical activities retain stable transaction IDs.');
    }finally{unlink($file3);}
}finally{unlink($file);}
echo "All editable reconciliation tests passed.\n";
