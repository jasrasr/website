<?php
/**
 * File Name: ledger.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Deterministic split-aware per-purchase tax-lot and performance calculations.
 * Revision: 1.2.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.2.0 - Date valuations at their observed price and withhold results if more recent account activity lacks a quote. 1.1.1 - Isolate calendar-aware tax holding period with explicit one-year tests. 1.1.0 - Preserve original shares and unit cost through splits; expose adjusted basis, full-quantity P&L and tax holding periods. 1.0.0 - Initial lot replay, realized/unrealized returns, snapshots and XIRR.
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
    if (!in_array($type, ['buy','sell','dividend','reinvest','split','price'], true)) {
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
    if ($type === 'dividend' && $amount <= 0) throw new InvalidArgumentException('Dividend dollars must be positive.');
    if ($type === 'price' && $price <= 0) throw new InvalidArgumentException('Market price must be positive.');
    if ($type === 'split' && ($num < 1 || $den < 1 || $num > 10000 || $den > 10000 || $num === $den)) {
        throw new InvalidArgumentException('Provide a non-1:1 split ratio (such as 10 and 1).');
    }
    $lot = trim((string)($row['lot_id'] ?? ''));
    if ($type === 'sell' && $lot === '') throw new InvalidArgumentException('Assign a specific purchase lot before recording a sale.');
    if (strlen($lot) > 64) throw new InvalidArgumentException('Invalid lot reference.');
    $note = trim((string)($row['note'] ?? ''));
    if (strlen($note) > 240) throw new InvalidArgumentException('Notes must be 240 characters or fewer.');
    return [
        'id' => bin2hex(random_bytes(12)), 'date' => $date, 'ticker' => $ticker,
        'type' => $type, 'quantity' => $qty, 'amount' => $amount,
        'price' => $price, 'numerator' => $num, 'denominator' => $den,
        'lot_id' => $type === 'sell' ? $lot : '', 'note' => $note,
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

function it_calculate(array $events): array
{
    $ordered=[];
    foreach ($events as $i=>$event) {
        if (!is_array($event)) throw new InvalidArgumentException('Invalid ledger record.');
        $ordered[]=$event+['_order'=>$i];
    }
    usort($ordered,static fn($a,$b)=>strcmp((string)$a['date'],(string)$b['date'])
        ?: (($a['type']==='split'?0:1)<=>($b['type']==='split'?0:1))
        ?: ($a['_order']<=>$b['_order']));
    $lots=[]; $sales=[]; $quotes=[]; $series=[]; $cash=[]; $divs=[]; $reinvested=[]; $purchases=[]; $proceeds=[]; $realized=[]; $latestActivity=[];
    foreach ($ordered as $e) {
        $t=(string)$e['ticker']; $kind=(string)$e['type']; $q=(float)$e['quantity']; $a=(float)$e['amount'];
        $divs[$t]??=0.0; $reinvested[$t]??=0.0; $purchases[$t]??=0.0; $proceeds[$t]??=0.0; $realized[$t]??=0.0; $cash[$t]??=[];
        if ($kind!=='price') $latestActivity[$t]=max($latestActivity[$t]??$e['date'],$e['date']);
        if ($kind==='buy' || $kind==='reinvest') {
            if ($kind==='buy') { $purchases[$t]+=$a; $cash[$t][]=[$e['date'],-$a]; }
            else $reinvested[$t]+=$a;
            $lots[$e['id']]=[
                'id'=>$e['id'],'ticker'=>$t,'date'=>$e['date'],'source'=>$kind,
                'original_shares'=>$q,'shares'=>$q,'original_cost'=>$a,'remaining_cost'=>$a,
                'original_unit_cost'=>$a/$q,'split_factor'=>1.0,
                'realized'=>0.0, 'sold_shares'=>0.0,
            ];
        } elseif ($kind==='split') {
            $factor=(float)$e['numerator']/(float)$e['denominator'];
            unset($quotes[$t]);
            foreach ($lots as &$lot) {
                if ($lot['ticker']===$t) {
                    $lot['shares']*=$factor;
                    $lot['split_factor']*=$factor;
                    $lot['sold_shares']*=$factor;
                }
            }
            unset($lot);
        } elseif ($kind==='sell') {
            $id=(string)$e['lot_id'];
            if (!isset($lots[$id]) || $lots[$id]['ticker']!==$t || $lots[$id]['shares']+0.000000001<$q) {
                throw new InvalidArgumentException('Sale on '.$e['date'].' exceeds or does not match the selected lot.');
            }
            $before=$lots[$id]['shares'];
            $basis=$lots[$id]['remaining_cost']*($q/$before);
            $lots[$id]['shares']=max(0,$before-$q);
            $lots[$id]['remaining_cost']=max(0,$lots[$id]['remaining_cost']-$basis);
            $lots[$id]['sold_shares']+=$q;
            $gain=$a-$basis;
            $lots[$id]['realized']+=$gain; $realized[$t]+=$gain; $proceeds[$t]+=$a;
            $sales[]=['id'=>$e['id'],'lot_id'=>$id,'ticker'=>$t,'date'=>$e['date'],'quantity'=>$q,'proceeds'=>$a,'cost'=>$basis,'gain'=>$gain];
            $cash[$t][]=[$e['date'],$a];
        } elseif ($kind==='dividend') {
            $divs[$t]+=$a; $cash[$t][]=[$e['date'],$a];
        } elseif ($kind==='price') {
            $quotes[$t]=['date'=>$e['date'],'price'=>(float)$e['price']];
            $snapshot=[];
            $cost=0.0; $value=0.0;
            foreach ($lots as $lot) {
                if ($lot['ticker']!==$t || $lot['shares']<=0.0000000001) continue;
                $mv=$lot['shares']*(float)$e['price'];
                $snapshot[]=['id'=>$lot['id'],'value'=>$mv,'gain'=>$mv-$lot['remaining_cost'],'cost'=>$lot['remaining_cost']];
                $cost+=$lot['remaining_cost']; $value+=$mv;
            }
            $series[$t][]=['date'=>$e['date'],'price'=>(float)$e['price'],'value'=>$value,'cost'=>$cost,'lots'=>$snapshot];
        }
    }
    $tickers=array_values(array_unique(array_merge(array_keys($purchases),array_keys($quotes))));
    sort($tickers);
    $holdings=[];$missing=[];$totalValue=0.;$allFlows=[];$net=0.;
    foreach ($tickers as $t) {
        $price=$quotes[$t]['price']??null;
        $priceUsable=$price!==null && ($quotes[$t]['date']??'') >= ($latestActivity[$t]??'');
        $qty=0.;$basis=0.;$currentLots=[];
        foreach ($lots as $lot) {
            if ($lot['ticker']!==$t) continue;
            if ($lot['shares']>0.000000001) { $qty+=$lot['shares']; $basis+=$lot['remaining_cost']; }
            $market=$priceUsable ? $lot['shares']*$price : null;
            $lot['market_value']=$market;
            $lot['unrealized']=$market===null?null:$market-$lot['remaining_cost'];
            $lot['adjusted_acquired_shares']=$lot['original_shares']*$lot['split_factor'];
            $lot['adjusted_unit_cost']=$lot['adjusted_acquired_shares']>0
                ?$lot['original_cost']/$lot['adjusted_acquired_shares']:null;
            $lot['remaining_unit_cost']=$lot['shares']>0.000000001
                ?$lot['remaining_cost']/$lot['shares']:null;
            $lot['roi_pct']=($market!==null && $lot['remaining_cost']>0)?100*($market/$lot['remaining_cost']-1):null;
            $holding=it_holding_period($lot['date'],gmdate('Y-m-d'));
            $age=$holding['days_held'];
            $valuationAge=$price===null?0:(int)((strtotime($quotes[$t]['date'].' UTC')-strtotime($lot['date'].' UTC'))/86400);
            $annualized=($market!==null && $lot['remaining_cost']>0 && $lot['shares']>0 && $valuationAge>0)
                ?100*(pow($market/$lot['remaining_cost'],365.25/$valuationAge)-1):null;
            $lot['annualized_pct']=$annualized!==null && is_finite($annualized)?$annualized:null;
            $lot=array_merge($lot,$holding);
            $currentLots[]=$lot;
        }
        $missingPrice=$qty>0.000000001 && !$priceUsable;
        if ($missingPrice) $missing[]=$t;
        $v=$qty<=0.000000001?0.0:($missingPrice?null:$qty*$price);
        if ($v!==null) $totalValue+=$v;
        $cashGain=$v===null?null:$v+$proceeds[$t]+$divs[$t]-$purchases[$t];
        $flows=$cash[$t];
        if ($v!==null && $qty>0) $flows[]=[$quotes[$t]['date'],$v];
        $holdings[$t]=[
            'ticker'=>$t,'shares'=>$qty,'basis'=>$basis,'price'=>$price,
            'price_date'=>$quotes[$t]['date']??null,'valuation_stale_or_incomplete'=>$missingPrice,'value'=>$v,'unrealized'=>$v===null?null:$v-$basis,
            'realized'=>$realized[$t],'dividends_cash'=>$divs[$t],'dividends_reinvested'=>$reinvested[$t],
            'purchases'=>$purchases[$t],'sales_proceeds'=>$proceeds[$t],
            'lifetime_profit'=>$cashGain,'xirr_pct'=>($missingPrice||!$flows)?null:(($x=it_xirr($flows))===null?null:$x*100),
            'lots'=>$currentLots,
        ];
        foreach ($cash[$t] as $f) $allFlows[]=$f;
        if ($v!==null && $qty>0) $allFlows[]=[$quotes[$t]['date'],$v];
        $net += $proceeds[$t]+$divs[$t]-$purchases[$t];
    }
    return [
        'holdings'=>$holdings,'series'=>$series,'sales'=>$sales,'missing_prices'=>$missing,
        'totals'=>[
            'market_value'=>count($missing)?null:$totalValue,
            'lifetime_profit'=>count($missing)?null:$totalValue+$net,
            'unrealized'=>count($missing)?null:array_sum(array_map(static fn($h)=>$h['unrealized']??0,$holdings)),
            'realized'=>array_sum($realized),'dividends_cash'=>array_sum($divs),
            'dividends_reinvested'=>array_sum($reinvested),
            'xirr_pct'=>count($missing)?null:(($x=it_xirr($allFlows))===null?null:$x*100)
        ],
    ];
}
