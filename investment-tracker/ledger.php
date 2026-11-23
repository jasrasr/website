<?php
/**
 * File Name: ledger.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Deterministic split-aware performance and broker-reported tax-basis calculations.
 * Revision: 2.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-11-22
 * Changelog: 2.0.0 - Apply temporary validated market quote overlays to open positions, charts and XIRR without ledger mutation. 1.9.0 - Preserve source-rounded reverse split share credits and documented whole-share round-ups on a single intact acquisition lot. 1.8.0 - Treat source-paired liquidation and cash merger sale legs as one execution when evaluating FIFO ambiguity. 1.7.0 - Report gross/net DRIP and withheld tax separately, without charging withheld dollars twice in performance or XIRR. 1.6.0 - Track performance basis separately from broker-reported tax basis, accept per-lot basis adjustments and confirmed sale allocations, and retain warnings for aggregate-only 1099-B data. 1.5.4 - Recognize a single Robinhood sell liquidating every available purchase lot, avoiding false FIFO warnings while retaining partial-sale uncertainty. 1.5.1 - Recalculate closed-position status, return on purchases, holding duration, and real versus only nominal FIFO ambiguity from existing ledger records. 1.4.1 - Mark stock receipt holding period unknown instead of using credit date as tax acquisition. 1.4.0 - Preserve unpriced share receipts with unknown tax basis and suppress unreliable gain calculations. 1.3.0 - Track stock-lending income and fee charges separately from dividends and basis. 1.2.0 - Date valuations at their observed price and withhold results if more recent account activity lacks a quote. 1.1.1 - Isolate calendar-aware tax holding period with explicit one-year tests. 1.1.0 - Preserve original shares and unit cost through splits; expose adjusted basis, full-quantity P&L and tax holding periods. 1.0.0 - Initial lot replay, realized/unrealized returns, snapshots and XIRR.
 */
declare(strict_types=1);

function it_event(array $row): array
{
    $type = strtolower(trim((string)($row['type'] ?? '')));
    $ticker = strtoupper(trim((string)($row['ticker'] ?? '')));
    $date = trim((string)($row['date'] ?? ''));
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || $date > gmdate('Y-m-d')) {
        throw new InvalidArgumentException('Enter a valid transaction date no later than today.');
    }
    if (!preg_match('/^[A-Z0-9.^-]{1,14}$/D', $ticker)) {
        throw new InvalidArgumentException('Invalid ticker symbol.');
    }
    if (!in_array($type, ['buy','sell','dividend','reinvest','split','price','other_income','fee','receipt','basis_adjustment','sale_basis'], true)) {
        throw new InvalidArgumentException('Select a supported transaction type.');
    }
    $qty = it_number($row['quantity'] ?? 0, 0, 1000000000);
    $amount = it_number($row['amount'] ?? 0, 0, 100000000000);
    $price = it_number($row['price'] ?? 0, 0, 1000000000);
    $num = it_integer($row['numerator'] ?? 0);
    $den = it_integer($row['denominator'] ?? 0);
    if (in_array($type, ['buy','sell','reinvest'], true) && ($qty <= 0 || $amount <= 0)) {
        throw new InvalidArgumentException('This event needs positive shares and total dollars.');
    }
    if (in_array($type,['dividend','other_income','fee','basis_adjustment'],true) && $amount <= 0) throw new InvalidArgumentException('Income, fee and basis-adjustment amounts must be positive.');
    if ($type === 'receipt' && $qty <= 0) throw new InvalidArgumentException('Received shares must be positive.');
    if ($type === 'price' && $price <= 0) throw new InvalidArgumentException('Market price must be positive.');
    if ($type === 'split' && ($num < 1 || $den < 1 || $num > 10000 || $den > 10000 || $num === $den)) {
        throw new InvalidArgumentException('Provide a non-1:1 split ratio (such as 10 and 1).');
    }
    $lot = trim((string)($row['lot_id'] ?? ''));
    if (in_array($type,['sell','basis_adjustment'],true) && $lot === '') throw new InvalidArgumentException('Assign a specific purchase lot before recording this event.');
    if (strlen($lot) > 64) throw new InvalidArgumentException('Invalid lot reference.');
    $note = trim((string)($row['note'] ?? ''));
    if (strlen($note) > 240) throw new InvalidArgumentException('Notes must be 240 characters or fewer.');
    $reportedBasis=null;
    if($type==='sell' && array_key_exists('reported_cost_basis',$row) && $row['reported_cost_basis']!=='') {
        $reportedBasis=it_number($row['reported_cost_basis'],0,100000000000);
    }
    $allocationConfirmed=in_array($type,['sell','sale_basis'],true) && in_array($row['allocation_confirmed']??false,[true,1,'1','true','on'],true);
    $saleId=$type==='sale_basis'?trim((string)($row['sale_id']??'')):'';
    if($type==='sale_basis' && !preg_match('/^(?:[a-f0-9]{24}|rh[a-f0-9]{27})$/D',$saleId))
        throw new InvalidArgumentException('Select the existing sale row being corrected.');
    $basisDirection=$type==='basis_adjustment'?strtolower(trim((string)($row['basis_direction']??''))):'';
    if($type==='basis_adjustment' && !in_array($basisDirection,['increase','decrease'],true))
        throw new InvalidArgumentException('Choose whether the tax-basis adjustment increases or decreases basis.');
    return [
        'id' => bin2hex(random_bytes(12)), 'date' => $date, 'ticker' => $ticker,
        'type' => $type, 'quantity' => $qty, 'amount' => $amount,
        'price' => $price, 'numerator' => $num, 'denominator' => $den,
        'lot_id' => in_array($type,['sell','basis_adjustment'],true) ? $lot : '', 'note' => $note,
        'reported_cost_basis'=>$reportedBasis,'allocation_confirmed'=>$allocationConfirmed,
        'allocation_estimated'=>$type==='sell' && !$allocationConfirmed,
        'basis_direction'=>$basisDirection,'sale_id'=>$saleId,
    ];
}

