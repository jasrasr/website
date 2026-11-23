<?php
/**
 * File Name: market-quotes.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Private authenticated, globally throttled Twelve Data stock quote cache; independent of the immutable transaction ledger.
 * Revision: 1.0.0
 * Created Date: 2026-11-22
 * Modified Date: 2026-11-22
 * Changelog: 1.0.0 - Add server-only Twelve Data quote retrieval with strict validation, freshness metadata, throttling and atomic shared caching.
 */
declare(strict_types=1);

/**
 * Expects framework JsonStore from the application's existing bootstrap.
 * Never returns an API key, sends quantities/balances to an external provider,
 * or creates new investment transaction records.
 */
function it_mq_config(string $dir): array
{
    $path=rtrim($dir,'/').'/config.local.php';
    $raw=is_file($path)?require $path:[];
    if(!is_array($raw))throw new RuntimeException('Invalid market data settings.');
    $key=trim((string)($raw['twelve_data_api_key']??''));
    return [
        'api_key'=>$key,
        'enabled'=>$key!=='',
        // Basic plan at time of implementation: 8 credits/minute, 800/day.
        // Intentionally reserve headroom rather than consume provider limits.
        'credits_per_minute'=>max(1,min(8,(int)($raw['market_quote_credits_per_minute']??8))),
        'credits_per_day'=>max(1,min(760,(int)($raw['market_quote_credits_per_day']??700))),
        'cache_seconds'=>max(300,min(86400,(int)($raw['market_quote_cache_seconds']??1800))),
        'stale_seconds'=>max(86400,min(604800,(int)($raw['market_quote_stale_seconds']??259200))),
        'max_quote_age_seconds'=>604800,
    ];
}
function it_mq_tickers(array $analysis): array
{
    $symbols=[];
    foreach(($analysis['holdings']??[]) as $ticker=>$holding) {
        if(!is_array($holding) || (float)($holding['shares']??0)<=0.000000001)continue;
        $ticker=strtoupper((string)$ticker);
        if(preg_match('/^[A-Z][A-Z0-9.^-]{0,13}$/D',$ticker))$symbols[$ticker]=true;
    }
    $out=array_keys($symbols);sort($out);return $out;
}
/**
 * Twelve Data quote may represent a latest regular-session price. Its
 * "timestamp" is an exchange quote timestamp, NOT time of our API request.
 * Reject missing dates, invalid/non-USD currency, and future/ancient quotes.
 */
function it_mq_parse_quote(array $raw,string $ticker,int $now): ?array
{
    if(($raw['status']??'')==='error' ||
       strtoupper((string)($raw['symbol']??''))!==$ticker ||
       strtoupper((string)($raw['currency']??''))!=='USD')return null;
    $value=$raw['close']??null;
    $ts=$raw['timestamp']??null;
    if(!is_numeric($value)||!is_numeric($ts))return null;
    $value=(float)$value;$ts=(int)$ts;
    if(!is_finite($value)||$value<=0 || $value>1000000000 ||
       $ts<946684800 || $ts>$now+300 || $now-$ts>604800)return null;
    return [
        'price'=>$value,
        'date'=>gmdate('Y-m-d',$ts),
        'as_of'=>gmdate(DATE_ATOM,$ts),
        'fetched_at'=>gmdate(DATE_ATOM,$now),
        'source'=>'Twelve Data',
        'currency'=>'USD',
        'market_open'=>isset($raw['is_market_open'])&&is_bool($raw['is_market_open'])?$raw['is_market_open']:null,
    ];
}
function it_mq_parse_response(string $json,array $symbols,int $now): array
{
    try{$data=json_decode($json,true,32,JSON_THROW_ON_ERROR);}
    catch(JsonException){return [];}
    if(!is_array($data)||($data['status']??'')==='error')return [];
    $result=[];$single=count($symbols)===1 && isset($data['symbol']);
    foreach($symbols as $ticker) {
        $quote=$single?$data:($data[$ticker]??null);
        if(!is_array($quote))continue;
        $parsed=it_mq_parse_quote($quote,$ticker,$now);
        if($parsed!==null)$result[$ticker]=$parsed;
    }
    return $result;
}
/**
 * The URL is a fixed HTTPS endpoint; symbols are validated and encoded.
 * Authorization is sent in an HTTP header so keys do not appear in URLs,
 * browser devtools, referrers, or query-string server logs.
 */
function it_mq_http_batch(array $symbols,string $apiKey): string
{
    if(!$symbols || count($symbols)>8 || !function_exists('curl_init'))
        throw new RuntimeException('Market quote provider is unavailable.');
    foreach($symbols as $symbol)
        if(!preg_match('/^[A-Z][A-Z0-9.^-]{0,13}$/D',$symbol))
            throw new InvalidArgumentException('Unsupported quote symbol.');
    $url='https://api.twelvedata.com/quote?'.http_build_query([
        'symbol'=>implode(',',$symbols),
        'format'=>'JSON',
    ],'','&',PHP_QUERY_RFC3986);
    $ch=curl_init($url);
    if($ch===false)throw new RuntimeException('Market quote provider is unavailable.');
    try {
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>3,
            CURLOPT_TIMEOUT=>10,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>[
                'Authorization: apikey '.$apiKey,
                'Accept: application/json',
            ],
            CURLOPT_USERAGENT=>'Jasr-InvestmentTracker/2.0',
        ]);
        $payload=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($payload===false || $status!==200 || strlen((string)$payload)>524288)
            throw new RuntimeException('Market quote provider is temporarily unavailable.');
        return (string)$payload;
    } finally {curl_close($ch);}
}
/** Return cached market observations only for the authenticated viewer's open positions. */
function it_mq_snapshot(string $path,array $tickers,array $config,int $now): array
{
    $records=\Jasr\Framework\JsonStore::read($path)['records'];
    $saved=$records[0]??[];
    $quotes=[];$set=array_fill_keys($tickers,true);
    foreach(($saved['quotes']??[]) as $ticker=>$q) {
        if(!isset($set[$ticker]) || !is_array($q))continue;
        $ts=strtotime((string)($q['as_of']??''));
        $price=$q['price']??null;
        if(!$ts || $ts>$now+300 || $now-$ts>$config['max_quote_age_seconds'] ||
           !is_numeric($price) || !is_finite((float)$price) || (float)$price<=0 ||
           ($q['source']??'')!=='Twelve Data' || ($q['currency']??'')!=='USD')continue;
        $q['stale']=$now-$ts>$config['stale_seconds'];
        $quotes[$ticker]=$q;
    }
    return $quotes;
}
/**
 * Once per minute a single 8-symbol-or-smaller batch may run. Cached quotes
 * are shared across authorized portfolios, while stock quantities and cost
 * basis never leave the local server. JsonStore uses an atomic locked update.
 * The optional fetcher and clock make every quota / failure mode testable.
 */
