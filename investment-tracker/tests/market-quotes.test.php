<?php
/**
 * File Name: market-quotes.test.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Synthetic quote parser, credits, caching and immutable-ledger valuation regressions.
 * Revision: 1.0.2
 * Created Date: 2026-11-22
 * Modified Date: 2026-11-22
 * Changelog: 1.0.2 - Prevent quote starvation with 64 open ticker batch simulation. 1.0.1 - Verify same-day chart refresh and last_quote_at timestamp priority. 1.0.0 - Test current quotes without purchase/basis changes, API limits, stale quotes, missing data and disabled integration.
 */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/1-Framework/bootstrap.php';
require_once dirname(__DIR__).'/ledger.php';
require_once dirname(__DIR__).'/market-quotes.php';
function must_quote(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException('FAIL: '.$message);
    echo "PASS: $message\n";
}
$now=time();
$fakeQuote=static fn(string $s,float $price,int $timestamp):array=>[
    'symbol'=>$s,'currency'=>'USD','close'=>(string)$price,'timestamp'=>$timestamp,'is_market_open'=>false
];
$a=it_mq_parse_quote($fakeQuote('ABCD',16.50,$now-15),'ABCD',$now);
must_quote($a!==null && abs($a['price']-16.50)<1e-10 &&
    $a['source']==='Twelve Data' && $a['currency']==='USD',
    'Valid US stock quote preserves provider timestamp and USD currency.');
must_quote(it_mq_parse_quote($fakeQuote('WRONG',10,$now),'ABCD',$now)===null,
    'Wrong-ticker API response is never misapplied.');
must_quote(it_mq_parse_quote(['symbol'=>'ABCD','currency'=>'CAD','close'=>'12','timestamp'=>$now],
    'ABCD',$now)===null,'Non-USD quote cannot silently misvalue a USD portfolio.');
must_quote(it_mq_parse_quote($fakeQuote('ABCD',0,$now),'ABCD',$now)===null &&
    it_mq_parse_quote($fakeQuote('ABCD',-1,$now),'ABCD',$now)===null &&
    it_mq_parse_quote($fakeQuote('ABCD',10,$now-8*86400),'ABCD',$now)===null &&
    it_mq_parse_quote($fakeQuote('ABCD',10,$now+1000),'ABCD',$now)===null,
    'Zero, negative, ancient and future quotes are rejected.');
must_quote(it_mq_parse_response('not json',['ABCD'],$now)===[] &&
    it_mq_parse_response('{"status":"error","code":429}', ['ABCD'],$now)===[],
    'Provider failures never produce an invented market price.');
$multi=['ABCD'=>$fakeQuote('ABCD',16.5,$now),'EFGH'=>$fakeQuote('EFGH',22,$now)];
must_quote(count(it_mq_parse_response(json_encode($multi),['ABCD','EFGH'],$now))===2,
    'Batch quotes are mapped to their own tickers.');
$single=it_mq_parse_response(json_encode($fakeQuote('ABCD',16.5,$now)),['ABCD'],$now);
must_quote(isset($single['ABCD']),'Single-ticker response shape is supported.');
$precise=$fakeQuote('ABCD',12.34,$now-3600);
$precise['last_quote_at']=$now-12;
$exact=it_mq_parse_quote($precise,'ABCD',$now);
must_quote($exact!==null && $exact['as_of']===gmdate(DATE_ATOM,$now-12),
    'Latest exchange quote time takes precedence over quote-bar opening time.');

$root=sys_get_temp_dir().'/investment-quotes-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);$cache=$root.'/market-quotes.json';
$config=['enabled'=>true,'api_key'=>'TEST-KEY-NOT-REAL',
 'credits_per_minute'=>8,'credits_per_day'=>12,
 'cache_seconds'=>1800,'stale_seconds'=>259200,'max_quote_age_seconds'=>604800];