function it_number(mixed $v, float $min, float $max): float
{
    if (!is_numeric($v) || !is_finite((float)$v) || (float)$v < $min || (float)$v > $max) {
        throw new InvalidArgumentException('Enter finite nonnegative numeric values within range.');
    }
    return (float)$v;
}
function it_integer(mixed $v): int
{
    if (!is_numeric($v) || (string)(int)$v !== (string)$v && (string)(int)$v !== (string)(float)$v) {
        throw new InvalidArgumentException('Split components must be integers.');
    }
    return (int)$v;
}
function it_xirr(array $flows): ?float
{
    if (count($flows) < 2) return null;
    $hasPos = false; $hasNeg = false;
    foreach ($flows as $f) { $hasPos = $hasPos || $f[1] > 0; $hasNeg = $hasNeg || $f[1] < 0; }
    if (!$hasPos || !$hasNeg) return null;
    usort($flows, static fn($a,$b) => strcmp($a[0],$b[0]));
    $start = strtotime($flows[0][0] . ' UTC');
    $npv = static function (float $r) use ($flows,$start): float {
        $sum=0.0;
        foreach ($flows as $f) {
            $days=(strtotime($f[0].' UTC')-$start)/86400;
            $sum += $f[1] / pow(1+$r,$days/365.25);
        }
        return $sum;
    };
    $low=-0.99999; $high=1.0;
    $a=$npv($low); $b=$npv($high);
    for ($j=0;$j<25 && $a*$b>0;$j++) { $high=$high*2+1; $b=$npv($high); }
    if (!is_finite($a) || !is_finite($b) || $a*$b>0) return null;
    for ($i=0;$i<120;$i++) {
        $mid=($low+$high)/2; $m=$npv($mid);
        if (abs($m)<0.00000001) return $mid;
        if (($a<0) === ($m<0)) { $low=$mid; $a=$m; }
        else { $high=$mid; $b=$m; }
    }
    return ($low+$high)/2;
}

/**
 * Standard publicly traded share holding-period indicator:
 * begins day after trade date; more than one year is long-term.
 * Corporate splits inherit the original lot's acquisition date.
 */
function it_holding_period(string $acquiredDate, string $asOfDate): array
{
    $acquired=new DateTimeImmutable($acquiredDate,new DateTimeZone('UTC'));
    $asOf=new DateTimeImmutable($asOfDate,new DateTimeZone('UTC'));
    if ($asOf < $acquired) throw new InvalidArgumentException('Holding date precedes acquisition.');
    $anniversary=$acquired->modify('+1 year');
    if ($acquired->format('m-d')==='02-29' && $anniversary->format('m-d')==='03-01') {
        $anniversary=$anniversary->modify('last day of February');
    }
    $firstLongTerm=$anniversary->modify('+1 day');
    $interval=$acquired->diff($asOf);
    return [
        'days_held'=>(int)$interval->format('%a'),
        'held_for'=>sprintf('%dy %dm %dd',$interval->y,$interval->m,$interval->d),
        'long_term_on'=>$firstLongTerm->format('Y-m-d'),
        'tax_term'=>$asOf >= $firstLongTerm?'long-term':'short-term',
        'days_to_long_term'=>$asOf >= $firstLongTerm?0:(int)$asOf->diff($firstLongTerm)->format('%a')
    ];
}

