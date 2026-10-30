<?php
/**
 * File Name: reconciliation.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Source-preserving Robinhood import staging, per-ticker corrections and safe ledger conversion.
 * Revision: 1.3.1
 * Created Date: 2026-10-30
 * Modified Date: 2026-10-30
 * Changelog: 1.3.1 - Make batch preview self-contained for CLI regression and safe combined-ledger validation. 1.3.0 - Add full-report eligibility preview, exception queue and atomic batch preparation. 1.2.0 - Suggest split ratios and FIFO from source records, preserving REC credits as unknown-basis receipts and estimated status. 1.1.1 - Preserve report end date for share confirmation and reject fractional split ratios. 1.1.0 - Keep historical execution prices as dated observations, separate from live quotes. 1.0.0 - Private draft, editable sale-lot allocations, verified split ratios, share reconciliation and atomic ticker import.
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
                'REC'=>'receipt',
                'AFEE','DFEE','DTAX'=>'fee',
                default=>'unsupported',
            };
            if($kind==='receipt' && (!is_numeric($qty) || (float)$qty<=0))$kind='unsupported';
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
        'report_last_date'=>max(array_column($rows,'date')),
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
        'report_last_date'=>$stage['report_last_date']??null,
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
        $rawN=$ratio['n']??null;$rawD=$ratio['d']??null;
        if(!is_numeric($rawN) || !is_numeric($rawD) || (float)$rawN!=(int)$rawN || (float)$rawD!=(int)$rawD)
            throw new InvalidArgumentException('Split ratio components must be whole numbers.');
        $n=(int)$rawN;$d=(int)$rawD;
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
        'estimated'=>(bool)($input['estimated']??false),
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
        if($kind==='receipt') {
            // A brokerage REC is a share credit, not proof of a zero-cost purchase.
            // Carry shares with unknown cost basis and unknown tax holding period.
            $q=(float)($r['quantity']??0);
            if($q<=0){$issues[]='Row '.$r['line'].' receipt has no positive share count.';continue;}
            $balance+=$q;
            $confirmedLots[$id]=['remaining'=>$q,'date'=>$r['date']];
            $events[]=it_rc_event($r,'receipt',$q,0);
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
    if(empty($review['source_confirmed']) && empty($review['estimated']))
        $issues[]='Confirm the records or choose provisional automatic allocation with estimated results.';
    if(!$issues) {
        try {it_calculate($events);}
        catch(Throwable $error){$issues[]='Ledger validation failed: '.$error->getMessage();}
    }
    if(!empty($review['estimated']))foreach($events as &$ev)$ev['allocation_estimated']=true;
    unset($ev);
    return ['ticker'=>$ticker,'events'=>$events,'issues'=>$issues,'computed_shares'=>$balance,
        'expected_shares'=>$review['expected_shares'],'ready'=>!$issues,
        'estimated'=>!empty($review['estimated']),
        'notice'=>!empty($review['estimated'])?'PROVISIONAL: FIFO assignment is not a verified broker election; REC shares have unknown cost basis. Do not use estimated tax lots for filing or deciding what to sell.':'Passing validation does not prove the broker elected these sale lots. Review tax statements separately.'];
}

/**
 * Infer a common exact split ratio from shares credited vs holdings immediately
 * beforehand. A matching quantity supports an estimate; it is NOT independent
 * corporate action verification. Never claim the multiplier is broker certified.
 */
function it_rc_split_guess(float $before, float $credited): ?array
{
    if ($before<=0 || $credited<=0) return null;
    $options=[];
    foreach ([2,3,4,5,6,7,8,10,12,15,20,25,50,100] as $n) {
        $delta=$before*($n-1);
        if (abs($credited-$delta)<=max(0.00015,abs($delta)*0.001))$options[]=['n'=>$n,'d'=>1];
    }
    return count($options)===1?$options[0]:null;
}
function it_rc_auto_review(array $stage, string $ticker): array
{
    $ticker=strtoupper(trim($ticker));
    if(isset($stage['completed'][$ticker]))throw new InvalidArgumentException('Already imported.');
    $rows=array_values(array_filter($stage['rows'],static fn($r)=>$r['ticker']===$ticker));
    if(!$rows)throw new InvalidArgumentException('Ticker not present in source file.');
    $available=[];$balance=0.0;$ratios=[];$sales=[];$issues=[];$warnings=[];
    foreach ($rows as $row) {
        $kind=$row['kind'];$id=$row['id'];
        if ($kind==='paired_credit' || $kind==='dividend' || $kind==='other_income' || $kind==='fee')continue;
        if ($kind==='buy' || $kind==='reinvest' || $kind==='receipt') {
            if($kind==='receipt')$warnings[]='Row '.$row['line'].' REC share credit adds '.(float)$row['quantity'].' shares with UNKNOWN cost basis and acquisition date.';
            $q=(float)($row['quantity']??0);
            if($q<=0){$issues[]='Row '.$row['line'].' missing purchase/receipt shares.';continue;}
            $available[$id]=['remaining'=>$q,'date'=>$row['date']];
            $balance+=$q;continue;
        }
        if($kind==='split_credit'){
            $ratio=it_rc_split_guess($balance,(float)($row['credit']??0));
            if(!$ratio){$issues[]='Row '.$row['line'].' split ratio not unambiguously inferable from share credit.';continue;}
            $ratios[$id]=$ratio;
            $factor=$ratio['n']/$ratio['d'];
            $balance*=$factor;
            foreach($available as &$lot)$lot['remaining']*=$factor;
            unset($lot);
            $warnings[]='Row '.$row['line'].' split ratio '.$ratio['n'].':'.$ratio['d'].' inferred from rounded credited shares; check corporate-action history.';
            continue;
        }
        if($kind==='sell'){
            $needed=(float)($row['quantity']??0);
            if($needed<=0){$issues[]='Row '.$row['line'].' invalid sale quantity.';continue;}
            $allocation=[];
            foreach($available as $lotId=>&$lot){
                if($needed<=0.0000000001)break;
                if($lot['remaining']<=0)continue;
                $used=min($needed,$lot['remaining']);
                $lot['remaining']-=$used;$needed-=$used;$allocation[$lotId]=$used;
            }
            unset($lot);
            if($needed>0.0000006){$issues[]='Row '.$row['line'].' sold more shares than earlier purchase and receipt history supports.';continue;}
            $sales[$id]=$allocation;$balance-=(float)$row['quantity'];
            $warnings[]='Row '.$row['line'].' sale allocated FIFO for performance ESTIMATE, not actual broker tax lots.';
            continue;
        }
        $issues[]='Row '.$row['line'].' code '.$row['code'].' unrecognized or complex corporate action; source retained but not safely importable.';
    }
    if($issues)return ['ready'=>false,'ticker'=>$ticker,'issues'=>$issues,'warnings'=>$warnings,'computed_shares'=>$balance];
    $new=it_rc_review($stage,$ticker,[
        'expected_shares'=>max(0,$balance),'ratios'=>$ratios,'sales'=>$sales,
        'estimated'=>true,'source_confirmed'=>false,
    ]);
    $out=it_rc_build($new,$ticker);
    if(!$out['ready'])return ['ready'=>false,'ticker'=>$ticker,'issues'=>$out['issues'],'warnings'=>$warnings,'computed_shares'=>$balance];
    return ['ready'=>true,'stage'=>$new,'ticker'=>$ticker,'warnings'=>$warnings,'events'=>$out['events'],
        'computed_shares'=>$balance,'estimated'=>true,'issues'=>[]];
}