$symbolList=['ABCD','EFGH','IJKL','MNOP','QRST','UVWX','YZAB','CDEF','GHIJ','KLMN','OPQR','STUV'];
$calls=[];$fetch=static function(array $symbols,string $key)use(&$calls,$now,$fakeQuote):string{
    must_quote($key==='TEST-KEY-NOT-REAL','Provider is passed a server-only key (never a browser field).');
    $calls[]=$symbols;
    $raw=[];
    foreach($symbols as $symbol)$raw[$symbol]=$fakeQuote($symbol,10.25,$now);
    return json_encode($raw,JSON_THROW_ON_ERROR);
};
try{
 $first=it_mq_refresh($cache,$symbolList,$config,false,$fetch,$now);
 must_quote($first['attempted']===8 && $first['updated']===8 && count($calls)===1,
    'First refresh requests at most eight symbols / provider credits.');
 $same=it_mq_refresh($cache,$symbolList,$config,false,$fetch,$now+15);
 must_quote($same['status']==='rate_limited' && count($calls)===1,
    'Repeated requests inside one minute cannot drain API credits.');
 $later=it_mq_refresh($cache,$symbolList,$config,false,$fetch,$now+61);
 must_quote($later['attempted']===4 && $later['updated']===4,
    'Second available minute completes a twelve-symbol portfolio.');
 $limit=it_mq_refresh($cache,$symbolList,$config,true,$fetch,$now+400);
 must_quote($limit['status']==='rate_limited' && count($calls)===2,
    'Daily credit cap refuses forced/manual refreshes after quota is exhausted.');
 $saved=\Jasr\Framework\JsonStore::read($cache)['records'][0];
 must_quote(count($saved['quotes'])===12 && count($saved['usage'])===2 &&
    !str_contains(json_encode($saved),'TEST-KEY-NOT-REAL'),
    'Shared quote cache never stores API keys or user purchase data.');
 $snapshot=it_mq_snapshot($cache,['ABCD','EFGH'], $config,$now+61);
 must_quote(count($snapshot)===2 && $snapshot['ABCD']['stale']===false,
    'Cache only returns authorized requested open symbols.');
 $oldSnap=it_mq_snapshot($cache,['ABCD'],$config,$now+259201);
 must_quote($oldSnap['ABCD']['stale']===true,
    'Market closure / stale quotes are clearly flagged.');
 $expired=it_mq_snapshot($cache,['ABCD'],$config,$now+604801);
 must_quote($expired===[],'Seven-day-old quotes cannot override newer transactions.');
 $off=$config;$off['enabled']=false;$off['api_key']='';
 $notConfigured=it_mq_refresh($cache,$symbolList,$off,false,$fetch,$now);
 must_quote($notConfigured['status']==='not_configured' && count($calls)===2,
    'Without a configured API key, the server never contacts a quote provider.');
 // At 30m the first tickers are due again. Without a fair/oldest queue,
 // an 8-credit batch every 5m would starve symbols past the first 48.
 $larger=$root.'/large.json';$largeConfig=$config;$largeConfig['credits_per_day']=700;
 $many=[];
 for($i=0;$i<64;$i++)$many[]=sprintf('Q%03d',$i);
 $largeCalls=[];
 $manyFetcher=static function(array $symbols,string $key)use(&$largeCalls,$now,$fakeQuote):string{
     $largeCalls[]=$symbols;
     $items=[];foreach($symbols as $ticker)$items[$ticker]=$fakeQuote($ticker,12.50,$now);
     return json_encode($items,JSON_THROW_ON_ERROR);
 };
 for($step=0;$step<8;$step++)
     it_mq_refresh($larger,$many,$largeConfig,false,$manyFetcher,$now+$step*300);
 $all=\Jasr\Framework\JsonStore::read($larger)['records'][0]['quotes'];
 must_quote(count($all)===64 && count($largeCalls)===8 &&
     isset($all['Q000'],$all['Q063']),
     'Fair queue refreshes every stock in a large portfolio before repeating cached tickers.');
}finally {
 foreach(glob($root.'/*')?:[] as $f)if(is_file($f))unlink($f);
 rmdir($root);
}

$older=gmdate('Y-m-d', $now-40*86400);
$recent=gmdate('Y-m-d', $now-2*86400);
$events=[
    it_event(['date'=>$older,'ticker'=>'ABCD','type'=>'buy','quantity'=>5,'amount'=>40]),
    it_event(['date'=>$recent,'ticker'=>'ABCD','type'=>'price','price'=>9]),
    it_event(['date'=>$older,'ticker'=>'CLOSED','type'=>'buy','quantity'=>1,'amount'=>5]),
];
$one=it_event(['date'=>$recent,'ticker'=>'CLOSED','type'=>'sell','quantity'=>1,'amount'=>8,'lot_id'=>$events[2]['id']]);
$events[]=$one;
$original=json_encode($events,JSON_THROW_ON_ERROR);
$before=it_calculate($events);
must_quote(abs($before['holdings']['ABCD']['value']-45)<1e-10 &&
    abs($before['holdings']['ABCD']['unrealized']-5)<1e-10,
    'Existing historical observation still calculates original gains.');
