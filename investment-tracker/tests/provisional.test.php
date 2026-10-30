<?php
/**
 * File Name: provisional.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Demonstrate CSV-driven stock splits, FIFO assumptions and unknown-basis stock receipts without user attestation.
 * Revision: 1.0.0
 * Created Date: 2026-10-30
 * Modified Date: 2026-10-30
 * Changelog: 1.0.0 - Synthetic provisional import accounting safety tests.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';
function ensure(bool $ok,string $label):void {
 if(!$ok)throw new RuntimeException('FAIL: '.$label);
 echo "PASS: $label\n";
}
$path=tempnam(sys_get_temp_dir(),'mock-rh-');
if(!$path)throw new RuntimeException('Fixture unavailable.');
$fh=fopen($path,'wb');
fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
$rows=[
 ['1/1/2020','1/1/2020','1/1/2020','DEMO','Synthetic Buy','Buy','0.1','$100.00','($10.00)'],
 ['8/31/2020','8/31/2020','8/31/2020','DEMO','Synthetic split','SPL','0.3','',''],
 ['9/1/2020','9/1/2020','9/1/2020','DEMO','Synthetic sell','Sell','0.2','$50.00','$10.00'],
 ['8/14/2023','8/14/2023','8/14/2023','DEMO','Synthetic share receipt','REC','0.025','',''],
 ['8/15/2023','8/15/2023','8/15/2023','DEMO','Synthetic dividend','CDIV','','','$0.01'],
 ['8/16/2023','8/16/2023','8/16/2023','DEMO',"Synthetic\nDividend Reinvestment",'Buy','0.0001','$100.00','($0.01)'],
];
foreach($rows as $row)fputcsv($fh,$row);
fclose($fh);
try {
 $stage=it_rc_stage($path);
 $auto=it_rc_auto_review($stage,'DEMO');
 ensure($auto['ready']===true,'Provisional stock inferred and imported without manual one-year attestation');
 ensure($auto['estimated']===true,'Inferred stock explicitly classified estimated');
 ensure(count($auto['warnings'])>=2,'Unknown basis and FIFO allocation warned');
 $events=$auto['events'];
 ensure(count(array_filter($events,fn($e)=>$e['type']==='split'))===1,'Rounded share credit matched unique four-for-one split');
 ensure(count(array_filter($events,fn($e)=>$e['type']==='receipt'))===1,'REC share credit preserved without invented cost');
 ensure(count(array_filter($events,fn($e)=>$e['type']==='sell'))===1,'Sale assigned to provisional historical FIFO lot');
 ensure(count(array_filter($events,fn($e)=>$e['type']==='dividend'))===0,'Matched dividend not double-counted');
 $data=it_calculate($events);
 $h=$data['holdings']['DEMO'];
 ensure(abs($h['shares']-0.2251)<0.00000001,'Position includes trade, split, sale, receipt and dividend reinvestment');
 ensure($h['basis_unknown']===true && $h['unrealized']===null,'Unknown receipt basis suppresses incorrect gain amount');
 ensure($h['lifetime_profit']===null && $h['xirr_pct']===null,'Unknown receipt basis blocks misleading lifetime return');
 ensure($h['allocation_estimated']===true,'Broker FIFO tax-lot election is not falsely declared verified');
 $bad=$stage;
 $bad['rows'][]=array_merge($bad['rows'][0],['id'=>'zz-top','kind'=>'unsupported','code'=>'CONV']);
 ensure(it_rc_auto_review($bad,'DEMO')['ready']===false,'Unsupported conversion is never silently ignored');
} finally {unlink($path);}
echo "All provisional auto-import tests passed.\n";
