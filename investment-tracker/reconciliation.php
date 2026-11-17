<?php
/**
 * File Name: reconciliation.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Source-preserving Robinhood import staging, per-ticker corrections and safe ledger conversion.
 * Revision: 1.9.1
 * Created Date: 2026-10-30
 * Modified Date: 2026-11-16
 * Changelog: 1.9.1 - Diagnose interdependent AMC/APE distribution, conversion and settlement while blocking unsupported basis transfers until broker evidence exists. 1.9.0 - Carry forward documented rounded WKHS splits and paired HCWC/HOST and CHK/CHKAQ history without duplicate cost basis; preserve completed-ticker protection. 1.8.0 - Normalize matched same-day reverse splits, cash liquidations/mergers, and unknown-basis source conversions on read without mutating drafts. 1.7.0 - Match dividends net of DTAX/DFEE, preserve gross/withheld amounts in imported ledger and normalize existing drafts on read. 1.3.4 - Restore positive cashless REC share credits in older staged CSV drafts during read-time reconciliation; preserve unknown basis. 1.3.3 - Require signed exact cancellation matches, preserve cash dividends on canceled DRIPs, and skip zero-event batches. 1.3.2 - Reconcile BCXL/SCXL order reversals by exact source trade before lot replay, including existing staged reports. 1.3.1 - Make batch preview self-contained for CLI regression and safe combined-ledger validation. 1.3.0 - Add full-report eligibility preview, exception queue and atomic batch preparation. 1.2.0 - Suggest split ratios and FIFO from source records, preserving REC credits as unknown-basis receipts and estimated status. 1.1.1 - Preserve report end date for share confirmation and reject fractional split ratios. 1.1.0 - Keep historical execution prices as dated observations, separate from live quotes. 1.0.0 - Private draft, editable sale-lot allocations, verified split ratios, share reconciliation and atomic ticker import.
 */
declare(strict_types=1);
require_once __DIR__.'/corporate-action-evidence.php';

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
                'surrender_quantity'=>preg_match('/^([0-9]+(?:\.[0-9]+)?)S$/D',$qty,$oldQty)?(float)$oldQty[1]:null,
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
    // Pairing is performed by the same read-time normalizer used for already
    // staged private reports, including dividend tax/ADR-fee withholding.
    usort($raw,static fn($a,$b)=>
        strcmp($a['date'],$b['date'])
        ?: (($a['kind']==='split_credit'?0:1)<=>($b['kind']==='split_credit'?0:1))
        ?: ($b['line']<=>$a['line']));
    return it_rc_reconcile_cancellations($raw);
}
function mb_safe_cut(string $value,int $length): string
{
    $value=trim(preg_replace('/\s+/u',' ',$value)??'');
    return substr($value,0,$length);
}
/**
 * Match Robinhood BCXL / SCXL cancellations to precisely offset original trades.
 * Buy + ($100) reversal -> do not create two purchases.
 * Existing staged reports also pass through this read-time normalization:
 * their persistent IDs, file hashes and saved corrections do NOT change.
 *
 * Reversal is accepted only when exactly one same-symbol/date/share/price/dollar
 * trade exists. Ambiguous or unpaired cancellations remain unsupported and must
 * block that ticker; no cost basis is guessed or silently lost.
 */
/**
 * Pair a DRIP only when the source provides an unambiguous dividend, its
 * cash-withholding fees (DTAX and/or DFEE), and the net reinvestment purchase.
 * The original CDIV and fee rows remain preserved for audit. This is run on
 * COPY of saved drafts on every read, not a persistent draft migration.
 *
 * Strict cents arithmetic prevents rounding from consuming an unrelated fee.
 * Only a unique source dividend / fee subset within the 14-day payment window
 * may be matched. No ticker-specific shortcuts, no guessed dividend/lot data.
 */