$nowIso=gmdate(DATE_ATOM,$now);
$up=it_calculate($events,[
  'ABCD'=>[
   'price'=>12.5,'date'=>gmdate('Y-m-d',$now),'as_of'=>$nowIso,
   'fetched_at'=>$nowIso,'source'=>'Twelve Data','currency'=>'USD','stale'=>false,
  ],
  'CLOSED'=>[
   'price'=>100000,'as_of'=>$nowIso,'source'=>'Twelve Data',
   'currency'=>'USD','stale'=>false,
  ],
]);
must_quote(abs($up['holdings']['ABCD']['value']-62.5)<1e-10 &&
    abs($up['holdings']['ABCD']['unrealized']-22.5)<1e-10 &&
    abs($up['holdings']['ABCD']['basis']-40)<1e-10 &&
    $up['holdings']['ABCD']['price_source']==='Twelve Data',
    'Quote updates holdings and unrealized gain while purchase basis is unchanged.');
must_quote($up['holdings']['ABCD']['price_as_of']===$nowIso &&
    $up['holdings']['ABCD']['price_stale']===false,
    'Provider timestamp is displayed for every open holding.');
must_quote(abs($up['holdings']['CLOSED']['value'])<1e-10 &&
    abs($up['holdings']['CLOSED']['realized']-3)<1e-10 &&
    $up['holdings']['CLOSED']['closed'],
    'Closed holdings are not repriced and keep historic realized profit.');
must_quote(abs($up['totals']['market_value']-62.5)<1e-10 &&
    $up['totals']['xirr_pct']!==$before['totals']['xirr_pct'],
    'Portfolio totals and XIRR respond to the latest accepted quote.');
must_quote(json_encode($events,JSON_THROW_ON_ERROR)===$original,
    'Revaluation did not modify or append any original ledger transaction event.');
must_quote(count($up['series']['ABCD'])===count($before['series']['ABCD'])+1,
    'Current quote extends the temporary chart only, not permanent history.');
$recentSameDay=it_event(['date'=>gmdate('Y-m-d',$now),'ticker'=>'ABCD','type'=>'price','price'=>10]);
$eventsSameDay=array_merge($events,[$recentSameDay]);
$sameDayBase=it_calculate($eventsSameDay);
$sameDayOverlay=it_calculate($eventsSameDay,['ABCD'=>[
    'price'=>12.5,'as_of'=>$nowIso,'source'=>'Twelve Data',
    'currency'=>'USD','stale'=>false,
]]);
must_quote(count($sameDayBase['series']['ABCD'])===count($sameDayOverlay['series']['ABCD']) &&
    abs($sameDayOverlay['series']['ABCD'][count($sameDayOverlay['series']['ABCD'])-1]['price']-12.5)<1e-9,
    'Same-day provider quote replaces the temporary chart endpoint without inserting extra event rows.');
$oldOverlay=it_calculate($events,['ABCD'=>[
    'price'=>2,'as_of'=>gmdate(DATE_ATOM,$now-12*86400),
    'source'=>'Twelve Data','currency'=>'USD',
]]);
must_quote(abs($oldOverlay['holdings']['ABCD']['value']-45)<1e-10,
    'Provider quote older than a saved price observation never replaces it.');
$badOverlay=it_calculate($events,['ABCD'=>[
    'price'=>7,'as_of'=>gmdate(DATE_ATOM,$now+5000),
    'source'=>'Twelve Data','currency'=>'USD',
]]);
must_quote(abs($badOverlay['holdings']['ABCD']['value']-45)<1e-10,
    'Future-valued quote is ignored and existing price stays intact.');
$staleOverlay=it_calculate($events,['ABCD'=>[
    'price'=>10,'as_of'=>$nowIso,'source'=>'Twelve Data',
    'currency'=>'USD','stale'=>true,
]]);
must_quote($staleOverlay['holdings']['ABCD']['price_stale']===true,
    'Marked-stale provider quote remains visibly stale.');
echo "All market-quote integration tests passed.\n";