/**
 * Inspect every symbol without mutating the permanent ledger.
 * All eligible stock events are built and replayed before authorization
 * to commit them in a single atomic portfolio update.
 */
function it_rc_batch_preview(array $stage,array $existing): array
{
    $symbols=it_rc_symbols($stage);
    $already=[];
    foreach ($existing as $event) {
        if(is_array($event) && empty($event['voided_at']) && isset($event['ticker']))
            $already[(string)$event['ticker']]=true;
    }
    $eligible=[];$blocked=[];$skipped=[];$all=[];
    foreach ($symbols as $symbol) {
        $ticker=$symbol['ticker'];
        if (isset($stage['completed'][$ticker])||isset($already[$ticker])) {
            $skipped[]=['ticker'=>$ticker,'reason'=>'Previously imported or already has ledger records.'];
            continue;
        }
        try {
            $auto=it_rc_auto_review($stage,$ticker);
        }catch(Throwable $e){
            $blocked[]=['ticker'=>$ticker,'reason'=>'Source rows cannot be reconstructed: '.$e->getMessage()];
            continue;
        }
        if (!$auto['ready']) {
            $blocked[]=['ticker'=>$ticker,'reason'=>implode('; ',array_slice($auto['issues']??[],0,3))];
            continue;
        }
        $events=$auto['events'];
        $all=array_merge($all,$events);
        $eligible[]=[
            'ticker'=>$ticker,'events'=>count($events),
            'computed_shares'=>$auto['computed_shares'],
            'warnings'=>array_slice($auto['warnings']??[],0,3),
        ];
    }
    if(count($all)>0){
        try {
            if(count($existing)+count($all)>20000)throw new InvalidArgumentException('Portfolio exceeds 20,000 event limit.');
            $ids=[];
            foreach(array_merge($existing,$all) as $e) {
                if(!empty($e['id']) && isset($ids[(string)$e['id']]))
                    throw new InvalidArgumentException('Duplicate ledger event identity.');
                if(!empty($e['id']))$ids[(string)$e['id']]=true;
            }
            it_calculate(array_values(array_filter(array_merge($existing,$all),
                static fn($e)=>empty($e['voided_at']))));
        }
        catch(Throwable $e) {
            // Fail closed instead of claiming any batch is ready when the
            // overall ledger cannot replay or store all of it.
            return [
                'ready'=>false,'eligible'=>[],'blocked'=>$blocked,'skipped'=>$skipped,
                'error'=>'Combined ledger validation failed: '.$e->getMessage(),
                'events'=>[],'import_count'=>0,'estimated'=>true
            ];
        }
    }
    return [
        'ready'=>count($eligible)>0,'eligible'=>$eligible,'blocked'=>$blocked,
        'skipped'=>$skipped,'events'=>$all,'import_count'=>count($all),'estimated'=>true,
    ];
}
function it_rc_batch_public(array $batch,array $stage,array $existing): array
{
    unset($batch['events']);
    $batch['eligible_count']=count($batch['eligible']);
    $batch['blocked_count']=count($batch['blocked']);
    $batch['skipped_count']=count($batch['skipped']);
    $batch['fingerprint']=hash('sha256',it_rc_revision($stage).'|'.hash('sha256',json_encode($existing,JSON_THROW_ON_ERROR)).'|'.
        json_encode($batch['eligible'],JSON_THROW_ON_ERROR));
    $batch['explanation']='The CSV is the source of activity. FIFO sale-lot choices are estimated, inferred splits are provisional; no verified broker tax lots are claimed.';
    return $batch;
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