function it_rc_pair_dividends(array $rows): array
{
    $credits=[];$fees=[];$byId=[];
    foreach($rows as $i=>$row) {
        $byId[(string)($row['id']??'')]=$i;
        if(($row['kind']??'')==='dividend' && ($row['code']??'')==='CDIV' &&
           is_numeric($row['amount']??null) && (float)$row['amount']>0)
            $credits[$row['ticker']][]=$i;
        if(($row['kind']??'')==='fee' &&
           in_array($row['code']??'',['DTAX','DFEE'],true) &&
           is_numeric($row['amount']??null) && (float)$row['amount']<0 &&
           empty($row['withheld_for']))
            $fees[$row['ticker']][]=$i;
    }
    foreach($rows as $i=>&$r) {
        if(($r['kind']??'')!=='reinvest')continue;
        // Already-paired prior drafts remain valid. Add gross-dividend
        // metadata if they were created before this release.
        if(!empty($r['paired_to'])) {
            $j=$byId[(string)$r['paired_to']]??null;
            if($j!==null && ($rows[$j]['kind']??'')==='paired_credit' &&
               ($rows[$j]['paired_to']??'')===($r['id']??null)) {
                $r['gross_dividend']=(float)$rows[$j]['amount'];
                unset($r['pair_issue']);
            }
            continue;
        }
        $net=$r['amount']??null;
        if(!is_numeric($net) || (float)$net>=0) {
            $r['pair_issue']='Invalid reinvestment cash amount';continue;
        }
        $netCents=(int)round(abs((float)$net)*100);
        if(abs(abs((float)$net)*100-$netCents)>0.00001 || $netCents<1) {
            $r['pair_issue']='Reinvestment amount is not whole cents';continue;
        }
        $matches=[];
        foreach($credits[$r['ticker']]??[] as $j) {
            $credit=$rows[$j];
            if(($credit['kind']??'')!=='dividend')continue;
            $age=(strtotime($r['date'].' UTC')-strtotime($credit['date'].' UTC'))/86400;
            if($age<0 || $age>14)continue;
            $grossCents=(int)round((float)$credit['amount']*100);
            if(abs((float)$credit['amount']*100-$grossCents)>0.00001 ||
               $grossCents<$netCents)continue;
            $difference=$grossCents-$netCents;
            if($difference===0) {
                $matches[]=['credit'=>$j,'fees'=>[],'gross'=>$grossCents];
                continue;
            }
            $candidates=[];
            foreach($fees[$r['ticker']]??[] as $k) {
                $fee=$rows[$k];
                if(!empty($fee['withheld_for']))continue;
                $feeDate=$fee['date'];
                if($feeDate<$credit['date'] || $feeDate>$r['date'])continue;
                $cents=(int)round(abs((float)$fee['amount'])*100);
                if($cents<1 || abs(abs((float)$fee['amount'])*100-$cents)>0.00001)continue;
                if($cents<=$difference)$candidates[]=['index'=>$k,'cents'=>$cents];
            }
            if(count($candidates)>8)continue; // Never invent a grouping.
            for($mask=1;$mask<(1<<count($candidates));$mask++) {
                $sum=0;$usedFees=[];
                foreach($candidates as $bit=>$candidate) {
                    if(!($mask&(1<<$bit)))continue;
                    $sum+=$candidate['cents'];$usedFees[]=$candidate['index'];
                }
                if($sum===$difference)$matches[]=['credit'=>$j,'fees'=>$usedFees,'gross'=>$grossCents];
                if(count($matches)>1)break;
            }
            if(count($matches)>1)break;
        }
        if(count($matches)!==1) {
            $r['pair_issue']=count($matches)>1?'Ambiguous dividend/withholding allocation':'No matching dividend credit after withholding';
            continue;
        }
        $match=$matches[0];$j=$match['credit'];
        $rows[$j]['kind']='paired_credit';
        $rows[$j]['paired_to']=$r['id'];
        $r['paired_to']=$rows[$j]['id'];
        $r['gross_dividend']=$match['gross']/100;
        $r['withheld_amount']=($match['gross']-$netCents)/100;
        foreach($match['fees'] as $k)$rows[$k]['withheld_for']=$r['id'];
        unset($r['pair_issue']);
    }
    unset($r);
    return $rows;
}


/**
 * Conservative, non-mutating corporate action normalization for ORIGINAL
 * Robinhood source records. Existing private drafts already contain stable
 * source rows/ids, so this only edits the COPY used during preview/replay.
 *
 * SPR S/credited share pairs can represent an same-symbol reverse split.
 * The actual ratio is not guessed here: it must reconcile to every open
 * purchase lot at replay, with a UNIQUE split denominator. Other actions
 * such as inter-ticker spinoffs and basis allocations stay blocked.
 */
