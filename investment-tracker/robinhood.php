<?php
/**
 * File Name: robinhood.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Privacy-preserving, preview-only parser and reconciliation report for Robinhood account activity CSV.
 * Revision: 1.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.0.0 - Recognize source headers, split credits, reinvestment pairs, unknown actions and unresolved sale allocations.
 */
declare(strict_types=1);

/**
 * Robinhood's Activity report contains dated account actions, NOT the
 * originating tax lots of its stock sales. It is NEVER automatically imported.
 * The footer may be a non-transaction disclaimer row and must be ignored.
 */
function it_rh_amount(string $value): ?float
{
    $value=trim($value);
    if ($value==='') return null;
    if (!preg_match('/^(\()?\\$?([0-9]{1,3}(?:,[0-9]{3})*|[0-9]+)(?:\\.([0-9]{1,2}))?(\))?$/D',$value,$m)) {
        throw new InvalidArgumentException('Invalid monetary value in activity report.');
    }
    if (($m[1]??'')!=='' xor ($m[4]??'')!=='') throw new InvalidArgumentException('Malformed negative monetary value.');
    $n=(float)str_replace(',','',$m[2].(isset($m[3])?'.'.$m[3]:''));
    return ($m[1]??'')!=='' ? -$n : $n;
}
function it_rh_date(string $date): string
{
    if (!preg_match('/^(\\d{1,2})\\/(\\d{1,2})\\/(\\d{4})$/D',trim($date),$m) ||
        !checkdate((int)$m[1],(int)$m[2],(int)$m[3])) {
        throw new InvalidArgumentException('Unrecognized Robinhood activity date.');
    }
    $iso=sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[1],(int)$m[2]);
    if ($iso > gmdate('Y-m-d')) throw new InvalidArgumentException('Future activity date is not supported.');
    return $iso;
}
function it_rh_classify(array $records): array
{
    $counts=[];$tickers=[];$events=[];$dividends=[];$reinvestments=[];$allIssues=[];
    foreach ($records as $i=>$r) {
        $code=$r['Trans Code'];
        $ticker=trim($r['Instrument']);
        $description=$r['Description'];
        $qty=trim($r['Quantity']);
        $amount=it_rh_amount($r['Amount']);
        $type=match($code){
            'Buy'=>str_contains($description,'Dividend Reinvestment')?'reinvest':'buy',
            'Sell'=>'sell',
            'CDIV'=>'dividend',
            'SPL'=>'split_credit',
            'SLIP'=>'stock_lending',
            'ACH','XENT','CARD','FUTSWP'=>'cash_movement',
            'AFEE','DFEE','DTAX','GOLD'=>'fee_or_tax',
            'INT','IADJ'=>'interest',
            default=>'unsupported',
        };
        $date=it_rh_date($r['Activity Date']);
        $counts[$type]=($counts[$type]??0)+1;
        if ($ticker!=='') {
            if(!preg_match('/^[A-Za-z0-9.^-]{1,16}$/D',$ticker)) throw new InvalidArgumentException('Unexpected instrument symbol.');
            $tickers[$ticker]??=['ticker'=>$ticker,'counts'=>[],'issues'=>[],'first_date'=>$date,'last_date'=>$date];
            $tickers[$ticker]['counts'][$type]=($tickers[$ticker]['counts'][$type]??0)+1;
            $tickers[$ticker]['first_date']=min($tickers[$ticker]['first_date'],$date);
            $tickers[$ticker]['last_date']=max($tickers[$ticker]['last_date'],$date);
        }
        $issue=null;
        if ($type==='sell') $issue='Sale missing its original purchase-lot allocation; brokerage activity alone cannot determine elected tax lots.';
        elseif ($type==='split_credit') $issue='Split quantity is newly credited shares, NOT split ratio; corporate-action terms need verification.';
        elseif ($type==='unsupported' && $ticker!=='') $issue='Unsupported corporate action or cancellation needs manual interpretation before reconciliation.';
        elseif ($type==='fee_or_tax' && $ticker!=='') $issue='Fee or tax adjustment needs treatment before final after-fee performance.';
        if (in_array($type,['buy','sell','reinvest'],true) && (!is_numeric($qty) || (float)$qty<=0 || $amount===null)) {
            $issue='Trade has missing or invalid quantity/amount.';
        }
        if ($issue!==null) {
            if($ticker!=='')$tickers[$ticker]['issues'][$issue]=($tickers[$ticker]['issues'][$issue]??0)+1;
            $allIssues[$issue]=($allIssues[$issue]??0)+1;
        }
        $event=[
            'date'=>$date,'ticker'=>$ticker,'type'=>$type,'quantity'=>$qty===''?null:(is_numeric($qty)?(float)$qty:null),
            'amount'=>$amount,'source_code'=>$code,'line'=>$i+2
        ];
        $events[]=$event;
        if($type==='dividend' && $ticker!=='' && $amount!==null && $amount>0) $dividends[$ticker][]=['index'=>$i,'date'=>$date,'amount'=>$amount];
        if($type==='reinvest' && $ticker!=='' && $amount!==null && $amount<0) $reinvestments[$ticker][]=['index'=>$i,'date'=>$date,'amount'=>abs($amount)];
    }
    $matched=[];
    foreach ($reinvestments as $ticker=>$buys) {
        usort($buys,static fn($a,$b)=>strcmp($a['date'],$b['date']));
        foreach ($buys as $buy) {
            $candidate=null;
            foreach ($dividends[$ticker]??[] as $d) {
                if (isset($matched[$d['index']]) || abs($d['amount']-$buy['amount'])>0.005) continue;
                $days=(strtotime($buy['date'].' UTC')-strtotime($d['date'].' UTC'))/86400;
                if($days<0 || $days>14)continue;
                if ($candidate===null || $d['date']>$candidate['date'])$candidate=$d;
            }
            if ($candidate!==null) {
                $matched[$candidate['index']]=$buy['index'];
            } else {
                $msg='Dividend reinvestment lacks a matching dividend credit; verify corporate action and rounding.';
                $tickers[$ticker]['issues'][$msg]=($tickers[$ticker]['issues'][$msg]??0)+1;
                $allIssues[$msg]=($allIssues[$msg]??0)+1;
            }
        }
    }
    foreach ($matched as $divIndex=>$buyIndex){
        $events[$divIndex]['type']='dividend_reinvested';
        $events[$divIndex]['paired_line']=$buyIndex+2;
        $events[$buyIndex]['paired_line']=$divIndex+2;
    }
    foreach ($tickers as &$t) {
        $t['review_required']=(bool)$t['issues'];
        // No ticker is implicitly ready to import: corporate-action chains,
        // opening positions and true sale-lot elections need reconciliation.
        $t['import_ready']=false;
        $t['issues']=array_map(static fn($name,$count)=>['issue'=>$name,'count'=>$count],
            array_keys($t['issues']),array_values($t['issues']));
        ksort($t['counts']);
    }
    unset($t);
    ksort($tickers);ksort($counts);
    return [
        'records'=>count($records),'first_date'=>count($events)?min(array_column($events,'date')):null,
        'last_date'=>count($events)?max(array_column($events,'date')):null,
        'counts'=>$counts,
        'ticker_count'=>count($tickers),'tickers'=>array_values($tickers),
        'matched_dividend_reinvestments'=>count($matched),
        'reinvestment_rows'=>array_sum(array_map('count',$reinvestments)),
        'open_issues'=>array_map(static fn($name,$count)=>['issue'=>$name,'count'=>$count],array_keys($allIssues),array_values($allIssues)),
        'samples'=>array_slice(array_map(static fn($event)=>[
            'date'=>$event['date'],'ticker'=>$event['ticker'],'type'=>$event['type'],
            'quantity'=>$event['quantity'],'amount'=>$event['amount']
        ],$events),0,8),
        'import_ready'=>false,
        'notice'=>'No records were saved. Tax-lot sales and stock-credit corporate actions require reconciliation; activity alone is not tax-lot proof.',
    ];
}
function it_rh_parse_csv(string $path): array
{
    if(!is_file($path)||!is_readable($path)||filesize($path)>5242880)throw new InvalidArgumentException('Robinhood CSV must be a readable file smaller than 5 MB.');
    $fh=fopen($path,'rb');if(!$fh)throw new InvalidArgumentException('Unable to open Robinhood CSV.');
    try {
        $columns=['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount'];
        $head=fgetcsv($fh,0,',','"','');
        if(!is_array($head))throw new InvalidArgumentException('Missing CSV header.');
        $head[0]=ltrim($head[0],"\xEF\xBB\xBF");
        if($head!==$columns)throw new InvalidArgumentException('Unexpected Robinhood CSV headers. Export Account activity, not a monthly statement.');
        $rows=[];$physical=1;
        while(($values=fgetcsv($fh,0,',','"',''))!==false) {
            $physical++;
            if($values===[null]||$values===[''])continue;
            if(count($values)===10 && trim(implode('',array_slice($values,0,9)))==='' &&
                str_contains($values[9],'informational purposes only'))continue;
            if(count($values)!==9)throw new InvalidArgumentException('Malformed Robinhood CSV row near line '.$physical.'.');
            if(!preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/D',$values[0]))throw new InvalidArgumentException('Malformed activity date near line '.$physical.'.');
            if(count($rows)>=10000)throw new InvalidArgumentException('Too many activity rows.');
            $rows[]=array_combine($columns,$values);
        }
        if(!$rows)throw new InvalidArgumentException('Robinhood report contains no activity rows.');
        return it_rh_classify($rows);
    } finally{fclose($fh);}
}
