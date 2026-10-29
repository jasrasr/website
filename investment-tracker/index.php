<?php
/**
 * File Name: index.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Private investment tax-lot dashboard and authenticated ledger API.
 * Revision: 1.1.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.1.0 - Read-only NVIDIA demo, original and split-adjusted lot display, tax-term clarity. 1.0.0 - Shared login, manual events, atomic CSV import and lot dashboard.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/demo.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; object-src \'none\'; base-uri \'none\'; frame-ancestors \'none\'');
$isApi=isset($_GET['api']);
$isDemo=($_GET['demo']??'')==='1';
$auth=\Jasr\Framework\SharedIdentity::connect();
$user=$auth->requireProject('investment-tracker',$_SERVER['REQUEST_METHOD']==='POST'?'member':'viewer',$isApi);
$storage=__DIR__.'/storage';
if (!is_dir($storage) && !mkdir($storage,0700,true) && !is_dir($storage)) { http_response_code(503); exit('Storage unavailable'); }
$path=$storage.'/'.hash('sha256',(string)$user['id']).'.json';

function it_reply(int $status, bool $success, string $message, mixed $data=null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'=>$success,'message'=>$message,'data'=>$data,'errors'=>$success?[]:[['code'=>$status===422?'VALIDATION_FAILED':'REQUEST_FAILED','message'=>$message]],
        'meta'=>['timestamp'=>gmdate(DATE_ATOM),'requestId'=>bin2hex(random_bytes(6))],
    ],JSON_THROW_ON_ERROR);
    exit;
}
function it_records(string $path): array
{
    return \Jasr\Framework\JsonStore::read($path)['records'];
}
function it_active(array $records): array
{
    return array_values(array_filter($records,static fn($row)=>is_array($row) && empty($row['voided_at'])));
}
function it_csv_events(string $path): array
{
    if (!isset($_FILES['file']) || !is_array($_FILES['file']) || ($_FILES['file']['error']??1)!==UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Select a valid CSV file.');
    }
    $file=$_FILES['file'];
    if (($file['size']??0)<1 || $file['size']>2097152 || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('CSV must be 2 MB or smaller.');
    }
    $handle=fopen($file['tmp_name'],'rb');
    if (!$handle) throw new InvalidArgumentException('Unable to read uploaded file.');
    try {
        $header=fgetcsv($handle,0,',','"','');
        $expected=['date','ticker','type','quantity','amount','price','numerator','denominator','lot_id','note','external_id'];
        if (!is_array($header) || $header!==$expected) {
            throw new InvalidArgumentException('This is not the normalized tracker CSV. Use the documented template; Robinhood exports need an explicit mapping before import.');
        }
        $rows=[]; $ids=[]; $count=0;
        while (($cells=fgetcsv($handle,0,',','"',''))!==false) {
            if ($cells===[null]) continue;
            if (++$count>2000 || count($cells)!==count($expected)) {
                throw new InvalidArgumentException('CSV has too many rows or mismatched columns.');
            }
            $raw=array_combine($expected,$cells);
            foreach (['quantity','amount','price','numerator','denominator'] as $key) if ($raw[$key]==='') $raw[$key]='0';
            $id=trim($raw['external_id']);
            if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$id) || isset($ids[$id])) {
                throw new InvalidArgumentException('Every CSV row needs a unique simple external_id.');
            }
            $ids[$id]=true;
            $event=it_event($raw);
            $event['external_id']=$id;
            $rows[]=$event;
        }
        if (!$rows) throw new InvalidArgumentException('The CSV is empty.');
        return $rows;
    } finally {
        fclose($handle);
    }
}
function it_append_import(array $existing,array $new): array
{
    $allExternal=[]; $lotsByRef=[];
    foreach ($existing as $row) {
        if (isset($row['external_id']) && (string)$row['external_id']!=='') $allExternal[(string)$row['external_id']]=true;
        if (isset($row['external_id']) && (string)$row['external_id']!=='' && in_array($row['type'],['buy','reinvest'],true) && empty($row['voided_at'])) {
            $lotsByRef[(string)$row['external_id']]=$row['id'];
        }
    }
    foreach ($new as $e) {
        if (isset($allExternal[$e['external_id']])) {
            throw new InvalidArgumentException('CSV contains already-imported external_id '.$e['external_id'].'. Nothing was imported.');
        }
        $allExternal[$e['external_id']]=true;
        if (in_array($e['type'],['buy','reinvest'],true)) $lotsByRef[$e['external_id']]=$e['id'];
    }
    foreach ($new as &$e) {
        if ($e['type']==='sell') {
            $ref=$e['lot_id'];
            if (isset($lotsByRef[$ref])) $e['lot_id']=$lotsByRef[$ref];
        }
    }
    unset($e);
    $all=array_merge($existing,$new);
    if (count($all)>20000) throw new InvalidArgumentException('Ledger is at its 20,000 event limit.');
    it_calculate(it_active($all)); // Validate lot sales/chronology before committing.
    return $all;
}
if ($isApi) {
    try {
        if ($_SERVER['REQUEST_METHOD']==='GET') {
            $rows=$isDemo?it_demo_events():it_records($path);
            it_reply(200,true,$isDemo?'Read-only incomplete NVIDIA demonstration loaded.':'Investment ledger loaded.',[
                'events'=>$rows,'analysis'=>it_calculate(it_active($rows)),'csrf'=>$auth->csrf(),
                'as_of'=>gmdate('Y-m-d'),'demo'=>$isDemo,'demo_exclusions'=>$isDemo?it_demo_exclusions():[]
            ]);
        }
        if ($isDemo) it_reply(403,false,'Demo mode is read-only. Switch to My portfolio to save personal activity.');
        if ($_SERVER['REQUEST_METHOD']!=='POST') it_reply(405,false,'Method not allowed.');
        if (!$auth->validCsrf($_POST['csrf']??null)) it_reply(403,false,'Invalid form token.');
        $action=(string)($_POST['action']??'');
        if ($action==='preview_csv') {
            $new=it_csv_events($path);
            $existing=it_records($path);
            $merged=it_append_import($existing,$new);
            it_reply(200,true,'CSV validated. No data saved yet.',[
                'rows'=>count($new),'tickers'=>array_values(array_unique(array_column($new,'ticker'))),
                'preview'=>array_slice($new,0,5),
                'projected'=>it_calculate(it_active($merged))['totals'],
            ]);
        }
        if ($action==='import_csv') {
            $new=it_csv_events($path);
            $rows=\Jasr\Framework\JsonStore::update($path,static fn($old)=>it_append_import($old,$new));
            it_reply(200,true,count($new).' CSV rows imported.', ['count'=>count($rows)]);
        }
        if ($action==='add') {
            $raw=json_decode((string)($_POST['event']??''),true,512,JSON_THROW_ON_ERROR);
            if (!is_array($raw)) throw new InvalidArgumentException('Invalid event.');
            $event=it_event($raw);
            \Jasr\Framework\JsonStore::update($path,static function(array $old) use($event):array {
                if (count($old)>=20000) throw new InvalidArgumentException('Ledger event limit reached.');
                $old[]=$event; it_calculate(it_active($old)); return $old;
            });
            it_reply(200,true,'Event saved.', ['id'=>$event['id']]);
        }
        if ($action==='void') {
            $id=(string)($_POST['id']??'');
            if (!preg_match('/^[a-f0-9]{24}$/D',$id)) throw new InvalidArgumentException('Invalid event ID.');
            \Jasr\Framework\JsonStore::update($path,static function(array $old) use ($id):array {
                $found=false;
                foreach ($old as &$row) {
                    if (($row['id']??'')===$id && empty($row['voided_at'])) {
                        $row['voided_at']=gmdate(DATE_ATOM); $found=true; break;
                    }
                }
                unset($row);
                if (!$found) throw new InvalidArgumentException('Event not found or already voided.');
                it_calculate(it_active($old)); return $old;
            });
            it_reply(200,true,'Event voided. Original record retained for audit.');
        }
        it_reply(422,false,'Unsupported action.');
    } catch (InvalidArgumentException|JsonException $e) {
        it_reply(422,false,$e->getMessage());
    } catch (Throwable $e) {
        error_log('Investment Tracker API error: '.get_class($e));
        it_reply(500,false,'Unable to process request. Check server logs.');
    }
}
function it_h(string $text): string { return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Investment Tracker · Tax Lots</title>
<link rel="stylesheet" href="style.css?v=1.1.0">
<script src="app.js?v=1.1.0" defer></script>
</head>
<body data-demo="<?=$isDemo?'1':'0'?>">
<header class="topbar"><div><span class="eyebrow">PRIVATE PORTFOLIO ANALYTICS</span><h1>Investment Tracker</h1><p>See what each purchase is worth, not just the average.</p></div><a class="account" href="<?=it_h($auth->portal())?>">Account <span aria-hidden="true">↗</span></a></header>
<main>
<nav class="modes" aria-label="Portfolio data source"><a class="mode <?=$isDemo?'':'active'?>" href="?">My private portfolio</a><a class="mode <?=$isDemo?'active':''?>" href="?demo=1">NVIDIA screenshot demo</a></nav>
<?php if($isDemo): ?><section class="demoBanner"><strong>NVIDIA DEMO · INCOMPLETE SCREENSHOT HISTORY · READ ONLY</strong><p>Separate from your private account. The illustrative results exclude earlier sold lots and all reinvested dividends because their exact lot or fractional-share data has not yet been verified. The last reference price is the October 9, 2026 execution price — not today’s market price. <strong>Do not rely on these demo totals for a trade.</strong></p></section><?php endif; ?>
<div id="notice" class="notice" role="status" aria-live="polite">Loading private investment data…</div>
<section class="metrics" aria-label="Portfolio totals">
  <article class="metric"><span>Market value</span><strong id="marketValue">—</strong><small>Only complete when every holding has a price</small></article>
  <article class="metric"><span>Lifetime gain / loss</span><strong id="lifetimeProfit">—</strong><small>Sales + current holdings + cash dividends − cash purchases</small></article>
  <article class="metric"><span>Unrealized gain / loss</span><strong id="unrealized">—</strong><small>Open lots at last recorded price</small></article>
  <article class="metric"><span>Annualized money-weighted return</span><strong id="xirr">—</strong><small>XIRR with transaction dates and current market value</small></article>
</section>
<section class="charts">
  <article class="panel chart"><div class="panel-heading"><div><span class="eyebrow">MARKED-TO-MARKET HISTORY</span><h2>Position value vs. remaining basis</h2></div><label class="compact">Stock <select id="tickerSelect" aria-label="Chart stock"></select></label></div><div id="valueChart" class="chartbox"></div><p class="hint">Points appear only on recorded price dates; previous prices are not fabricated.</p></article>
  <article class="panel chart"><div class="panel-heading"><div><span class="eyebrow">LOT COMPARISON</span><h2>Unrealized gain by purchase</h2></div></div><div id="lotChart" class="chartbox"></div><p class="hint">Tap a lot below to see its individual growth history.</p></article>
</section>
<section class="panel"><div class="panel-heading"><div><span class="eyebrow">EVERY PURCHASE IS UNIQUE</span><h2>Open tax lots</h2></div><div id="lotSummary" class="subtle"></div></div><div class="tablewrap"><table><thead><tr><th>Purchased</th><th>Source</th><th class="numeric">Original shares</th><th class="numeric">Original $/share</th><th class="numeric">Split factor</th><th class="numeric">Shares now</th><th class="numeric">Adjusted $/share</th><th class="numeric">Basis remaining</th><th class="numeric">Market value</th><th class="numeric">Unrealized $</th><th class="numeric">Unrealized %</th><th>Held / tax period</th><th>Action</th></tr></thead><tbody id="lotsBody"></tbody></table></div><div id="selectedLot" class="lotDetail" hidden><div class="panel-heading"><h3 id="selectedName">Lot history</h3><button type="button" id="hideLot" class="ghost">Close</button></div><div id="lotFacts" class="lotFacts"></div><div id="lotHistory" class="chartbox"></div></div></section>
<section class="charts lower">
<article class="panel"><span class="eyebrow">WHAT-IF ONLY · NEVER PLACES AN ORDER</span><h2>Simulate a partial sale</h2>
 <form id="saleSimulator" class="formgrid"><label>Purchase lot<select id="saleLot" required></select></label><label>Shares to sell<input id="saleQty" type="number" min="0.00000001" step="any" required></label><label>Assumed sale price per share<input id="salePrice" type="number" min="0.00000001" step="any" required></label><button class="primary" type="submit">Calculate sale</button></form>
 <div id="saleResult" class="result">Select a lot and quantity to preview a sale without changing records.</div>
 <p class="hint">Shares held for MORE than one year are generally long-term; exactly one year is short-term. A split retains the original lot holding period, while each reinvestment gets its own purchase date. This is an estimate, not a tax determination.</p>
</article>
<article class="panel"><span class="eyebrow">PORTFOLIO DETAILS</span><h2>By ticker</h2><div id="holdings" class="holdings"></div><p class="hint">Cash dividends and reinvested dividends are distinguished to avoid double-counting cash flows.</p></article>
</section>
<section class="charts lower">
<article class="panel" data-private-write><span class="eyebrow">MANUAL ENTRY</span><h2>Add activity or market price</h2><form id="addEvent" class="formgrid">
<label>Stock ticker<input name="ticker" maxlength="14" placeholder="NVDA" required autocapitalize="characters"></label>
<label>Event<select name="type" id="eventType"><option value="buy">Buy shares</option><option value="sell">Sell selected lot</option><option value="dividend">Cash dividend</option><option value="reinvest">Dividend reinvestment</option><option value="split">Stock split</option><option value="price">Market price observation</option></select></label>
<label>Date<input name="date" id="eventDate" type="date" required></label>
<label data-fields="buy sell reinvest">Quantity of shares<input name="quantity" type="number" step="any" min="0" value="0"></label>
<label data-fields="buy sell reinvest dividend">Total dollars (buy cost / net sale / dividend)<input name="amount" type="number" step="any" min="0" value="0"></label>
<label data-fields="price">Market price per share<input name="price" type="number" step="any" min="0" value="0"></label>
<label data-fields="split">New shares (numerator)<input name="numerator" type="number" min="0" step="1" value="0"></label>
<label data-fields="split">Old shares (denominator)<input name="denominator" type="number" min="0" step="1" value="0"></label>
<label data-fields="sell">Sell from purchase<select id="manualLot" name="lot_id"></select></label>
<label class="full">Notes (optional)<input name="note" maxlength="240" placeholder="Broker confirmation / source"></label>
<button class="primary" type="submit">Save event</button></form><p class="hint">Buy total includes acquisition fees; sale total is net proceeds after selling fees. A reinvestment creates a new tax lot but does not count as new external cash invested.</p></article>
<article class="panel" data-private-write><span class="eyebrow">IMPORT / RECONCILE</span><h2>Bulk CSV import</h2><p>Use the normalized tracker format. A raw Robinhood report needs column and corporate-action mapping first; it is not blindly imported.</p>
<form id="csvForm"><label class="full">Normalized CSV file<input id="csvFile" name="file" type="file" accept=".csv,text/csv" required></label><div class="actions"><button type="button" id="previewCsv" class="secondary">Validate &amp; preview</button><button type="button" id="commitCsv" class="primary" disabled>Import validated file</button></div></form>
<div id="csvPreview" class="result">No import preview yet.</div><p class="hint">Download the <a href="sample.csv" download>sample CSV</a>. Every row has a unique external_id; sales reference the purchase's external_id in lot_id. All-or-nothing validation prevents partial imports or duplicate IDs.</p></article>
</section>
<section class="panel"><div class="panel-heading"><div><span class="eyebrow">IMMUTABLE HISTORY</span><h2>Activity ledger</h2></div><span id="recordCount" class="subtle"></span></div><div class="tablewrap"><table><thead><tr><th>Date</th><th>Stock</th><th>Type</th><th class="numeric">Shares</th><th class="numeric">Amount / price</th><th>Notes</th><th>Correction</th></tr></thead><tbody id="eventsBody"></tbody></table></div><div class="actions"><button type="button" class="secondary" id="newerEvents" disabled>Newer events</button><span id="eventPage" class="subtle" aria-live="polite"></span><button type="button" class="secondary" id="olderEvents" disabled>Older events</button></div><p class="hint">Voiding retains the original record for audit. Voiding a purchase that has allocated sales is blocked until its dependencies are corrected.</p></section>
</main>
<footer>Investment Tracker · Revision 1.1.0 · Updated October 29, 2026 · Data private to your central account · Calculations are estimates, not trading or tax advice</footer>
</body></html>