/**
 * Robinhood's importer splits one broker SELL execution into per-purchase-lot
 * ledger rows: rh + 22-hex source ID + 5-hex lot suffix. Group only these
 * audited rows, never unrelated same-day sales or user-entered events.
 * This is a read-only derived grouping; existing saved events are unchanged.
 */
function it_sale_execution_key(array $event): string
{
    $id=(string)($event['id']??'');
    if(($event['type']??'')==='sell' &&
       preg_match('/^rh([a-f0-9]{22})[a-f0-9]{5}$/D',$id,$match) &&
       preg_match('/^Robinhood row [0-9]+ \((?:Sell|LIQ|MRGC|SPR)\)/D',(string)($event['note']??''))) {
        return 'rh|'.$event['ticker'].'|'.$event['date'].'|'.$match[1];
    }
    // Other sale events stand alone; never group independent orders by date.
    return 'single|'.$id;
}

function it_calculate(array $events,array $marketQuotes=[]): array
{
    // Immutable sale-basis records overlay an earlier sale during replay. This
    // lets a later broker statement correct basis/confirmation without editing
    // or deleting the imported source transaction.
    $saleIndexes=[];$corrections=[];
    foreach($events as $i=>$event) {
        if(!is_array($event))throw new InvalidArgumentException('Invalid ledger record.');
        if(($event['type']??'')==='sell')$saleIndexes[(string)($event['id']??'')]=$i;
        elseif(($event['type']??'')==='sale_basis')$corrections[]=$event;
    }
    $corrected=[];
    foreach($corrections as $correction) {
        $target=(string)($correction['sale_id']??'');
        if(!isset($saleIndexes[$target]))throw new InvalidArgumentException('Sale-basis correction target is missing.');
        if(isset($corrected[$target]))throw new InvalidArgumentException('Void the earlier sale-basis correction before recording a replacement.');
        $sale=$events[$saleIndexes[$target]];
        if(($sale['ticker']??'')!==($correction['ticker']??'') || ($correction['date']??'')<($sale['date']??''))
            throw new InvalidArgumentException('Sale-basis correction must match the ticker and occur no earlier than the sale.');
        $correctionBasis=$correction['amount']??null;
        if(!is_numeric($correctionBasis) || !is_finite((float)$correctionBasis) || (float)$correctionBasis<0 || (float)$correctionBasis>100000000000)
            throw new InvalidArgumentException('Sale-basis correction must contain a finite nonnegative reported basis.');
        $events[$saleIndexes[$target]]['reported_cost_basis']=(float)$correctionBasis;
        $events[$saleIndexes[$target]]['allocation_confirmed']=!empty($correction['allocation_confirmed']);
        if(empty($correction['allocation_confirmed']))
            $events[$saleIndexes[$target]]['allocation_estimated']=true;
        $corrected[$target]=true;
    }
    $ordered=[];
    foreach ($events as $i=>$event) {
        if(($event['type']??'')==='sale_basis')continue;
        $ordered[]=$event+['_order'=>$i];
    }
    usort($ordered,static fn($a,$b)=>strcmp((string)$a['date'],(string)$b['date'])
        ?: (($a['type']==='split'?0:1)<=>($b['type']==='split'?0:1))
        ?: ($a['_order']<=>$b['_order']));
    // Calculate the total quantity of each original Robinhood SELL before
    // replaying its individual lot allocations. A sale of ALL outstanding
    // shares cannot have competing FIFO selections, regardless of lot count.
    $saleExecutionShares=[];$saleExecutionFullExit=[];
    foreach($ordered as $salePart) {
        if(($salePart['type']??'')!=='sell' || empty($salePart['allocation_estimated']))continue;
        $key=it_sale_execution_key($salePart);
        $saleExecutionShares[$key]=($saleExecutionShares[$key]??0.0)+(float)$salePart['quantity'];
    }
    $lots=[]; $sales=[]; $quotes=[]; $series=[]; $cash=[]; $divs=[]; $reinvested=[]; $reinvestedGross=[]; $dripWithholding=[]; $cashFees=[]; $purchases=[]; $proceeds=[]; $realized=[]; $taxRealized=[]; $otherIncome=[]; $fees=[]; $latestActivity=[]; $basisUncertain=[]; $estimated=[]; $saleAllocationAmbiguous=[]; $taxBasisUncertain=[]; $brokerBasisReported=[]; $confirmedSaleAllocation=[]; $purchaseLotCount=[]; $saleEventCount=[]; $firstCashPurchase=[]; $lastSaleDate=[];
    foreach ($ordered as $e) {
        $t=(string)$e['ticker']; $kind=(string)$e['type']; $q=(float)$e['quantity']; $a=(float)$e['amount'];
        $divs[$t]??=0.0; $reinvested[$t]??=0.0; $reinvestedGross[$t]??=0.0; $dripWithholding[$t]??=0.0; $cashFees[$t]??=0.0; $purchases[$t]??=0.0; $proceeds[$t]??=0.0; $realized[$t]??=0.0; $taxRealized[$t]??=0.0; $otherIncome[$t]??=0.0; $fees[$t]??=0.0; $cash[$t]??=[];
        // A tax-only basis adjustment does not change shares or market value,
        // so it must not invalidate an otherwise current price observation.
        if (!in_array($kind,['price','basis_adjustment'],true))
            $latestActivity[$t]=max($latestActivity[$t]??$e['date'],$e['date']);
        if(!empty($e['allocation_estimated']))$estimated[$t]=true;
        if ($kind==='buy' || $kind==='reinvest' || $kind==='receipt') {
            $purchaseLotCount[$t]=($purchaseLotCount[$t]??0)+1;
            if ($kind==='buy') {
                $firstCashPurchase[$t]??=$e['date']; $purchases[$t]+=$a; $cash[$t][]=[$e['date'],-$a]; }
            elseif($kind==='reinvest') {
                $gross=(float)($e['gross_dividend']??$a);
                if(!is_finite($gross) || $gross<$a || $gross>100000000000)
                    throw new InvalidArgumentException('Gross reinvested dividend is invalid.');
                $reinvested[$t]+=$a;
                $reinvestedGross[$t]+=$gross;
            }
            $lots[$e['id']]=[
                'id'=>$e['id'],'ticker'=>$t,'date'=>$e['date'],'source'=>$kind,
                'original_shares'=>$q,'shares'=>$q,'original_cost'=>$a,'remaining_cost'=>$a,
                'original_tax_cost'=>$kind==='receipt'?null:$a,'remaining_tax_cost'=>$kind==='receipt'?null:$a,
                'tax_basis_adjustments'=>0.0,
                'original_unit_cost'=>$kind==='receipt'?null:$a/$q,'split_factor'=>1.0,
                'basis_unknown'=>$kind==='receipt',
                'realized'=>0.0, 'sold_shares'=>0.0,
            ];
        } elseif ($kind==='split') {
            $factor=(float)$e['numerator']/(float)$e['denominator'];
            unset($quotes[$t]);
            $activeLotIds=[];$beforeShares=0.0;
            foreach($lots as $lotId=>$existingLot){
                if($existingLot['ticker']===$t && $existingLot['shares']>0.000000001){
                    $activeLotIds[]=$lotId;$beforeShares+=(float)$existingLot['shares'];
                }
            }
            $reported=array_key_exists('reported_split_shares',$e)?(float)$e['reported_split_shares']:null;
            if($reported!==null){
                if(count($activeLotIds)!==1 || $reported<=0 || !is_finite($reported) ||
                   !empty($lots[$activeLotIds[0]]['sold_shares']))
                    throw new InvalidArgumentException('A broker-rounded split requires one intact open acquisition lot.');
                $expected=$beforeShares*$factor;
                $valid=!empty($e['split_round_up'])
                    ?abs(ceil($expected-0.0000000001)-$reported)<=0.00000001
                    :abs($expected-$reported)<=0.000050001;
                if(!$valid)throw new InvalidArgumentException('Reported split credit disagrees with ratio and shares.');
            }
            foreach ($lots as &$lot) {
                if ($lot['ticker']===$t) {
                    $lot['shares']*=$factor;
                    $lot['split_factor']*=$factor;
                    $lot['sold_shares']*=$factor;
                }
            }
            unset($lot);
            if($reported!==null){
                $id=$activeLotIds[0];
                $lots[$id]['shares']=$reported;
                // An actual whole-share round-up or 4dp brokerage credit
                // changes adjusted shares, not original cash or tax basis.
                $lots[$id]['split_factor']=$reported/$lots[$id]['original_shares'];
            }
        } elseif ($kind==='basis_adjustment') {
            $id=(string)$e['lot_id'];
            if(!isset($lots[$id]) || $lots[$id]['ticker']!==$t || !empty($lots[$id]['basis_unknown']) || $lots[$id]['shares']<=0.000000001)
                throw new InvalidArgumentException('Tax-basis adjustment does not match a known-basis purchase lot.');
            $change=$e['basis_direction']==='increase'?$a:-$a;
            $next=(float)$lots[$id]['remaining_tax_cost']+$change;
            if($next < -0.0000001)
                throw new InvalidArgumentException('Tax-basis adjustment would reduce the selected lot below zero.');
            $lots[$id]['remaining_tax_cost']=max(0,$next);
            $lots[$id]['tax_basis_adjustments']+=$change;
            $brokerBasisReported[$t]=true;
        } elseif ($kind==='sell') {
            $id=(string)$e['lot_id'];
            if (!isset($lots[$id]) || $lots[$id]['ticker']!==$t || $lots[$id]['shares']+0.000000001<$q) {
                throw new InvalidArgumentException('Sale on '.$e['date'].' exceeds or does not match the selected lot.');
            }
            // The importer splits one broker sell across multiple lot rows.
            // ACB: one sale of 2 shares covers both 1-share purchase lots;
            // although two lots were open, there is no alternative allocation.
            // Evaluate a source sale ONCE before its first lot leg is applied.
            $currentSaleAmbiguous=false;
            if (!empty($e['allocation_estimated']) && empty($e['allocation_confirmed'])) {
                $group=it_sale_execution_key($e);
                $eligibleOpenLots=0;$availableShares=0.0;
                foreach($lots as $candidate){
                    if($candidate['ticker']===$t && $candidate['shares']>0.000000001){
                        $eligibleOpenLots++;
                        $availableShares+=$candidate['shares'];
                    }
                }
                if(!array_key_exists($group,$saleExecutionFullExit)) {
                    $soldShares=$saleExecutionShares[$group]??$q;
                    $tolerance=max(0.0000000001,$availableShares*0.00000000001);
                    $saleExecutionFullExit[$group]=abs($availableShares-$soldShares)<=$tolerance;
                }
                if($eligibleOpenLots>1 && !$saleExecutionFullExit[$group]) {
                    $saleAllocationAmbiguous[$t]=true;
                    $currentSaleAmbiguous=true;
                }
            }
            $saleEventCount[$t]=($saleEventCount[$t]??0)+1;
            $lastSaleDate[$t]=$e['date'];
            $before=$lots[$id]['shares'];
            $basis=$lots[$id]['remaining_cost']*($q/$before);
            $proportionalTaxBasis=!empty($lots[$id]['basis_unknown'])?null:
                (float)$lots[$id]['remaining_tax_cost']*($q/$before);
            $reportedTaxBasis=array_key_exists('reported_cost_basis',$e) && $e['reported_cost_basis']!==null
                ?(float)$e['reported_cost_basis']:null;
            if($reportedTaxBasis!==null && $currentSaleAmbiguous)$taxBasisUncertain[$t]=true;
            $taxBasis=$reportedTaxBasis??$proportionalTaxBasis;
            if($taxBasis!==null && $taxBasis>(float)$lots[$id]['remaining_tax_cost']+0.0000001)
                throw new InvalidArgumentException('Reported sale basis exceeds the selected lot tax basis; record a basis increase first.');
            if(!empty($lots[$id]['basis_unknown']))$basisUncertain[$t]=true;
            $lots[$id]['shares']=max(0,$before-$q);
            $lots[$id]['remaining_cost']=max(0,$lots[$id]['remaining_cost']-$basis);
            if($taxBasis!==null)$lots[$id]['remaining_tax_cost']=max(0,(float)$lots[$id]['remaining_tax_cost']-$taxBasis);
            if($lots[$id]['shares']<=0.000000001)$lots[$id]['remaining_tax_cost']=0.0;
            $lots[$id]['sold_shares']+=$q;
            $gain=$a-$basis;
            $taxGain=$taxBasis===null?null:$a-$taxBasis;
            $lots[$id]['realized']+=$gain; $realized[$t]+=$gain; $proceeds[$t]+=$a;
            if($taxGain!==null)$taxRealized[$t]+=$taxGain;
            if($reportedTaxBasis!==null)$brokerBasisReported[$t]=true;
            if(!empty($e['allocation_confirmed']))$confirmedSaleAllocation[$t]=true;
            $sales[]=['id'=>$e['id'],'lot_id'=>$id,'lot_date'=>$lots[$id]['date'],'ticker'=>$t,'date'=>$e['date'],'quantity'=>$q,'proceeds'=>$a,'cost'=>$basis,'gain'=>$gain,
                'tax_cost'=>$taxBasis,'tax_gain'=>$taxGain,'reported_cost_basis'=>$reportedTaxBasis,'allocation_confirmed'=>!empty($e['allocation_confirmed'])];
            $cash[$t][]=[$e['date'],$a];
        } elseif ($kind==='dividend') {
            $divs[$t]+=$a; $cash[$t][]=[$e['date'],$a];
        } elseif ($kind==='other_income') {
            $otherIncome[$t]+=$a; $cash[$t][]=[$e['date'],$a];
        } elseif ($kind==='fee') {
            $fees[$t]+=$a;
            if(!empty($e['withheld_from_drip'])) {
                // Gross dividend minus withholding was already used to
                // purchase fewer shares. Reporting this tax is necessary,
                // but charging it again would understate total return/XIRR.
                $dripWithholding[$t]+=$a;
            } else {
                $cashFees[$t]+=$a;
                $cash[$t][]=[$e['date'],-$a];
            }
        } elseif ($kind==='price') {
            $quotes[$t]=['date'=>$e['date'],'price'=>(float)$e['price']];
            $snapshot=[];
            $cost=0.0; $value=0.0;
            foreach ($lots as $lot) {
                if ($lot['ticker']!==$t || $lot['shares']<=0.0000000001) continue;
                $mv=$lot['shares']*(float)$e['price'];
                $snapshot[]=['id'=>$lot['id'],'value'=>$mv,'gain'=>!empty($lot['basis_unknown'])?null:$mv-$lot['remaining_cost'],'cost'=>!empty($lot['basis_unknown'])?null:$lot['remaining_cost']];
                $cost+=$lot['remaining_cost']; $value+=$mv;
            }
            $series[$t][]=['date'=>$e['date'],'price'=>(float)$e['price'],'value'=>$value,'cost'=>$cost,'lots'=>$snapshot];
        }
    }
    // Overlay external observations only for OPEN positions. They are not
    // ledger transactions, never modify cost basis or tax lots, and never
    // write an event to the owner's private permanent portfolio.
    $openTickers=[];
    foreach($lots as $lot)if($lot['shares']>0.000000001)$openTickers[$lot['ticker']]=true;
    foreach($marketQuotes as $ticker=>$quote){
        if(!isset($openTickers[$ticker]) || !isset($purchases[$ticker]) ||
           !is_array($quote) || ($quote['source']??'')!=='Twelve Data' ||
           ($quote['currency']??'')!=='USD' ||
           !is_numeric($quote['price']??null) ||
           !is_finite((float)$quote['price']) ||
           (float)$quote['price']<=0 || (float)$quote['price']>1000000000 ||
           !is_string($quote['as_of']??null))continue;
        $timestamp=strtotime($quote['as_of']);
        if(!$timestamp || $timestamp>time()+300 || time()-$timestamp>604800)continue;
        $date=gmdate('Y-m-d',$timestamp);
        if($date<($latestActivity[$ticker]??'0000-00-00') ||
           (isset($quotes[$ticker]) && $date<$quotes[$ticker]['date']))continue;
        $price=(float)$quote['price'];
        $quotes[$ticker]=[
            'price'=>$price,'date'=>$date,'source'=>'Twelve Data',
            'as_of'=>gmdate(DATE_ATOM,$timestamp),'fetched_at'=>$quote['fetched_at']??null,
            'stale'=>!empty($quote['stale']),
        ];
        // Add only a read-only point to the returned chart; never insert
        // it into historical source events or persist it as a manual quote.
        $snapshot=[];$totalBasis=0.0;$marketValue=0.0;
        foreach($lots as $lot){
            if($lot['ticker']!==$ticker || $lot['shares']<=0.000000001)continue;
            $value=$lot['shares']*$price;
            $snapshot[]=[
                'id'=>$lot['id'],'value'=>$value,
                'gain'=>!empty($lot['basis_unknown'])?null:$value-$lot['remaining_cost'],
                'cost'=>!empty($lot['basis_unknown'])?null:$lot['remaining_cost'],
            ];
            $marketValue+=$value;$totalBasis+=$lot['remaining_cost'];
        }
        $series[$ticker]??=[];
        $lastPoint=$series[$ticker] ? end($series[$ticker])['date']:null;
        $point=['date'=>$date,'price'=>$price,
                'value'=>$marketValue,'cost'=>$totalBasis,'lots'=>$snapshot];
        if($lastPoint===null || $date>$lastPoint)$series[$ticker][]=$point;
        elseif($date===$lastPoint)$series[$ticker][count($series[$ticker])-1]=$point;
    }
    $tickers=array_values(array_unique(array_merge(array_keys($purchases),array_keys($quotes))));
    sort($tickers);
    $holdings=[];$missing=[];$totalValue=0.;$allFlows=[];$net=0.;$uncertain=[];
    foreach ($tickers as $t) {
        $price=$quotes[$t]['price']??null;
        $priceUsable=$price!==null && ($quotes[$t]['date']??'') >= ($latestActivity[$t]??'');
        $quoteSource=$quotes[$t]['source']??'Recorded trade/manual observation';
        $quoteAsOf=$quotes[$t]['as_of']??null;
        $quoteStale=isset($quotes[$t]) &&
            ($quoteSource==='Twelve Data'
                ? !empty($quotes[$t]['stale'])
                : (time()-(strtotime($quotes[$t]['date'].' 23:59:59 UTC')?:0)>259200));
        $qty=0.;$basis=0.;$taxBasis=0.;$currentLots=[];
        $unknownBasis=!empty($basisUncertain[$t]);
        foreach ($lots as $lot) {
            if ($lot['ticker']!==$t) continue;
            if ($lot['shares']>0.000000001) { $qty+=$lot['shares']; $basis+=$lot['remaining_cost']; $taxBasis+=(float)($lot['remaining_tax_cost']??0); if(!empty($lot['basis_unknown']))$unknownBasis=true; }
            $market=$priceUsable ? $lot['shares']*$price : null;
            $lot['market_value']=$market;
            $lot['unrealized']=($market===null||!empty($lot['basis_unknown']))?null:$market-$lot['remaining_cost'];
            $lot['tax_unrealized']=($market===null||!empty($lot['basis_unknown']))?null:$market-(float)$lot['remaining_tax_cost'];
            $lot['tax_basis_uncertain']=!empty($taxBasisUncertain[$t]);
            $lot['adjusted_acquired_shares']=$lot['original_shares']*$lot['split_factor'];
            $lot['adjusted_unit_cost']=$lot['adjusted_acquired_shares']>0
                ?(!empty($lot['basis_unknown'])?null:$lot['original_cost']/$lot['adjusted_acquired_shares']):null;
            $lot['remaining_unit_cost']=$lot['shares']>0.000000001
                ?(!empty($lot['basis_unknown'])?null:$lot['remaining_cost']/$lot['shares']):null;
            $lot['remaining_tax_unit_cost']=$lot['shares']>0.000000001
                ?(!empty($lot['basis_unknown'])?null:(float)$lot['remaining_tax_cost']/$lot['shares']):null;
            if($lot['tax_basis_uncertain']) {
                $lot['remaining_tax_cost']=null;
                $lot['remaining_tax_unit_cost']=null;
                $lot['tax_unrealized']=null;
            }
            $lot['roi_pct']=($market!==null && empty($lot['basis_unknown']) && $lot['remaining_cost']>0)?100*($market/$lot['remaining_cost']-1):null;
            $holding=it_holding_period($lot['date'],gmdate('Y-m-d'));
            $age=$holding['days_held'];
            $valuationAge=$price===null?0:(int)((strtotime($quotes[$t]['date'].' UTC')-strtotime($lot['date'].' UTC'))/86400);
            $annualized=($market!==null && empty($lot['basis_unknown']) && $lot['remaining_cost']>0 && $lot['shares']>0 && $valuationAge>0)
                ?100*(pow($market/$lot['remaining_cost'],365.25/$valuationAge)-1):null;
            $lot['annualized_pct']=$annualized!==null && is_finite($annualized)?$annualized:null;
            if(!empty($lot['basis_unknown'])) {
                $holding['tax_term']='unknown';$holding['held_for']='Acquisition date unknown';
                $holding['long_term_on']=null;$holding['days_to_long_term']=null;
            }
            $lot=array_merge($lot,$holding);
            $currentLots[]=$lot;
        }
        $missingPrice=$qty>0.000000001 && !$priceUsable;
        if($unknownBasis)$uncertain[]=$t;
        if ($missingPrice) $missing[]=$t;
        $v=$qty<=0.000000001?0.0:($missingPrice?null:$qty*$price);
        if ($v!==null) $totalValue+=$v;
        if(abs(($reinvestedGross[$t]-$reinvested[$t])-$dripWithholding[$t])>0.00001)
            throw new InvalidArgumentException('Gross DRIP, withholding and reinvestment do not reconcile.');
        $cashGain=($v===null||$unknownBasis)?null:$v+$proceeds[$t]+$divs[$t]+$otherIncome[$t]-$cashFees[$t]-$purchases[$t];
        // Every view recomputes these values from the already-saved ledger.
        // Nothing is written back to a user's imported records or draft.
        $closed=$qty<=0.000000001 && $purchases[$t]>0 && ($saleEventCount[$t]??0)>0;
        $simpleClosed=$closed && !$unknownBasis && ($purchaseLotCount[$t]??0)===1
            && ($saleEventCount[$t]??0)===1 && isset($firstCashPurchase[$t],$lastSaleDate[$t]);
        $closedHolding=$simpleClosed
            ?it_holding_period($firstCashPurchase[$t],$lastSaleDate[$t]):null;
        $lifetimeReturnPct=$closed && !$unknownBasis && $purchases[$t]>0 && $cashGain!==null
            ?100*$cashGain/$purchases[$t]:null;
        $flows=$cash[$t];
        if ($v!==null && $qty>0) $flows[]=[$quotes[$t]['date'],$v];
        $holdings[$t]=[
            'ticker'=>$t,'shares'=>$qty,'basis'=>$unknownBasis?null:$basis,
            'tax_basis'=>($unknownBasis||!empty($taxBasisUncertain[$t]))?null:$taxBasis,'price'=>$price,
            'basis_unknown'=>$unknownBasis,'allocation_estimated'=>!empty($estimated[$t]),
            'sale_allocation_ambiguous'=>!empty($saleAllocationAmbiguous[$t]),
            'broker_basis_reported'=>!empty($brokerBasisReported[$t]),
            'confirmed_sale_allocation'=>!empty($confirmedSaleAllocation[$t]),
            'tax_basis_uncertain'=>!empty($taxBasisUncertain[$t]),
            'tax_basis_differs'=>!$unknownBasis && (!empty($taxBasisUncertain[$t]) || abs($taxBasis-$basis)>0.0000001 || abs($taxRealized[$t]-$realized[$t])>0.0000001),
            'closed'=>$closed,'simple_closed_trade'=>$simpleClosed,
            'first_purchased_on'=>$firstCashPurchase[$t]??null,
            'closed_on'=>$closed?$lastSaleDate[$t]:null,
            'closed_holding_days'=>$closedHolding['days_held']??null,
            'closed_tax_term'=>$closedHolding['tax_term']??null,
            'closed_total_return_pct'=>$lifetimeReturnPct,
            'price_date'=>$quotes[$t]['date']??null,'price_source'=>$quoteSource,
            'price_as_of'=>$quoteAsOf,'price_stale'=>$quoteStale,
            'valuation_stale_or_incomplete'=>$missingPrice||$quoteStale,
            'value'=>$v,'unrealized'=>($v===null||$unknownBasis)?null:$v-$basis,
            'realized'=>$unknownBasis?null:$realized[$t],'tax_realized'=>$unknownBasis?null:$taxRealized[$t],
            'tax_unrealized'=>($v===null||$unknownBasis||!empty($taxBasisUncertain[$t]))?null:$v-$taxBasis,
            'dividends_cash'=>$divs[$t],'dividends_reinvested'=>$reinvested[$t],
            'dividends_reinvested_gross'=>$reinvestedGross[$t],
            'dividend_withholding'=>$dripWithholding[$t],
            'other_income'=>$otherIncome[$t],'fees'=>$fees[$t],
            'purchases'=>$purchases[$t],'sales_proceeds'=>$proceeds[$t],
            'lifetime_profit'=>$cashGain,'xirr_pct'=>($closed||$missingPrice||$unknownBasis||!$flows)?null:(($x=it_xirr($flows))===null?null:$x*100),
            'lots'=>$currentLots,
        ];
        foreach ($cash[$t] as $f) $allFlows[]=$f;
        if ($v!==null && $qty>0) $allFlows[]=[$quotes[$t]['date'],$v];
        $net += $proceeds[$t]+$divs[$t]+$otherIncome[$t]-$cashFees[$t]-$purchases[$t];
    }
    return [
        'holdings'=>$holdings,'series'=>$series,'sales'=>$sales,'missing_prices'=>$missing,'unknown_basis_tickers'=>$uncertain,
        'totals'=>[
            'market_value'=>count($missing)?null:$totalValue,
            'lifetime_profit'=>count($missing)||count($uncertain)?null:$totalValue+$net,
            'unrealized'=>count($missing)||count($uncertain)?null:array_sum(array_map(static fn($h)=>$h['unrealized']??0,$holdings)),
            'realized'=>count($uncertain)?null:array_sum($realized),'tax_realized'=>count($uncertain)?null:array_sum($taxRealized),'dividends_cash'=>array_sum($divs),
            'other_income'=>array_sum($otherIncome),'fees'=>array_sum($fees),
            'dividends_reinvested'=>array_sum($reinvested),
            'dividends_reinvested_gross'=>array_sum($reinvestedGross),
            'dividend_withholding'=>array_sum($dripWithholding),
            'xirr_pct'=>count($missing)||count($uncertain)?null:(($x=it_xirr($allFlows))===null?null:$x*100)
        ],
    ];
}
