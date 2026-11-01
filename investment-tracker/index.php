<?php
/**
 * File Name: index.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Private investment tax-lot dashboard and authenticated ledger API.
 * Revision: 1.5.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-30
 * Changelog: 1.5.0 - Enable preview-first, atomic batch import of all eligible stocks with explicit exception summary. 1.4.2 - Put estimated import first and move manual verification behind advanced details. 1.4.0 - Allow explicitly provisional one-click import using reported activity, inferred splits and FIFO; preserve unknown share basis. 1.3.2 - Offer explicitly unverified FIFO lot suggestions for review. 1.3.1 - Serialize stage and review edits with ticker commit to avoid concurrent draft mutation. 1.3.0 - Private draft creation, editable per-stock split and sale-lot reviews, validated atomic import. 1.2.1 - Clarify original Robinhood CSV versus normalized data, and guide misdirected uploads to safe preview. 1.2.0 - Preview Robinhood source reports without writing unverified lots; protect asset history from accidental import. 1.1.0 - Read-only NVIDIA demo, original and split-adjusted lot display, tax-term clarity. 1.0.0 - Shared login, manual events, atomic CSV import and lot dashboard.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/demo.php';
require_once __DIR__ . '/robinhood.php';
require_once __DIR__ . '/reconciliation.php';
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
$reconcilePath=$storage.'/'.hash('sha256',(string)$user['id']).'.rh-stage.json';

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
        if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['reconciliation'])) {
            $draft=\Jasr\Framework\JsonStore::read($reconcilePath)['records'][0]??null;
            if(!$draft)it_reply(200,true,'No Robinhood draft has been staged.', ['exists'=>false]);
            $ticker=isset($_GET['ticker'])?strtoupper(trim((string)$_GET['ticker'])):null;
            $view=it_rc_view($draft,$ticker);
            $view['exists']=true;
            if($ticker!==null && isset($draft['reviews'][$ticker]) && !isset($draft['completed'][$ticker])) {
                $check=it_rc_build($draft,$ticker);
                unset($check['events']);
                $view['validation']=$check;
            }
            it_reply(200,true,'Private reconciliation draft loaded.', $view);
        }
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
        if ($action==='stage_robinhood') {
            if (!isset($_FILES['file']) || ($_FILES['file']['error']??1)!==UPLOAD_ERR_OK ||
                !is_uploaded_file((string)$_FILES['file']['tmp_name'])) {
                throw new InvalidArgumentException('Upload the original Robinhood activity CSV.');
            }
            $draft=it_rc_stage((string)$_FILES['file']['tmp_name']);
            $lock=fopen($reconcilePath.'.work.lock','c');
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Review lock unavailable.');
            try {
                $records=\Jasr\Framework\JsonStore::update($reconcilePath,static fn(array $old):array=>[$draft]);
            } finally {flock($lock,LOCK_UN);fclose($lock);}
            it_reply(200,true,'Private draft staged. No portfolio records have been imported.',
                it_rc_view($records[0]));
        }
        if ($action==='save_reconciliation') {
            $raw=$_POST['review']??'';
            if(!is_string($raw)||strlen($raw)>100000)throw new InvalidArgumentException('Invalid review size.');
            $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new InvalidArgumentException('Invalid review request.');
            $expected=(string)($data['revision']??'');
            $ticker=strtoupper(trim((string)($data['ticker']??'')));
            $lock=fopen($reconcilePath.'.work.lock','c');
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Review lock unavailable.');
            try {
                $updated=\Jasr\Framework\JsonStore::update($reconcilePath,static function(array $records) use($expected,$ticker,$data):array {
                    $draft=$records[0]??null;
                    if(!$draft || !hash_equals(it_rc_revision($draft),$expected)) {
                        throw new InvalidArgumentException('Draft changed in another tab; refresh and review again.');
                    }
                    return [it_rc_review($draft,$ticker,$data)];
                });
            } finally {flock($lock,LOCK_UN);fclose($lock);}
            $view=it_rc_view($updated[0],$ticker);
            $validation=it_rc_build($updated[0],$ticker);
            unset($validation['events']);$view['validation']=$validation;
            it_reply(200,true,$validation['ready']?'Review validated. Ledger not yet changed.':'Corrections saved; more review required.',$view);
        }
        if ($action==='preview_auto_import_all') {
            $draft=\Jasr\Framework\JsonStore::read($reconcilePath)['records'][0]??null;
            if(!$draft)throw new InvalidArgumentException('Stage the original Robinhood CSV first.');
            $rows=it_records($path);
            $batch=it_rc_batch_preview($draft,$rows);
            $public=it_rc_batch_public($batch,$draft,$rows);
            it_reply(200,true,'Preview complete. No account records have been imported.',$public);
        }
        if ($action==='commit_auto_import_all') {
            $provided=(string)($_POST['fingerprint']??'');
            if(!preg_match('/^[a-f0-9]{64}$/D',$provided))
                throw new InvalidArgumentException('Preview the entire report before importing.');
            $lock=fopen($reconcilePath.'.work.lock','c');
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Review lock unavailable.');
            try {
                $draft=\Jasr\Framework\JsonStore::read($reconcilePath)['records'][0]??null;
                if(!$draft)throw new InvalidArgumentException('Staged report missing.');
                $imported=null;
                $after=\Jasr\Framework\JsonStore::update($path,static function(array $old)use($draft,$provided,&$imported):array{
                    $batch=it_rc_batch_preview($draft,$old);
                    $public=it_rc_batch_public($batch,$draft,$old);
                    if(!hash_equals($public['fingerprint'],$provided))
                        throw new InvalidArgumentException('Portfolio or report changed after preview. Preview again to avoid duplicate records.');
                    if(!$batch['ready']||!$batch['events'])
                        throw new InvalidArgumentException('No eligible stocks are ready for import.');
                    $imported=$public;
                    return it_append_import($old,$batch['events']);
                });
                \Jasr\Framework\JsonStore::update($reconcilePath,static function(array $records)use($imported):array{
                    if(!isset($records[0]))throw new RuntimeException('Staged report missing after portfolio import.');
                    foreach($imported['eligible'] as $symbol) {
                        $records[0]['completed'][$symbol['ticker']]=[
                            'at'=>gmdate(DATE_ATOM),'events'=>$symbol['events'],
                            'method'=>'batch_provisional_fifo',
                            'warnings'=>$symbol['warnings'],
                        ];
                    }
                    return $records;
                });
                it_reply(200,true,'Eligible stocks imported with provisional tax-lot attribution; unsupported symbols remain untouched.',[
                    'imported_symbols'=>array_column($imported['eligible'],'ticker'),
                    'stocks'=>count($imported['eligible']),
                    'events'=>$imported['import_count'],
                    'blocked'=>$imported['blocked'],'skipped'=>$imported['skipped'],
                    'ledger_events'=>count($after),'estimated'=>true
                ]);
            } finally {flock($lock,LOCK_UN);fclose($lock);}
        }
        if ($action==='auto_import_stock') {
            $ticker=strtoupper(trim((string)($_POST['ticker']??'')));
            $expected=(string)($_POST['revision']??'');
            if(!preg_match('/^[A-Z0-9.^-]{1,14}$/D',$ticker))
                throw new InvalidArgumentException('Select a valid ticker.');
            $lock=fopen($reconcilePath.'.work.lock','c');
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Review lock unavailable.');
            try {
                $draft=\Jasr\Framework\JsonStore::read($reconcilePath)['records'][0]??null;
                if(!$draft || !hash_equals(it_rc_revision($draft),$expected))
                    throw new InvalidArgumentException('Draft changed; refresh before importing.');
                $auto=it_rc_auto_review($draft,$ticker);
                if(!$auto['ready'])
                    throw new InvalidArgumentException('Cannot safely import this stock: '.implode(' ',array_slice($auto['issues'],0,4)));
                $events=$auto['events'];
                $after=\Jasr\Framework\JsonStore::update($path,static function(array $old)use($ticker,$events):array{
                    foreach(it_active($old) as $event)
                        if(($event['ticker']??'')===$ticker)
                            throw new InvalidArgumentException('This ticker already has ledger activity; no duplicate import performed.');
                    return it_append_import($old,$events);
                });
                \Jasr\Framework\JsonStore::update($reconcilePath,static function(array $records)use($ticker,$events,$auto):array{
                    if(!isset($records[0]))throw new RuntimeException('Missing staged report after import.');
                    $records[0]['completed'][$ticker]=[
                        'at'=>gmdate(DATE_ATOM),'events'=>count($events),
                        'method'=>'provisional_fifo','warnings'=>$auto['warnings'],
                    ];
                    return $records;
                });
                it_reply(200,true,'Provisional brokerage activity imported; tax basis and elected lots NOT verified.',[
                    'ticker'=>$ticker,'imported'=>count($events),'warnings'=>$auto['warnings'],
                    'total_events'=>count($after),'estimated'=>true
                ]);
            } finally {flock($lock,LOCK_UN);fclose($lock);}
        }
        if ($action==='commit_reconciliation') {
            $ticker=strtoupper(trim((string)($_POST['ticker']??'')));
            $expected=(string)($_POST['revision']??'');
            if(!preg_match('/^[A-Z0-9.^-]{1,14}$/D',$ticker))throw new InvalidArgumentException('Invalid ticker.');
            $lock=fopen($reconcilePath.'.work.lock','c');
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Review lock unavailable.');
            try {
                $draft=\Jasr\Framework\JsonStore::read($reconcilePath)['records'][0]??null;
                if(!$draft || !hash_equals(it_rc_revision($draft),$expected)) {
                    throw new InvalidArgumentException('Reconciliation changed; refresh before saving.');
                }
                if(isset($draft['completed'][$ticker]))throw new InvalidArgumentException('This ticker has already been imported.');
                $built=it_rc_build($draft,$ticker);
                if(!$built['ready'])throw new InvalidArgumentException('Unresolved items remain: '.implode(' ',array_slice($built['issues'],0,3)));
                $new=$built['events'];
                $after=\Jasr\Framework\JsonStore::update($path,static function(array $old)use($ticker,$new):array {
                    foreach(it_active($old) as $e) {
                        if(($e['ticker']??'')===$ticker)
                            throw new InvalidArgumentException('This ticker already has personal ledger events. Reconcile duplicates before importing.');
                    }
                    return it_append_import($old,$new);
                });
                \Jasr\Framework\JsonStore::update($reconcilePath,static function(array $records)use($ticker,$new):array {
                    if(!isset($records[0]))throw new RuntimeException('Reconciliation draft missing after import.');
                    $records[0]['completed'][$ticker]=['at'=>gmdate(DATE_ATOM),'events'=>count($new)];
                    return $records;
                });
                it_reply(200,true,'Validated '.$ticker.' ledger events imported for this user only.',
                    ['ticker'=>$ticker,'imported'=>count($new),'total_events'=>count($after)]);
            } finally {
                flock($lock,LOCK_UN);fclose($lock);
            }
        }
        if ($action==='preview_robinhood') {
            if(!isset($_FILES['file']) || ($_FILES['file']['error']??1)!==UPLOAD_ERR_OK ||
                !is_uploaded_file((string)$_FILES['file']['tmp_name'])) {
                throw new InvalidArgumentException('Select a valid Robinhood account activity CSV.');
            }
            $summary=it_rh_parse_csv((string)$_FILES['file']['tmp_name']);
            it_reply(200,true,'Robinhood activity analyzed. No financial data was saved.', $summary);
        }
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
<link rel="stylesheet" href="style.css?v=1.5.0">
<script src="app.js?v=1.5.0" defer></script>
</head>
<body data-demo="<?=$isDemo?'1':'0'?>">
<header class="topbar"><div><span class="eyebrow">PRIVATE PORTFOLIO ANALYTICS</span><h1>Investment Tracker</h1><p>See what each purchase is worth, not just the average.</p></div><a class="account" href="<?=it_h($auth->portal())?>">Account <span aria-hidden="true">↗</span></a></header>
<main>
<nav class="modes" aria-label="Portfolio data source"><a class="mode <?=$isDemo?'':'active'?>" href="?">My private portfolio</a><a class="mode <?=$isDemo?'active':''?>" href="?demo=1">NVIDIA screenshot demo</a></nav>
<?php if($isDemo): ?><section class="demoBanner"><strong>NVIDIA DEMO · INCOMPLETE SCREENSHOT HISTORY · READ ONLY</strong><p>Separate from your private account. The export reconciles NVIDIA shares and fractional dividend reinvestments. Historical sale-lot assignments are a provisional illustration, and the last reference price is the October 9, 2026 execution price — not today’s market quote. <strong>Do not treat historical demonstration valuations as live trading prices or broker-confirmed tax lots.</strong></p></section><?php endif; ?>
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
<article class="panel" data-private-write id="robinhood-upload"><span class="eyebrow">ROBINHOOD DIRECT DOWNLOAD</span><h2>Analyze original Robinhood CSV</h2>
 <p>Upload the original Robinhood Account activity CSV to analyze purchases, sales, dividends, split credits, and account events. The report is evaluated for exceptions, <strong>not automatically imported</strong> because sale tax-lot elections and some corporate actions are not stated in the CSV.</p>
 <form id="rhForm"><label>Robinhood Account activity report (.csv) — no conversion needed<input id="rhFile" type="file" accept=".csv,text/csv" required></label><div class="actions"><button type="submit" class="secondary">Analyze original CSV</button><button type="button" class="primary" id="stageRh">Create private import draft</button></div></form>
 <p class="hint">Creating a new draft replaces any prior uncommitted review work for this user. It does not replace or delete already imported holdings.</p>
 <div id="rhPreview" class="result">Select the original downloaded CSV to inspect trade types and reconciliation issues. Nothing is saved.</div>
 <div id="rhTickers" class="rhTickers"></div>
 <section id="reconciliation" class="reconcile"><div class="panel-heading"><div><span class="eyebrow">PRIVATE · PER-TICKER REVIEW</span><h3>Import your Robinhood history</h3></div><span id="rhDraftStatus" class="subtle">No draft selected</span></div>
  <p class="hint">Choose a stock and import its reported activity. The tracker can infer common stock splits and estimate sale allocations using FIFO. No extra brokerage verification is required for an <strong>estimated</strong> history. Tax-basis gaps are identified rather than guessed. For tax-ready results, advanced manual review remains available below.</p>
  <div class="rhBatchActions">
    <button id="rhPreviewAll" class="secondary" type="button" disabled>Preview import of all stocks</button>
    <button id="rhCommitAll" class="primary" type="button" disabled>Import eligible stocks</button>
  </div>
  <div id="rhBatchStatus" class="result" aria-live="polite">Stage your unmodified Robinhood report, then preview all stocks. No account data is changed during preview.</div>
  <p class="hint">One import can process every supported stock. Any exceptions are listed with their original transaction codes; other symbols are not held back. Past sale allocations remain estimated, not broker-certified tax lots.</p>
  <div class="formgrid">
   <label>Select stock<select id="rhDraftTicker"><option value="">Select stock</option></select></label>
   <label>Broker shares as of report end<input id="rhExpectedShares" type="number" min="0" step="any" placeholder="e.g. 1.736881"></label>
  </div>
  <div class="actions" id="rhAutomaticActions"><button id="rhAutoImport" class="primary" type="button" disabled>Import selected stock as estimated</button></div>
  <p class="hint">Estimated imports use the CSV as supplied; sale purchase-lot assignments are assumed FIFO, not verified broker elections. Shares with unknown cost basis show no taxable gain estimate. Some complex actions may still prevent an import.</p>
  <details class="rhAdvanced"><summary>Advanced: verify tax lots and correct corporate actions</summary>
  <div id="rhReviewLines" class="rhReviewLines"></div>
  <label class="rhConfirm"><input id="rhConfirm" type="checkbox"> I verified purchases, split ratios, the broker's selected sale tax lots, and current shares against my records. An activity report alone does not establish tax-lot elections.</label>
  <div class="actions"><button id="rhSuggestFifo" class="ghost" type="button" disabled>Suggest FIFO allocations (unverified)</button><button id="rhSaveReview" class="secondary" type="button" disabled>Save corrections &amp; validate</button><button id="rhCommit" class="primary" type="button" disabled>Import this verified stock</button></div>
  <div id="rhValidation" class="result" aria-live="polite">Upload and stage a Robinhood report to start reviewing individual stocks.</div></details>
 </section>
 <p class="hint">Do not commit personal brokerage exports to this public repository. This analysis happens on the authenticated server request; this version does not persist the uploaded source or automatically assign sale lots.</p>
</article>
<article class="panel" data-private-write><span class="eyebrow">ADVANCED · INTERNAL TRACKER FORMAT</span><h2>Import normalized tracker CSV</h2><p><strong>This is not for Robinhood downloads.</strong> For the CSV exported directly from Robinhood, use <a href="#robinhood-upload">Analyze original Robinhood CSV</a> above. The internal import below requires mapped purchase lots, splits, dividends, and a normalized file.</p>
<form id="csvForm"><label class="full">Normalized tracker CSV only<input id="csvFile" name="file" type="file" accept=".csv,text/csv" required></label><div class="actions"><button type="button" id="previewCsv" class="secondary">Validate &amp; preview</button><button type="button" id="commitCsv" class="primary" disabled>Import validated file</button></div></form>
<div id="csvPreview" class="result">No import preview yet.</div><p class="hint">Download the <a href="sample.csv" download>sample CSV</a>. Every row has a unique external_id; sales reference the purchase's external_id in lot_id. All-or-nothing validation prevents partial imports or duplicate IDs.</p></article>
</section>
<section class="panel"><div class="panel-heading"><div><span class="eyebrow">IMMUTABLE HISTORY</span><h2>Activity ledger</h2></div><span id="recordCount" class="subtle"></span></div><div class="tablewrap"><table><thead><tr><th>Date</th><th>Stock</th><th>Type</th><th class="numeric">Shares</th><th class="numeric">Amount / price</th><th>Notes</th><th>Correction</th></tr></thead><tbody id="eventsBody"></tbody></table></div><div class="actions"><button type="button" class="secondary" id="newerEvents" disabled>Newer events</button><span id="eventPage" class="subtle" aria-live="polite"></span><button type="button" class="secondary" id="olderEvents" disabled>Older events</button></div><p class="hint">Voiding retains the original record for audit. Voiding a purchase that has allocated sales is blocked until its dependencies are corrected.</p></section>
</main>
<footer>Investment Tracker · Revision 1.5.0 · Updated October 29, 2026 · Data private to your central account · Calculations are estimates, not trading or tax advice</footer>
</body></html>