function it_mq_refresh(string $path,array $tickers,array $config,bool $force=false,?callable $fetcher=null,?int $clock=null): array
{
    $now=$clock??time();
    if(empty($config['enabled']) || trim((string)($config['api_key']??''))==='') {
        return ['status'=>'not_configured','attempted'=>0,'updated'=>0,'message'=>'Configure a private Twelve Data key to enable automatic quotes.'];
    }
    $fetcher??=it_mq_http_batch(...);
    $tickers=array_values(array_unique(array_filter($tickers,static fn($ticker)=>
        is_string($ticker)&&preg_match('/^[A-Z][A-Z0-9.^-]{0,13}$/D',$ticker))));
    sort($tickers);
    if(!$tickers)return ['status'=>'no_open_positions','attempted'=>0,'updated'=>0,'message'=>'No open stock positions to refresh.'];
    $result=['status'=>'cached','attempted'=>0,'updated'=>0,'message'=>'Using recent cached quotes.'];
    \Jasr\Framework\JsonStore::update($path,static function(array $records)use(
        $tickers,$config,$force,$fetcher,$now,&$result
    ):array {
        $saved=$records[0]??[];
        if(!is_array($saved))$saved=[];
        $quotes=is_array($saved['quotes']??null)?$saved['quotes']:[];
        $attempts=is_array($saved['attempts']??null)?$saved['attempts']:[];
        $usage=array_values(array_filter(is_array($saved['usage']??null)?$saved['usage']:[],
            static fn($entry)=>is_array($entry) && is_int($entry['at']??null) &&
                is_int($entry['credits']??null) && $entry['at']>$now-86400));
        $minute=0;$daily=0;
        foreach($usage as $item) {
            $daily+=$item['credits'];
            if($item['at']>$now-60)$minute+=$item['credits'];
        }
        $maxMinute=max(1,min(8,(int)$config['credits_per_minute']));
        $maxDay=max(1,min(760,(int)$config['credits_per_day']));
        $allow=min(8,$maxMinute-$minute,$maxDay-$daily);
        if($allow<=0) {
            $result=['status'=>'rate_limited','attempted'=>0,'updated'=>0,
                     'message'=>'Quote provider budget reached; cached quotes are retained.'];
            return [['quotes'=>$quotes,'attempts'=>$attempts,'usage'=>$usage]];
        }
        $due=[];
        foreach($tickers as $ticker) {
            $q=$quotes[$ticker]??[];
            $lastFetch=strtotime((string)($q['fetched_at']??''))?:0;
            $lastAttempt=(int)($attempts[$ticker]??0);
            $minimum=$force?300:$config['cache_seconds'];
            // The provider can report the same regular close all weekend.
            // Don't request again until the cache interval has elapsed.
            if($now-$lastFetch<$minimum || $now-$lastAttempt<300)continue;
            $due[]=$ticker;
        }
        if(!$due) {
            $result=['status'=>'cached','attempted'=>0,'updated'=>0,'message'=>'Quotes are up to date in the local cache.'];
            return [['quotes'=>$quotes,'attempts'=>$attempts,'usage'=>$usage]];
        }
        $chosen=array_slice($due,0,$allow);
        $usage[]=['at'=>$now,'credits'=>count($chosen)];
        foreach($chosen as $ticker)$attempts[$ticker]=$now;
        $result=['status'=>'refreshed','attempted'=>count($chosen),'updated'=>0,'message'=>'Quote refresh completed.'];
        try{
            $response=$fetcher($chosen,$config['api_key']);
            $parsed=it_mq_parse_response((string)$response,$chosen,$now);
            foreach($parsed as $ticker=>$quote) {
                $previous=strtotime((string)($quotes[$ticker]['as_of']??''))?:0;
                $observed=strtotime($quote['as_of'])?:0;
                if($observed<$previous)continue;
                $quotes[$ticker]=$quote;
                $result['updated']++;
            }
            if(!$parsed || count($parsed)<count($chosen)){
                $result['status']='partial';
                $result['message']='Some symbols have no valid USD quote or are unavailable from the provider.';
            }
        }catch(Throwable $exception) {
            // Avoid exposing the HTTP header / key in the response or logs.
            $result['status']='unavailable';
            $result['message']='Quote provider could not be reached; previous prices were preserved.';
        }
        return [['quotes'=>$quotes,'attempts'=>$attempts,'usage'=>$usage]];
    });
    return $result;
}
