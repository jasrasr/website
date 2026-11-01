<?php
/**
 * File Name: robinhood.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Synthetic Robinhood CSV parser and privacy/reconciliation tests.
 * Revision: 1.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.0.0 - Validate multiline activity, trade types, DRIP pairing, footer and unsafe sale-lot blocking.
 */
declare(strict_types=1);
require_once dirname(__DIR__).'/robinhood.php';
function rh_assert(bool $condition,string $why):void {
    if(!$condition)throw new RuntimeException('FAILED: '.$why);
    echo "PASS: $why\n";
}
rh_assert(abs(it_rh_amount('($1,200.12)')+1200.12)<1e-8,'Parenthesized thousands are outflows');
rh_assert(abs(it_rh_amount('$0.41')-0.41)<1e-8,'Dividend is positive');
rh_assert(it_rh_amount('')===null,'Blank activity amount is null');
$tmp=tempnam(sys_get_temp_dir(),'rh-');
if(!$tmp)throw new RuntimeException('Unable to create test fixture');
$fh=fopen($tmp,'wb');
fputcsv($fh,['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount']);
fputcsv($fh,['10/9/2026','10/9/2026','10/13/2026','NVDA',"NVIDIA\nCUSIP: 67066G104",'Buy','0.106928','$233.80','($25.00)']);
fputcsv($fh,['9/15/2026','9/15/2026','9/16/2026','NVDA',"NVIDIA\nCUSIP: 67066G104\nDividend Reinvestment",'Buy','0.001925','$212.91','($0.41)']);
fputcsv($fh,['9/14/2026','9/14/2026','9/14/2026','NVDA','Cash Div: R/D 2026-09-10 - 1.628028 shares at 0.25','CDIV','','','$0.41']);
fputcsv($fh,['6/10/2024','6/10/2024','6/10/2024','NVDA','NVIDIA CUSIP 67066G104','SPL','0.5627','','']);
fputcsv($fh,['5/24/2021','5/24/2021','5/25/2021','NVDA','NVIDIA','Sell','0.073831','$627.71','$46.34']);
fputcsv($fh,['2/4/2026','2/4/2026','2/5/2026','','ACH Deposit','ACH','','','$30.00']);
fputcsv($fh,['','','','','','','','','','The data provided is for informational purposes only.']);
fclose($fh);
try{
  $a=it_rh_parse_csv($tmp);
  rh_assert($a['records']===6,'Ignores trailing disclaimer, not transactions');
  rh_assert($a['ticker_count']===1,'Groups symbols without account transfers');
  rh_assert($a['matched_dividend_reinvestments']===1,'Links dividend credit to subsequent DRIP of same ticker and amount');
  rh_assert($a['counts']['split_credit']===1,'Stock split additions are flagged as credits, not ratios');
  rh_assert($a['counts']['sell']===1,'Sold shares are kept and flagged for lot election');
  rh_assert($a['import_ready']===false && $a['tickers'][0]['import_ready']===false,'Nothing auto-imports or assigns lots');
  rh_assert($a['tickers'][0]['review_required']===true,'Ticker requires review when sold or split');
  $invalid=str_replace('Activity Date','Created Date',file_get_contents($tmp));
  file_put_contents($tmp,$invalid);
  try{it_rh_parse_csv($tmp);throw new RuntimeException('Invalid CSV header was accepted');}
  catch(InvalidArgumentException $e){rh_assert(true,'Unrecognized source headers rejected');}
} finally {unlink($tmp);}
echo "All Robinhood CSV parser tests passed.\n";
