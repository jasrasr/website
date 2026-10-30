<?php
/**
 * File Name: reconciliation.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Source-preserving Robinhood import staging, per-ticker corrections and safe ledger conversion.
 * Revision: 1.1.0
 * Created Date: 2026-10-30
 * Modified Date: 2026-10-30
 * Changelog: 1.1.0 - Keep historical execution prices as dated observations, separate from live quotes. 1.0.0 - Private draft, editable sale-lot allocations, verified split ratios, share reconciliation and atomic ticker import.
 */
declare(strict_types=1);

/* No brokerage CSV contents are committed or stored under a publicly readable URL.
 * Per-user, per-report parsed drafts remain protected by storage/.htaccess.
 * Real tax-lot election must come from a broker statement, not activity CSV.
 */
function it_rc_read(string $file): array
{
    if (!is_file($file) || !is_readable($file) || filesize($file)>5242880) {
        throw new InvalidArgumentException('Robinhood activity CSV must be under 5 MB.');
    }
    $fh=fopen($file,'rb');
    if (!$fh) throw new InvalidArgumentException('Unable to read uploaded CSV.');
    try {
        $fields=['Activity Date','Process Date','Settle Date','Instrument','Description','Trans Code','Quantity','Price','Amount'];
        $head=fgetcsv($fh,0,',','"','');
        if (!is_array($head)) throw new InvalidArgumentException('Missing activity header.');
        $head[0]=ltrim($head[0],"\xEF\xBB\xBF");
        if ($head!==$fields) throw new InvalidArgumentException('Select the original Robinhood Account activity CSV.');
        $raw=[];$line=1;
        while (($values=fgetcsv($fh,0,',','"',''))!==false) {
            $line++;
            if ($values===[null] || $values===['']) continue;
            if (count($values)===10 && trim(implode('',array_slice($values,0,9)))==='' &&
                str_contains($values[9],'informational purposes only')) continue;
            if (count($values)!==9 || count($raw)>=10000) {
                throw new InvalidArgumentException('Invalid or oversized activity row near '.$line.'.');
            }
            $r=array_combine($fields,$values);
            $ticker=strtoupper(trim($r['Instrument']));
            $date=it_rh_date($r['Activity Date']);
            if ($ticker==='' || !preg_match('/^[A-Z0-9.^-]{1,14}$/D',$ticker)) continue;
            $code=trim($r['Trans Code']);
            $qty=trim($r['Quantity']);
            $amount=it_rh_amount($r['Amount']);
            $kind=match($code) {
                'Buy'=>str_contains($r['Description'],'Dividend Reinvestment')?'reinvest':'buy',
                'Sell'=>'sell',
                'CDIV'=>'dividend',
                'SPL'=>'split_credit',
                'SLIP'=>'other_income',
                'AFEE','DFEE','DTAX'=>'fee',
                default=>'unsupported',
            };
            if (in_array($kind,['buy','sell','reinvest'],true) &&
                (!is_numeric($qty) || (float)$qty<=0 || $amount===null || $amount==0)) {
                $kind='unsupported';
            }
            $normalizedKey=implode('|',[$date,$ticker,$code,$qty,$r['Price'],$r['Amount'],$r['Description']]);
            $raw[]=[
                'date'=>$date,'ticker'=>$ticker,'code'=>$code,'kind'=>$kind,
                'quantity'=>is_numeric($qty)?(float)$qty:null,
                'amount'=>$amount,'price'=>$r['Price']!==''?it_rh_amount($r['Price']):null,
                'credit'=>$kind==='split_credit' && is_numeric($qty)?(float)$qty:null,
                'line'=>$line,'key'=>$normalizedKey,
                'description'=>mb_safe_cut($r['Description'],130),
            ];
        }
    } finally { fclose($fh); }
    if (!$raw) throw new InvalidArgumentException('No supported stock symbols in report.');
    $seen=[];
    foreach ($raw as &$r) {
        $key=hash('sha256',$r['key']);
        $seen[$key]=($seen[$key]??0)+1;
        $r['id']=substr(hash('sha256',$key.'|'.$seen[$key]),0,22);
        unset($r['key']);
    }
    unset($r);
    // Pair a dividend credit with a subsequent SAME-SYMBOL DRIP only if unique.
    $dividends=[];
    foreach ($raw as $i=>$r) if ($r['kind']==='dividend') $dividends[$r['ticker']][]=$i;
    $used=[];
    foreach ($raw as $i=>&$r) {
        if ($r['kind']!=='reinvest') continue;
        $candidates=[];
        foreach ($dividends[$r['ticker']]??[] as $j) {
            $d=$raw[$j];
            $days=(strtotime($r['date'].' UTC')-strtotime($d['date'].' UTC'))/86400;
            if ($days>=0 && $days<=14 && !isset($used[$j]) &&
                abs(($d['amount']??0)-abs((float)$r['amount']))<=0.005) $candidates[]=$j;
        }
        if (count($candidates)===1) {
            $j=$candidates[0];$used[$j]=true;
            $raw[$j]['kind']='paired_credit';
            $raw[$j]['paired_to']=$r['id'];
            $r['paired_to']=$raw[$j]['id'];
        } else $r['pair_issue']=count($candidates)>1?'Ambiguous DRIP credit':'No matching dividend credit';
    }
    unset($r);
    usort($raw,static fn($a,$b)=>
        strcmp($a['date'],$b['date'])
        ?: (($a['kind']==='split_credit'?0:1)<=>($b['kind']==='split_credit'?0:1))
        ?: ($b['line']<=>$a['line']));
    return $raw;
}
function mb_safe_cut(string $value,int $length): string
{
    $value=trim(preg_replace('/\s+/u',' ',$value)??'');
    return substr($value,0,$length);
}
function it_rc_stage(string $uploadedFile): array
{
    $rows=it_rc_read($uploadedFile);
    return [
        'created_at'=>gmdate(DATE_ATOM),
        'source_hash'=>hash_file('sha256',$uploadedFile),
        'source_records'=>count($rows),
        'rows'=>$rows,
        'reviews'=>[],
        'completed'=>[],
    ];
}
function it_rc_symbols(array $stage): array
{
    $counts=[];
    foreach ($stage['rows'] as $r) {
        $t=$r['ticker'];
        $counts[$t]??=['ticker'=>$t,'counts'=>[],'status'=>'not reviewed'];
        $counts[$t]['counts'][$r['kind']]=($counts[$t]['counts'][$r['kind']]??0)+1;
    }
    foreach ($counts as $t=>&$info) {
        if(isset($stage['completed'][$t]))$info['status']='imported';
        elseif(isset($stage['reviews'][$t]))$info['status']='review in progress';
    }
    unset($info);
    ksort($counts);
    return array_values($counts);
}
function it_rc_revision(array $stage): string
{
    return hash('sha256',json_encode($stage,JSON_THROW_ON_ERROR));
}
function it_rc_view(array $stage,?string $ticker=null): array
{
    $result=[
        'created_at'=>$stage['created_at'],'source_records'=>$stage['source_records'],
        'ticker_count'=>count(it_rc_symbols($stage)),'source_fingerprint'=>substr($stage['source_hash'],0,12),
        'tickers'=>it_rc_symbols($stage),'revision'=>it_rc_revision($stage),
        'notice'=>'Draft in private storage; no ledger holdings were imported by staging.',
    ];
    if ($ticker!==null) {
        $ticker=strtoupper($ticker);
        $result['ticker']=$ticker;
        $result['rows']=array_values(array_filter($stage['rows'],static fn($r)=>$r['ticker']===$ticker));
        $result['review']=$stage['reviews'][$ticker]??['expected_shares'=>'','ratios'=>[],'sales'=>[]];
        $result['completed']=$stage['completed'][$ticker]??null;
    }
    return $result;
}
function it_rc_review(array $stage,string $ticker,array $input): array
{
    $ticker=strtoupper(trim($ticker));
    if(!preg_match('/^[A-Z0-9.^-]{1,14}$/D',$ticker))throw new InvalidArgumentException('Invalid ticker.');
    if(isset($stage['completed'][$ticker]))throw new InvalidArgumentException('This ticker is already imported; edits cannot rewrite its ledger history.');
    $rows=array_values(array_filter($stage['rows'],static fn($x)=>$x['ticker']===$ticker));
    if(!$rows)throw new InvalidArgumentException('Ticker is not in staged report.');
    $expected=$input['expected_shares']??null;
    if(!is_numeric($expected) || (float)$expected<0 || !is_finite((float)$expected)) {
        throw new InvalidArgumentException('Enter the verified Robinhood current share quantity.');
    }
    $validIds=array_fill_keys(array_column($rows,'id'),true);
    $ratios=[];$sales=[];
    foreach($input['ratios']??[] as $id=>$ratio) {
        if(!isset($validIds[$id]) || !is_array($ratio) || count($ratio)>3)
            throw new InvalidArgumentException('Invalid split correction.');
        $n=(int)($ratio['n']??0);$d=(int)($ratio['d']??0);
        if ($n<1 || $d<1 || $n>10000 || $d>10000 || $n===$d)
            throw new InvalidArgumentException('Split ratio must be non-1:1 positive integers.');
        $ratios[$id]=['n'=>$n,'d'=>$d];
    }
    foreach($input['sales']??[] as $id=>$allocation) {
        if(!isset($validIds[$id]) || !is_array($allocation) || count($allocation)>100) {
            throw new InvalidArgumentException('Invalid sale lot allocations.');
        }
        $choices=[];
        foreach($allocation as $lotId=>$q) {
            if(!isset($validIds[$lotId]) || !is_numeric($q) || (float)$q<=0 || !is_finite((float)$q)) {
                throw new InvalidArgumentException('Sale allocations require existing positive-quantity purchase lots.');
            }
            $choices[$lotId]=(float)$q;
        }
        $sales[$id]=$choices;
    }
    $stage['reviews'][$ticker]=[
        'expected_shares'=>(float)$expected,
        'ratios'=>$ratios,'sales'=>$sales,
        'source_confirmed'=>(bool)($input['source_confirmed']??false),
    ];
    return $stage;
}
function it_rc_build(array $stage,string $ticker): array
{
    $review=$stage['reviews'][$ticker]??null;
    if(!$review)throw new InvalidArgumentException('Save the stock review first.');
    $rows=array_values(array_filter($stage['rows'],static fn($r)=>$r['ticker']===$ticker));
    $events=[];$issues=[];$balance=0.0;$confirmedLots=[];
    foreach($rows as $r) {
        $kind=$r['kind'];$id=$r['id'];
        if($kind==='paired_credit')continue;
        if($kind==='unsupported') {
            $issues[]='Row '.$r['line'].' '.$r['code'].': unsupported stock action; needs separate verified corporate-action treatment.';
            continue;
        }
        if($kind==='reinvest' && !empty($r['pair_issue'])) {
            $issues[]='Row '.$r['line'].' reinvestment: '.$r['pair_issue'].'; verify dividend before import.';
            continue;
        }
        if($kind==='split_credit') {
            $ratio=$review['ratios'][$id]??null;
            if(!$ratio){$issues[]='Row '.$r['line'].' split: supply verified numerator and denominator.';continue;}
            $factor=$ratio['n']/$ratio['d'];
            $credit=(float)($r['credit']??0);
            $expectedCredit=$balance*($factor-1);
            if($balance<=0 || $factor<=0 || abs($credit-$expectedCredit)>max(0.0002,abs($expectedCredit)*0.002)) {
                $issues[]='Row '.$r['line'].' split credit '.$credit.' does not reconcile to before-split shares '.$balance.' at '.$ratio['n'].':'.$ratio['d'].'.';
                continue;
            }
            $balance*=$factor;
            foreach ($confirmedLots as &$lot) $lot['remaining']*=$factor;
            unset($lot);
            $events[]=it_rc_event($r,'split',0,0,'',$ratio['n'],$ratio['d']);
            continue;
        }
        if($kind==='buy' || $kind==='reinvest') {
            if(($r['amount']??null)===null || (float)$r['amount']>=0 ||
                ($r['quantity']??0)<=0) {
                $issues[]='Row '.$r['line'].' purchase values are not valid.';
                continue;
            }
            $q=(float)$r['quantity'];$balance+=$q;
            $confirmedLots[$id]=['remaining'=>$q,'date'=>$r['date']];
            $events[]=it_rc_event($r,$kind,$q,abs((float)$r['amount']));
            if(($r['price']??0)>0)$events[]=it_rc_event($r,'price',0,0,'',0,0,(float)$r['price']);
            continue;
        }
        if($kind==='sell') {
            $alloc=$review['sales'][$id]??[];
            $q=(float)($r['quantity']??0);
            $proceeds=(float)($r['amount']??0);
            if($q<=0||$proceeds<=0 || !$alloc) {
                $issues[]='Row '.$r['line'].' sale: assign originating purchase lot(s), share amounts and verify proceeds.';
                continue;
            }
            $allocated=array_sum($alloc);
            if(abs($allocated-$q)>0.0000006){
                $issues[]='Row '.$r['line'].' sale: allocations '.$allocated.' do not total sold shares '.$q.'.';
                continue;
            }
            foreach($alloc as $lotId=>$shares){
                if(!isset($confirmedLots[$lotId]) || $confirmedLots[$lotId]['remaining']+0.0000006<$shares) {
                    $issues[]='Row '.$r['line'].' sale references unavailable or oversold lot '.$lotId.'.';
                    continue 2;
                }
            }
            foreach($alloc as $lotId=>$shares){
                $confirmedLots[$lotId]['remaining']-=$shares;
                $events[]=it_rc_event($r,'sell',$shares,$proceeds*($shares/$allocated),'rh'.$lotId);
            }
            $balance-=$q;
            if(($r['price']??0)>0)$events[]=it_rc_event($r,'price',0,0,'',0,0,(float)$r['price']);
            continue;
        }
        if($kind==='dividend' || $kind==='other_income') {
            if(($r['amount']??0)<=0){$issues[]='Row '.$r['line'].' expected positive income.';continue;}
            $events[]=it_rc_event($r,$kind,0,(float)$r['amount']);
            continue;
        }
        if($kind==='fee') {
            if(($r['amount']??0)>=0){$issues[]='Row '.$r['line'].' expected a negative fee.';continue;}
            $events[]=it_rc_event($r,'fee',0,abs((float)$r['amount']));
        }
    }
    if(abs($balance-(float)$review['expected_shares'])>0.000001) {
        $issues[]='Calculated remaining shares '.number_format($balance,8,'.','').
            ' do not match verified broker shares '.number_format((float)$review['expected_shares'],8,'.','').'.';
    }
    if(empty($review['source_confirmed']))
        $issues[]='Confirm that purchase dates, split ratios and selected sale lots have been reviewed against brokerage records.';
    if(!$issues) {
        try {it_calculate($events);}
        catch(Throwable $error){$issues[]='Ledger validation failed: '.$error->getMessage();}
    }
    return ['ticker'=>$ticker,'events'=>$events,'issues'=>$issues,'computed_shares'=>$balance,
        'expected_shares'=>$review['expected_shares'],'ready'=>!$issues,
        'notice'=>'Passing validation does not prove the broker elected these sale lots. Review tax statements separately.'];
}
function it_rc_event(array $r,string $kind,float $q,float $amount,string $lot='',int $num=0,int $den=0,float $price=0): array
{
    $ev=it_event([
        'date'=>$r['date'],'ticker'=>$r['ticker'],'type'=>$kind,
        'quantity'=>$q,'amount'=>$amount,'price'=>$price,
        'numerator'=>$num,'denominator'=>$den,'lot_id'=>$lot,
        'note'=>'Robinhood row '.$r['line'].' ('.$r['code'].')'.($kind==='price'?' execution price; NOT closing quote':''),
    ]);
    $ev['id']='rh'.$r['id'].($kind==='sell'?substr(hash('sha256',$lot),0,5):($kind==='price'?'quote':''));
    $ev['external_id']=$ev['id'];
    return $ev;
}
