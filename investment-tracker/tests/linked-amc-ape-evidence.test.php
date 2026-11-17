<?php
/**
 * File Name: linked-amc-ape-evidence.test.php
 * Revision: 1.0.0
 * Description: Synthetic AMC/APE multi-security basis-evidence preview and no-unsafe-import regression.
 * Author: Jason Lamb (with help from ChatGPT)
 * Created: 2026-11-16
 * Modified: 2026-11-16
 * Changelog: 1.0.0 - Diagnose verified source chain without guessing 2022/2023 corporate basis adjustments.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/reconciliation.php';

function prove(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL: '.$label);
    echo "PASS: $label\n";
}
function source_row(string $date,string $ticker,string $description,string $code,string $qty='',string $amount=''):array{
    return [$date,$date,$date,$ticker,$description,$code,$qty,'',$amount];
}
function make_stage(array $rows):array {
    $path=tempnam(sys_get_temp_dir(),'amc-ape-evidence-');
    $fh=fopen($path,'wb');
    fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
    foreach($rows as $row)fputcsv($fh,$row);
    fclose($fh);
    try{return it_rc_stage($path);}finally{unlink($path);}
}
$rows=[
  source_row('8/28/2023','AMC','Class A CUSIP: 00165C302','SDIV','0.0667'),
  source_row('8/25/2023','AMC','Class A CUSIP: 00165C302','MRGS','0.7000'),
  source_row('8/25/2023','APE','Preferred unit CUSIP: 00165C203','MRGS','7S'),
  source_row('8/24/2023','AMC','Class A CUSIP: 00165C302','SPR','0.5000'),
  source_row('8/24/2023','AMC','Class A CUSIP: 00165C104','SPR','5S'),
  source_row('11/10/2022','APE','Additional APE buy','Buy','2','($8.00)'),
  source_row('8/22/2022','APE','Original preferred dividend CUSIP: 00165C203','SOFF','5'),
  source_row('1/1/2022','AMC','AMC original purchase','Buy','5','($100.00)'),
];
$stage=make_stage($rows);
$rev=it_rc_revision($stage);
$matched=it_rc_amc_ape_review_evidence($stage);
prove($matched!==null && $matched['group']==='AMC_APE_2022_2023' &&
      $matched['source_quantities_match']===true,
      'Synthetically complete, issuer-documented security action chain is identified.');
prove(count($matched['source_lines'])===6 && count($matched['required_evidence'])===3 &&
      $matched['review_status']==='broker_basis_evidence_required',
      'Source rows produce a targeted checklist for broker-confirmed distributions and lot basis.');
$batch=it_rc_batch_preview($stage,[]);
prove(!$batch['ready'] && $batch['import_count']===0 &&
      count($batch['eligible'])===0 && count($batch['blocked'])===2,
      'Both securities stay blocked; no phantom APE receipt or settlement buy is imported.');
foreach($batch['blocked'] as $stock){
    prove(in_array($stock['ticker'],['AMC','APE'],true) &&
          $stock['group']==='AMC_APE_2022_2023' &&
          str_contains($stock['reason'],'tax basis') &&
          count($stock['required_evidence'])===3,
          'Each ticker displays linked evidence requirements instead of generic unsupported code.');
}
prove(it_rc_revision($stage)===$rev,
      'Preview does not alter private source record or previously staged report revision.');
$pub=it_rc_batch_public($batch,$stage,[]);
prove($pub['eligible_count']===0 && $pub['blocked_count']===2 &&
      !isset($pub['events']),
      'Public preview contains no importable unverified ledger events.');
$missing=$stage;
foreach($missing['rows'] as &$r){
    if(($r['code']??'')==='SDIV')$r['code']='UNKNOWN';
}
unset($r);
prove(it_rc_amc_ape_review_evidence($missing)===null,
      'Missing settlement proof cannot be mislabeled as a complete linked chain.');
$mismatch=$stage;
foreach($mismatch['rows'] as &$r){
    if(($r['code']??'')==='SDIV')$r['quantity']=0.3;
}
unset($r);
prove(it_rc_amc_ape_review_evidence($mismatch)===null,
      'Incorrect settlement quantity invalidates the linked corporate-action diagnosis.');
$other=make_stage([
    source_row('8/25/2023','OTHER','Unrelated merger','MRGS','1S'),
    source_row('1/1/2022','OTHER','Purchase','Buy','1','($1.00)'),
]);
prove(it_rc_amc_ape_review_evidence($other)===null,
      'Unrelated ticker with the same corporate code is not treated as AMC/APE.');
$already=it_event(['date'=>'2021-01-01','ticker'=>'AMC','type'=>'buy','quantity'=>1,'amount'=>1]);
$skip=it_rc_batch_preview($stage,[$already]);
prove(count($skip['skipped'])>=1 && $skip['import_count']===0,
      'Already-imported history is still skipped without reimport.');
echo "All linked AMC/APE evidence review checks passed.\n";