function it_rc_simple_corporate_actions(array $rows): array
{
    $byGroup=[];
    foreach($rows as $i=>$row) {
        $code=(string)($row['code']??'');
        if(in_array($code,['SPR','SXCH','LIQ','MRGS','MRGC'],true))
            $byGroup[$row['ticker'].'|'.$row['date']][$code][]=$i;
        if($code==='CONV' && ($row['kind']??'')==='unsupported' &&
           is_numeric($row['quantity']??null) && (float)$row['quantity']>0 &&
           ($row['price']??null)===null && ($row['amount']??null)===null) {
            // Account conversion delivered shares, but supplies neither
            // original purchase amount nor original holding period.
            $rows[$i]['kind']='receipt';$rows[$i]['basis_unknown_source']='CONV';
        }
    }
    foreach($byGroup as $group) {
        $pairs=$group['SPR']??[];
        if(count($pairs)===2) {
            $credits=[];$surrenders=[];
            foreach($pairs as $i) {
                $row=$rows[$i];
                if(($row['kind']??'')!=='unsupported'||($row['price']??null)!==null||($row['amount']??null)!==null)continue;
                if(is_numeric($row['quantity']??null) && (float)$row['quantity']>0)
                    $credits[]=$i;
                elseif(($row['quantity']??null)===null)$surrenders[]=$i;
            }
            if(count($credits)===1 && count($surrenders)===1) {
                $c=$credits[0];$old=$surrenders[0];
                $cusipCredit=[];$cusipOld=[];
                $hasCredit=preg_match('/\bCUSIP:\s*([A-Z0-9]{9})\b/i',(string)$rows[$c]['description'],$cusipCredit);
                $hasOld=preg_match('/\bCUSIP:\s*([A-Z0-9]{9})\b/i',(string)$rows[$old]['description'],$cusipOld);
                // Require documented change of the *same symbol* security
                // identifier. Avoid treating arbitrary SPR as a split.
                if($hasCredit && $hasOld && $cusipCredit[1]!==$cusipOld[1]) {
                    $rows[$c]['kind']='split_credit';
                    $rows[$c]['credit']=(float)$rows[$c]['quantity'];
                    $rows[$c]['corporate_action']='reverse_split';
                    $rows[$c]['paired_surrender']=$rows[$old]['id'];
                    $rows[$c]['surrender_quantity']=$rows[$old]['surrender_quantity']??null;
                    $rows[$old]['kind']='paired_surrender';
                    $rows[$old]['paired_to']=$rows[$c]['id'];
                }
            }
        }
        foreach([['cash'=>'LIQ','surrender'=>'SXCH','name'=>'liquidation'],
                 ['cash'=>'MRGC','surrender'=>'MRGS','name'=>'cash_merger']] as $spec) {
            $cash=$group[$spec['cash']]??[];$surrender=$group[$spec['surrender']]??[];
            if(count($cash)!==1 || count($surrender)!==1)continue;
            $c=$cash[0];$old=$surrender[0];
            $credit=$rows[$c];$removed=$rows[$old];
            if(($credit['kind']??'')!=='unsupported' ||
               ($removed['kind']??'')!=='unsupported' ||
               !is_numeric($credit['amount']??null) || (float)$credit['amount']<=0 ||
               ($credit['price']??null)!==null || ($removed['amount']??null)!==null ||
               ($removed['price']??null)!==null || ($removed['quantity']??null)!==null)continue;
            $desc=(string)($credit['description']??'');
            $term=$spec['name']==='liquidation'?'Liquidation':'Merger';
            if(!preg_match('/Cash received thru '. $term . '\s+([0-9]+(?:\.[0-9]+)?)\s+shares?\b/i',$desc,$match))continue;
            $shares=(float)$match[1];
            if(!is_finite($shares) || $shares<=0)continue;
            $sourceSurrender=$removed['surrender_quantity']??null;
            if($sourceSurrender!==null && abs((float)$sourceSurrender-$shares)>0.000051)continue;
            $rows[$c]['kind']='sell';
            $rows[$c]['quantity']=$shares;
            $rows[$c]['corporate_action']=$spec['name'];
            $rows[$c]['paired_surrender']=$removed['id'];
            $rows[$old]['kind']='paired_surrender';
            $rows[$old]['paired_to']=$rows[$c]['id'];
        }
    }
    // An SPR may have been stored before this feature; new/old rows were
    // originally "unsupported". Reorder only the copied rows so a credited
    // reverse split executes before other same-day activity and surrendered
    // shares are not mistaken for additional purchases.
    usort($rows,static fn($a,$b)=>
        strcmp($a['date'],$b['date'])
        ?: (($a['kind']==='split_credit'?0:1)<=>($b['kind']==='split_credit'?0:1))
        ?: ($b['line']<=>$a['line']));
    return $rows;
}

