<?php
/**
 * File Name: provisional.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Demonstrate CSV-driven stock splits, FIFO assumptions and unknown-basis stock receipts without user attestation.
 * Revision: 1.2.0
 * Created Date: 2026-10-30
 * Modified Date: 2026-11-13
 * Changelog: 1.2.0 - Test old staged REC receipts in ticker views, import-all preview and unknown-basis valuation without mutating drafts. 1.1.0 - Validate automatic all-tickers batch preview, source exception queue and stale review fingerprint. 1.0.0 - Synthetic provisional import accounting safety tests.
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
 // Mimic an already staged Robinhood CSV from before REC share-credit
 // support. It saved code=REC as "unsupported", although the original
 // source row contains a valid 0.0281-share AAPL credit with no cash amount.
 $oldDraft=$stage;
 foreach($oldDraft['rows'] as &$source) {
     if($source['code']==='REC') {
         $source['kind']='unsupported';
         $source['line']=773;
         $source['quantity']=0.0281;
         $source['amount']=null;
         $source['price']=null;
     }
 }
 unset($source);
 $originalRevision=it_rc_revision($oldDraft);
 $before=it_rc_view($oldDraft,'DEMO');
 $credit=array_values(array_filter($before['rows'],static fn($x)=>$x['code']==='REC'));
 ensure(count($credit)===1 && $credit[0]['kind']==='receipt',
    'Previously staged row 773 REC is shown as share receipt, not unsupported.');
 ensure(it_rc_view($oldDraft)['tickers'][0]['counts']['receipt']===1,
    'All-stock preview counts legacy REC credits as receipts.');
 $retro=it_rc_auto_review($oldDraft,'DEMO');
 ensure($retro['ready']===true && count(array_filter($retro['events'],
    static fn($e)=>$e['type']==='receipt'))===1,
    'Existing staged CSV imports previously unsupported REC shares automatically.');
 $retroHolding=it_calculate($retro['events'])['holdings']['DEMO'];
 ensure($retroHolding['basis_unknown']===true &&
    $retroHolding['unrealized']===null &&
    $retroHolding['lifetime_profit']===null,
    'Legacy REC adds shares while withholding unknown cost basis and profit.');
 ensure(it_rc_revision($oldDraft)===$originalRevision &&
    count(array_filter($oldDraft['rows'],static fn($x)=>$x['code']==='REC' && $x['kind']==='unsupported'))===1,
    'Legacy import fix does not mutate saved source rows or draft revision.');
 $retroBatch=it_rc_batch_preview($oldDraft,[]);
 ensure($retroBatch['ready']===true &&
    count($retroBatch['eligible'])===1 && count($retroBatch['blocked'])===0,
    'Preview import of all stocks no longer blocks a valid legacy REC credit.');
 foreach ([
    ['quantity'=>0,'amount'=>null,'price'=>null],
    ['quantity'=>-0.0281,'amount'=>null,'price'=>null],
    ['quantity'=>0.0281,'amount'=>25.0,'price'=>null],
    ['quantity'=>0.0281,'amount'=>null,'price'=>10.0],
 ] as $invalid) {
    $badReceipt=$oldDraft;
    foreach($badReceipt['rows'] as &$source) {
        if($source['code']==='REC')$source=array_merge($source,$invalid);
    }
    unset($source);
    ensure(it_rc_auto_review($badReceipt,'DEMO')['ready']===false,
        'Invalid, cash-bearing, or priced REC action is not silently converted to a share receipt.');
 }
 $bad=$stage;
 $bad['rows'][]=array_merge($bad['rows'][0],['id'=>'zz-top','kind'=>'unsupported','code'=>'CONV']);
 ensure(it_rc_auto_review($bad,'DEMO')['ready']===false,'Unsupported conversion is never silently ignored');
} finally {unlink($path);}
// Batch preview should import clean securities without importing complex actions.
$stage2=$stage;
$stage2['rows'][]=array_merge($stage2['rows'][0],[
    'id'=>'zz-bad-action','ticker'=>'BLOCK','kind'=>'unsupported','code'=>'CONV',
]);
$stage2['rows'][]=array_merge($stage2['rows'][0],[
    'id'=>'zz-good-buy','ticker'=>'OTHER','kind'=>'buy','amount'=>-15.0,'quantity'=>0.5
]);
$plan=it_rc_batch_preview($stage2,[]);
ensure($plan['ready']===true,'Full report preview has at least one supported ticker');
ensure(count($plan['eligible'])===2,'Batch imports both supported synthetic tickers');
ensure(count($plan['blocked'])===1 && $plan['blocked'][0]['ticker']==='BLOCK',
  'Unsupported corporate-action ticker remains in exception queue');
$public=it_rc_batch_public($plan,$stage2,[]);
ensure(!array_key_exists('events',$public) && $public['eligible_count']===2
  && preg_match('/^[a-f0-9]{64}$/',$public['fingerprint'])===1,
  'Public preview contains status/fingerprint, not raw imported ledger event list');
$existing=[[
    'id'=>'personal-other','date'=>'2020-01-01','ticker'=>'OTHER','type'=>'buy',
    'quantity'=>0.1,'amount'=>1,'price'=>0,'numerator'=>0,'denominator'=>0,'lot_id'=>'','note'=>''
]];
$withExisting=it_rc_batch_preview($stage2,$existing);
ensure(count($withExisting['eligible'])===1 && count($withExisting['skipped'])===1,
  'Batch skips a ticker already present in the authenticated portfolio');
ensure($public['fingerprint']!==it_rc_batch_public($withExisting,$stage2,$existing)['fingerprint'],
  'Preview fingerprint detects changes in existing portfolio state');

echo "All provisional auto-import tests passed.\n";