/** Infer only a UNIQUE reverse ratio, allowing 4dp broker credit rounding. */
function it_rc_reverse_split_guess(float $before,float $received): ?array
{
    if($before<=0 || $received<=0 || $received>=$before)return null;
    $options=[];
    for($d=2;$d<=200;$d++) {
        if(abs($received-$before/$d)<=0.000050001)$options[]=['n'=>1,'d'=>$d];
        if(count($options)>1)return null;
    }
    return count($options)===1?$options[0]:null;
}

function it_rc_reconcile_cancellations(array $rows): array
{
    // Backward compatibility: older private drafts were staged before positive
    // Robinhood REC share credits were supported, so their saved kind remains
    // "unsupported". Reclassify only an unpriced, cashless, positive share
    // credit while reading. This preserves the staged source row, ID, and
    // revision; it does NOT fabricate a zero-dollar purchase or cost basis.
    foreach($rows as &$source) {
        if(($source['code']??'')!=='REC' || ($source['kind']??'')!=='unsupported')continue;
        $quantity=$source['quantity']??null;
        $cash=$source['amount']??null;
        $price=$source['price']??null;
        if(!is_numeric($quantity) || !is_finite((float)$quantity) || (float)$quantity<=0 ||
           ($cash!==null && (!is_numeric($cash) || (float)$cash!=0.0)) ||
           ($price!==null && (!is_numeric($price) || (float)$price!=0.0)))continue;
        $source['kind']='receipt';
    }
    unset($source);
    $rows=it_rc_pair_dividends($rows);
    $used=[];
    foreach($rows as $idx=>$cancel) {
        $code=(string)($cancel['code']??'');
        if(!in_array($code,['BCXL','SCXL'],true) ||
            ($cancel['kind']??'unsupported')==='cancelled_trade') continue;
        $buyCancellation=$code==='BCXL';
        $q=$cancel['quantity']??null;
        $amount=$cancel['amount']??null;
        $price=$cancel['price']??null;
        // BCXL must REFUND a buy; SCXL must REVERSE positive sale proceeds.
        // Equal-and-opposite cash alone does not prove the correct direction.
        if(!is_numeric($q)||!is_numeric($amount)||!is_numeric($price)||
            (float)$q<=0 || (float)$price<=0 ||
            ($buyCancellation?(float)$amount<=0:(float)$amount>=0)) {
            $rows[$idx]['cancel_issue']='Reversal has invalid quantity, price or cash direction.';
            continue;
        }
        $candidates=[];
        foreach($rows as $j=>$original) {
            $kind=$original['kind']??'';
            if($idx===$j || isset($used[$j]) ||
                !($buyCancellation?in_array($kind,['buy','reinvest'],true):$kind==='sell') ||
                ($original['ticker']??'')!==$cancel['ticker'] ||
                ($original['date']??'')!==$cancel['date']) continue;
            // These are raw CSV quantities, not calculated split-adjusted
            // holdings. Never apply an epsilon that could erase real shares.
            if((float)($original['quantity']??0)!==(float)$q ||
                (float)($original['price']??0)!==(float)$price ||
                (float)($original['amount']??0)!==-(float)$amount)continue;
            $candidates[]=$j;
        }
        if(count($candidates)!==1) {
            $rows[$idx]['cancel_issue']=count($candidates)===0
                ?'No exact corresponding completed trade found.'
                :'Multiple possible canceled trades; cannot choose safely.';
            continue;
        }
        $originalIdx=$candidates[0];
        $original=$rows[$originalIdx];
        if(($original['kind']??'')==='reinvest') {
            // The earlier DRIP matching pass turned the cash dividend into
            // paired_credit. Canceling the DRIP BUY does not cancel that
            // dividend: restore the original positive dividend payment.
            // Any separately reversed dividend still fails validation rather
            // than producing a misleading net-positive dividend.
            $creditMatches=[];
            foreach($rows as $k=>$credit) {
                if(($credit['kind']??'')==='paired_credit' &&
                    ($credit['paired_to']??'')===$original['id'])
                    $creditMatches[]=$k;
            }
            if(count($creditMatches)>1) {
                $rows[$idx]['cancel_issue']='Multiple dividend credits for canceled reinvestment.';
                continue;
            }
            if(count($creditMatches)===1) {
                $k=$creditMatches[0];
                if(($rows[$k]['code']??'')!=='CDIV' || (float)($rows[$k]['amount']??0)<=0) {
                    $rows[$idx]['cancel_issue']='Invalid dividend credit linked to canceled reinvestment.';
                    continue;
                }
                $rows[$k]['kind']='dividend';
                unset($rows[$k]['paired_to']);
                // A canceled DRIP does not cover its withholding. If the
                // dividend was still paid, the fee is now a normal cash tax.
                foreach($rows as &$fee) {
                    if(($fee['withheld_for']??'')===$original['id'])unset($fee['withheld_for']);
                }
                unset($fee);
            }
        }
        $used[$originalIdx]=true;
        $rows[$originalIdx]['kind']='cancelled_trade';
        $rows[$originalIdx]['cancelled_by']=$cancel['id'];
        $rows[$idx]['kind']='cancelled_trade';
        $rows[$idx]['cancels']=$rows[$originalIdx]['id'];
    }
    return it_rc_verified_special_actions(it_rc_simple_corporate_actions($rows));
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
    foreach (it_rc_reconcile_cancellations($stage['rows']) as $r) {
        $t=$r['ticker'];
        $counts[$t]??=['ticker'=>$t,'counts'=>[],'status'=>'not reviewed','source_tickers'=>[]];
        $counts[$t]['source_tickers'][$r['source_ticker']??$t]=true;
        $counts[$t]['counts'][$r['kind']]=($counts[$t]['counts'][$r['kind']]??0)+1;
    }
    foreach ($counts as $t=>&$info) {
        $sources=array_keys($info['source_tickers']);
        $info['source_tickers']=$sources;
        foreach($sources as $original)if(isset($stage['completed'][$original]))$info['status']='imported';
        if($info['status']!=='imported' && isset($stage['completed'][$t]))$info['status']='imported';
        elseif($info['status']!=='imported' && isset($stage['reviews'][$t]))$info['status']='review in progress';
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
        $result['rows']=array_values(array_filter(it_rc_reconcile_cancellations($stage['rows']),static fn($r)=>$r['ticker']===$ticker));
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
    $rows=array_values(array_filter(it_rc_reconcile_cancellations($stage['rows']),static fn($x)=>$x['ticker']===$ticker));
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
    $rows=array_values(array_filter(it_rc_reconcile_cancellations($stage['rows']),static fn($r)=>$r['ticker']===$ticker));
    $events=[];$issues=[];$balance=0.0;$confirmedLots=[];
    foreach($rows as $r) {
        $kind=$r['kind'];$id=$r['id'];
        if($kind==='paired_credit' || $kind==='cancelled_trade' || $kind==='paired_surrender')continue;
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
            $mode=$r['corporate_action']??'';
            $reverse=in_array($mode,['reverse_split','cross_ticker_reverse','verified_rounded_split'],true);
            $roundUp=$mode==='verified_rounded_split';
            $expectedCredit=$reverse?$balance*$factor:$balance*($factor-1);
            $rounding=$reverse?0.000050001:max(0.0002,abs($expectedCredit)*0.002);
            $surrender=$r['surrender_quantity']??null;
            $requiredRatio=$r['ratio_n']??null;
            $requiredDenominator=$r['ratio_d']??null;
            $ratioVerified=$requiredRatio===null ||
                ((int)$requiredRatio===(int)$ratio['n'] && (int)$requiredDenominator===(int)$ratio['d']);
            $validNewShares=$roundUp
                ?abs(ceil($expectedCredit-0.0000000001)-$credit)<=0.00000001
                :abs($credit-$expectedCredit)<=$rounding;
            $openLots=array_keys(array_filter($confirmedLots,static fn($lot)=>($lot['remaining']??0)>0.000000001));
            $reported=isset($r['reported_split_shares'])?(float)$r['reported_split_shares']:null;
            // Only one outstanding lot can receive an account-level rounded
            // share adjustment without unverified tax-lot attribution.
            $requiresSingle=$roundUp || $reported!==null;
            if($balance<=0 || $factor<=0 || !$ratioVerified ||
               ($reverse && $ratio['n']>=$ratio['d']) ||
               !$validNewShares ||
               ($reverse && $surrender!==null && abs((float)$surrender-$balance)>0.000050001) ||
               ($requiresSingle && count($openLots)!==1)) {
                $issues[]='Row '.$r['line'].' corporate split cannot safely reconcile outstanding shares and rounded credit.';
                continue;
            }
            $balance*=$factor;
            foreach($confirmedLots as &$lot)$lot['remaining']*=$factor;
            unset($lot);
            if($requiresSingle) {
                $balance=$credit;
                $confirmedLots[$openLots[0]]['remaining']=$credit;
            }
            $split=it_rc_event($r,'split',0,0,'',$ratio['n'],$ratio['d']);
            if($requiresSingle)$split['reported_split_shares']=$credit;
            if($roundUp)$split['split_round_up']=true;
            $events[]=$split;
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
            $purchase=it_rc_event($r,$kind,$q,abs((float)$r['amount']));
            if($kind==='reinvest' && isset($r['gross_dividend']))
                $purchase['gross_dividend']=(float)$r['gross_dividend'];
            $events[]=$purchase;
            if(($r['price']??0)>0)$events[]=it_rc_event($r,'price',0,0,'',0,0,(float)$r['price']);
            continue;
        }
        if($kind==='sell') {
            if(in_array($r['corporate_action']??'',['liquidation','cash_merger','cash_in_lieu'],true) &&
               abs($balance-(float)($r['quantity']??0))>0.000001) {
                $issues[]='Row '.$r['line'].' cash corporate action does not fully reconcile outstanding shares.';
                continue;
            }
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
            $feeEvent=it_rc_event($r,'fee',0,abs((float)$r['amount']));
            if(!empty($r['withheld_for']))$feeEvent['withheld_from_drip']=true;
            $events[]=$feeEvent;
        }
    }
    if(!$events)$issues[]='No importable investment activity remains after excluded cancellations.';
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
    $rows=array_values(array_filter(it_rc_reconcile_cancellations($stage['rows']),static fn($r)=>$r['ticker']===$ticker));
    if(!$rows)throw new InvalidArgumentException('Ticker not present in source file.');
    $available=[];$balance=0.0;$ratios=[];$sales=[];$issues=[];$warnings=[];
    foreach ($rows as $row) {
        $kind=$row['kind'];$id=$row['id'];
        if ($kind==='paired_credit' || $kind==='cancelled_trade' || $kind==='paired_surrender' || $kind==='dividend' || $kind==='other_income' || $kind==='fee')continue;
        if ($kind==='buy' || $kind==='reinvest' || $kind==='receipt') {
            if($kind==='receipt')$warnings[]='Row '.$row['line'].' '.$row['code'].' share credit adds '.(float)$row['quantity'].' shares with UNKNOWN cost basis and acquisition date.';
            $q=(float)($row['quantity']??0);
            if($q<=0){$issues[]='Row '.$row['line'].' missing purchase/receipt shares.';continue;}
            $available[$id]=['remaining'=>$q,'date'=>$row['date']];
            $balance+=$q;continue;
        }
        if($kind==='split_credit'){
            $mode=$row['corporate_action']??'';
            $reverse=in_array($mode,['reverse_split','cross_ticker_reverse','verified_rounded_split'],true);
            $verified=isset($row['ratio_n'],$row['ratio_d']);
            $roundUp=$mode==='verified_rounded_split';
            $ratio=$verified
                ?['n'=>(int)$row['ratio_n'],'d'=>(int)$row['ratio_d']]
                :($reverse
                    ?it_rc_reverse_split_guess($balance,(float)($row['credit']??0))
                    :it_rc_split_guess($balance,(float)($row['credit']??0)));
            if(!$ratio){$issues[]='Row '.$row['line'].' split ratio not unambiguously inferable from share credit.';continue;}
            $factor=$ratio['n']/$ratio['d'];
            $credit=(float)($row['credit']??0);
            $expected=$reverse?$balance*$factor:$balance*($factor-1);
            $validCredit=$roundUp
                ?abs(ceil($expected-0.0000000001)-$credit)<=0.00000001
                :abs($credit-$expected)<=($reverse?0.000050001:max(0.0002,abs($expected)*0.002));
            $openLots=array_keys(array_filter($available,static fn($l)=>($l['remaining']??0)>0.000000001));
            $requiresSingle=$roundUp || isset($row['reported_split_shares']);
            $surrender=$row['surrender_quantity']??null;
            if($balance<=0 || $factor<=0 || !$validCredit ||
               ($reverse && $ratio['n']>=$ratio['d']) ||
               ($reverse && $surrender!==null && abs((float)$surrender-$balance)>0.000050001) ||
               ($requiresSingle && count($openLots)!==1)) {
                $issues[]='Row '.$row['line'].' split source does not reconcile the pre-split position and credit.';
                continue;
            }
            $ratios[$id]=$ratio;
            $balance*=$factor;
            foreach($available as &$lot)$lot['remaining']*=$factor;
            unset($lot);
            if($requiresSingle){
                $balance=$credit;
                $available[$openLots[0]]['remaining']=$credit;
            }
            $warnings[]='Row '.$row['line'].' ratio '.$ratio['n'].':'.$ratio['d'].
                ($verified?' derived from independently published issuer terms':' inferred from brokerage credit').
                '; confirm tax-basis adjustments with your broker.';
            continue;
        }
        if($kind==='sell'){
            $needed=(float)($row['quantity']??0);
            if($needed<=0){$issues[]='Row '.$row['line'].' invalid sale quantity.';continue;}
            if(in_array($row['corporate_action']??'',['liquidation','cash_merger','cash_in_lieu'],true) &&
               abs($balance-$needed)>0.000001){
                $issues[]='Row '.$row['line'].' cash settlement shares differ from the outstanding position.';
                continue;
            }
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
    if(!$out['events'])return [
        'ready'=>false,'noop'=>true,'ticker'=>$ticker,
        'issues'=>['All source transactions canceled or excluded; nothing to import.'],
        'warnings'=>$warnings,'computed_shares'=>$balance,
    ];
    if(!$out['ready'])return ['ready'=>false,'ticker'=>$ticker,'issues'=>$out['issues'],'warnings'=>$warnings,'computed_shares'=>$balance];
    return ['ready'=>true,'stage'=>$new,'ticker'=>$ticker,'warnings'=>$warnings,'events'=>$out['events'],
        'computed_shares'=>$balance,'estimated'=>true,'issues'=>[]];
}


/**
 * Review-only AMC/APE evidence diagnostic.
 *
 * AMC's 2022/2023 SEC filings describe APE issuance, 1:10 AMC reverse split,
 * 1:10 APE conversion, and 1:7.5 litigation settlement. Source quantities
 * establish shares but NOT how the broker allocated the ORIGINAL AMC basis
 * to APE or subsequent settlement shares. Never treat SOFF/SDIV as free
 * purchases or MRGS as a cash sale.
 *
 * Matches public CUSIPs, issuer action dates, and a UNIQUE sequence of source
 * rows in a temporary read-only normalized draft; no user portfolio mutation.
 */
function it_rc_amc_ape_review_evidence(array $stage): ?array
{
    $required=[
        'APE distributed on 2022-08-22 (SOFF) from original AMC purchase lots',
        'AMC reverse split on 2023-08-24 (SPR), 1 share per 10',
        'APE to AMC conversion on 2023-08-25 (MRGS), 1 AMC per 10 APE',
        'AMC litigation settlement distribution on 2023-08-28 (SDIV), 1 AMC per 7.5 eligible post-split AMC',
    ];
    $patterns=[
        ['ticker'=>'APE','date'=>'2022-08-22','code'=>'SOFF','cusip'=>'00165C203','positive'=>true],
        ['ticker'=>'AMC','date'=>'2023-08-24','code'=>'SPR','cusip'=>'00165C104','positive'=>false],
        ['ticker'=>'AMC','date'=>'2023-08-24','code'=>'SPR','cusip'=>'00165C302','positive'=>true],
        ['ticker'=>'APE','date'=>'2023-08-25','code'=>'MRGS','cusip'=>'00165C203','positive'=>false],
        ['ticker'=>'AMC','date'=>'2023-08-25','code'=>'MRGS','cusip'=>'00165C302','positive'=>true],
        ['ticker'=>'AMC','date'=>'2023-08-28','code'=>'SDIV','cusip'=>'00165C302','positive'=>true],
    ];
    $rows=it_rc_reconcile_cancellations($stage['rows']);
    $matched=[];
    foreach($patterns as $pattern) {
        $hits=array_values(array_filter($rows,static function($row)use($pattern) {
            if(($row['ticker']??'')!==$pattern['ticker'] ||
               ($row['date']??'')!==$pattern['date'] ||
               ($row['code']??'')!==$pattern['code'] ||
               ($row['price']??null)!==null || ($row['amount']??null)!==null ||
               it_rc_source_cusip($row)!==$pattern['cusip'])return false;
            $qty=$row['quantity']??null;
            return $pattern['positive']
                ?is_numeric($qty)&&(float)$qty>0
                :$qty===null;
        }));
        if(count($hits)!==1)return null;
        $matched[]=$hits[0];
    }
    // Same-day source quantities are rounded by the broker to 4dp.
    // A mismatch is a genuine blocker, not evidence for a reconstructed lot.
    $oldAmc=$matched[1]['surrender_quantity']??null;
    $amcSplit=(float)$matched[2]['quantity'];
    $oldApe=$matched[3]['surrender_quantity']??null;
    $converted=(float)$matched[4]['quantity'];
    $settlement=(float)$matched[5]['quantity'];
    if($oldAmc!==null && abs((float)$oldAmc/10-$amcSplit)>0.000050001)return null;
    if($oldApe!==null && abs((float)$oldApe/10-$converted)>0.000050001)return null;
    if(abs($amcSplit/7.5-$settlement)>0.000050001)return null;
    return [
        'group'=>'AMC_APE_2022_2023',
        'linked_tickers'=>['AMC','APE'],
        'confirmed_source_steps'=>$required,
        'source_lines'=>array_map(static fn($row)=>(int)$row['line'],$matched),
        'source_quantities_match'=>true,
        'review_status'=>'broker_basis_evidence_required',
        'required_evidence'=>[
            'Broker-confirmed basis and inherited acquisition dates allocated to the 2022 APE distribution from the original AMC tax lots',
            'Broker-confirmed adjusted APE lot basis carried into AMC in the August 2023 conversion',
            'Basis allocation for August 2023 litigation settlement shares and confirmation of eligible original AMC lots',
        ],
    ];
}

/**
 * Inspect every symbol without mutating the permanent ledger.
 * All eligible stock events are built and replayed before authorization
 * to commit them in a single atomic portfolio update.
 */
function it_rc_batch_preview(array $stage,array $existing): array
{
    $symbols=it_rc_symbols($stage);
    // Linked AMC/APE security actions cannot be imported one symbol at a
    // time; supply an actionable evidence summary without weakening safety.
    $linked=it_rc_amc_ape_review_evidence($stage);
    $already=[];
    foreach ($existing as $event) {
        if(is_array($event) && empty($event['voided_at']) && isset($event['ticker']))
            $already[(string)$event['ticker']]=true;
    }
    $eligible=[];$blocked=[];$skipped=[];$all=[];
    foreach ($symbols as $symbol) {
        $ticker=$symbol['ticker'];
        $aliasSources=$symbol['source_tickers']??[$ticker];
        $known=isset($stage['completed'][$ticker])||isset($already[$ticker]);
        foreach($aliasSources as $original)
            if(isset($stage['completed'][$original])||isset($already[$original]))$known=true;
        if($known) {
            $skipped[]=['ticker'=>$ticker,'reason'=>'Previously imported or already has ledger records.'];
            continue;
        }
        try {
            $auto=it_rc_auto_review($stage,$ticker);
        }catch(Throwable $e){
            $blocked[]=['ticker'=>$ticker,'reason'=>'Source rows cannot be reconstructed: '.$e->getMessage()];
            continue;
        }
        if (!empty($auto['noop'])) {
            $skipped[]=['ticker'=>$ticker,'reason'=>'Canceled activity only; no ledger events to import.'];
            continue;
        }
        if (!$auto['ready']) {
            if($linked!==null && in_array($ticker,$linked['linked_tickers'],true)) {
                $blocked[]=[
                    'ticker'=>$ticker,
                    'reason'=>'Linked AMC/APE distribution, reverse split, conversion and stock settlement: source shares reconcile, but tax basis for the 2022 APE distribution and 2023 settlement must be documented before an accurate import.',
                    'related_tickers'=>$linked['linked_tickers'],
                    'group'=>$linked['group'],
                    'basis_status'=>$linked['review_status'],
                    'required_evidence'=>$linked['required_evidence'],
                    'source_lines'=>$linked['source_lines'],
                ];
            } else {
                $blocked[]=['ticker'=>$ticker,'reason'=>implode('; ',array_slice($auto['issues']??[],0,3))];
            }
            continue;
        }
        $events=$auto['events'];
        if(!$events) {
            $skipped[]=['ticker'=>$ticker,'reason'=>'No importable ledger events.'];
            continue;
        }
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
        'ready'=>count($eligible)>0 && count($all)>0,'eligible'=>$eligible,'blocked'=>$blocked,
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
        'note'=>'Robinhood row '.$r['line'].' ('.$r['code'].')'.
            (isset($r['source_ticker'])?' originally '.$r['source_ticker']:'').
            (isset($r['source_cash_line'])?' cash settlement row '.$r['source_cash_line']:'').
            ($kind==='price'?' execution price; NOT closing quote':''),
    ]);
    $ev['id']='rh'.$r['id'].($kind==='sell'?substr(hash('sha256',$lot),0,5):($kind==='price'?'quote':''));
    $ev['external_id']=$ev['id'];
    return $ev;
}
