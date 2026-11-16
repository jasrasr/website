<?php
/**
 * File Name: corporate-action-evidence.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Source-constrained, externally verified corporate-action terms for one-off multi-day / cross-symbol conversions.
 * Revision: 1.0.0
 * Created Date: 2026-11-16
 * Modified Date: 2026-11-16
 * Changelog: 1.0.0 - Replay documented rounded WKHS splits, HCWC/HOST symbol change, and CHK/CHKAQ fractional cash settlement without inventing tax basis.
 */
declare(strict_types=1);

/**
 * Public issuer actions only. These ratios/CUSIPs/dates are not derived from,
 * and reveal nothing about, any user's private brokerage holdings.
 *
 * WKHS SEC: https://www.sec.gov/Archives/edgar/data/1425287/000162828026022417/wkhs-20251231.htm
 * Workhorse 2024 8-K: https://www.sec.gov/Archives/edgar/data/1425287/000121390024052252/ea0207776-8k_workhorse.htm
 * Workhorse 2025 8-K: https://www.sec.gov/Archives/edgar/data/1425287/000121390025117831/ea0268147-8k_workhorse.htm
 * HCWC 2026 8-K: https://www.sec.gov/Archives/edgar/data/1948864/000149315226040875/form8-k.htm
 * HOST 2026 8-K: https://www.sec.gov/Archives/edgar/data/1948864/000149315226043135/form8-k.htm
 * CHK 2020 8-K: https://www.sec.gov/Archives/edgar/data/895126/000089512620000096/a8-k2020x04x13reresult.htm
 */
function it_rc_special_action_terms(): array
{
    return [
        'rounded_splits'=>[
            ['ticker'=>'WKHS','surrender'=>'2024-06-17','credit'=>'2024-06-27',
             'old_cusip'=>'98138J206','new_cusip'=>'98138J305','n'=>1,'d'=>20],
            ['ticker'=>'WKHS','surrender'=>'2025-03-17','credit'=>'2025-03-26',
             'old_cusip'=>'98138J305','new_cusip'=>'98138J404','n'=>2,'d'=>25],
            ['ticker'=>'WKHS','surrender'=>'2025-12-08','credit'=>'2025-12-10',
             'old_cusip'=>'98138J404','new_cusip'=>'98138J503','n'=>1,'d'=>12],
        ],
        'cross_ticker_reverse'=>[
            'from'=>'HCWC','to'=>'HOST','date'=>'2026-08-31',
            'old_cusip'=>'42227T105','new_cusip'=>'42227T303',
            'n'=>1,'d'=>35,
        ],
        'cash_in_lieu'=>[
            'from'=>'CHK','settlement_symbol'=>'CHKAQ','split_date'=>'2020-04-15',
            'old_cusip'=>'165167107','ratio_d'=>200,'max_days'=>30,
        ],
    ];
}

function it_rc_source_cusip(array $row): ?string
{
    return preg_match('/\bCUSIP:\s*([A-Z0-9]{9})\b/i',(string)($row['description']??''),$m)
        ? strtoupper($m[1]):null;
}

/** Select exactly one matching Robinhood source row. Ambiguity is a blocker. */
function it_rc_unique_source_index(array $rows,callable $test): ?int
{
    $matches=[];
    foreach($rows as $i=>$row)if($test($row))$matches[]=$i;
    return count($matches)===1?$matches[0]:null;
}

/**
 * This function edits only a temporary normalized COPY of a draft. Source row
 * IDs, original file, SHA, saved report revision and completed flags survive.
 * Pairing is conditional upon unique matching rows with verified public terms.
 */
function it_rc_verified_special_actions(array $rows): array
{
    $rules=it_rc_special_action_terms();

    // The v1.8 parser accepted same-day SPR pairs. The three issuer-verified
    // WKHS pairs are delayed, with whole-share round-up for small holdings.
    foreach($rules['rounded_splits'] as $rule) {
        $old=it_rc_unique_source_index($rows,static fn($r)=>
            ($r['ticker']??'')===$rule['ticker'] &&
            ($r['date']??'')===$rule['surrender'] &&
            ($r['code']??'')==='SPR' &&
            it_rc_source_cusip($r)===$rule['old_cusip'] &&
            ($r['quantity']??null)===null &&
            ($r['amount']??null)===null &&
            ($r['price']??null)===null);
        $new=it_rc_unique_source_index($rows,static fn($r)=>
            ($r['ticker']??'')===$rule['ticker'] &&
            ($r['date']??'')===$rule['credit'] &&
            ($r['code']??'')==='SPR' &&
            it_rc_source_cusip($r)===$rule['new_cusip'] &&
            is_numeric($r['quantity']??null) && (float)$r['quantity']>0 &&
            ($r['amount']??null)===null && ($r['price']??null)===null);
        if($old===null||$new===null||$old===$new)continue;
        if(($rows[$new]['corporate_action']??'')==='verified_rounded_split')continue;
        if(($rows[$old]['kind']??'')!=='unsupported' ||
           ($rows[$new]['kind']??'')!=='unsupported')continue;
        $intervening=false;
        foreach($rows as $i=>$r) {
            if($i===$old||$i===$new||($r['ticker']??'')!==$rule['ticker'])continue;
            if(($r['date']??'')>$rule['surrender'] && ($r['date']??'')<$rule['credit'] &&
               in_array($r['code']??'',['Buy','Sell','SPR','SPL','REC'],true))$intervening=true;
        }
        if($intervening)continue;
        $rows[$new]['kind']='split_credit';
        $rows[$new]['credit']=(float)$rows[$new]['quantity'];
        $rows[$new]['corporate_action']='verified_rounded_split';
        $rows[$new]['ratio_n']=$rule['n'];$rows[$new]['ratio_d']=$rule['d'];
        $rows[$new]['split_round_up']=true;
        $rows[$new]['paired_surrender']=$rows[$old]['id'];
        $rows[$new]['surrender_quantity']=$rows[$old]['surrender_quantity']??null;
        $rows[$old]['kind']='paired_surrender';
        $rows[$old]['paired_to']=$rows[$new]['id'];
    }

    // SEC documents a HCWC 1:35 split with new CUSIP, followed weeks later
    // by an HCWC->HOST rename. Robinhood's export may display the successor
    // symbol already on the split credit: canonicalize every historical HCWC
    // ledger row to HOST, preserving original symbols in each event's note.
    $rule=$rules['cross_ticker_reverse'];
    $old=it_rc_unique_source_index($rows,static fn($r)=>
        ($r['ticker']??'')===$rule['from'] &&
        ($r['date']??'')===$rule['date'] &&
        ($r['code']??'')==='SPR' &&
        it_rc_source_cusip($r)===$rule['old_cusip'] &&
        ($r['quantity']??null)===null && ($r['amount']??null)===null &&
        ($r['price']??null)===null);
    $new=it_rc_unique_source_index($rows,static fn($r)=>
        ($r['ticker']??'')===$rule['to'] &&
        ($r['date']??'')===$rule['date'] &&
        ($r['code']??'')==='SPR' &&
        it_rc_source_cusip($r)===$rule['new_cusip'] &&
        is_numeric($r['quantity']??null) && (float)$r['quantity']>0 &&
        ($r['amount']??null)===null && ($r['price']??null)===null);
    if($old!==null&&$new!==null &&
       ($rows[$old]['kind']??'')==='unsupported' &&
       ($rows[$new]['kind']??'')==='unsupported') {
        $newShares=(float)$rows[$new]['quantity'];
        // Validate the predecessor's surrendered quantity when the new draft
        // contains it. Old saved drafts lack the original string "25S" but
        // still validate the actual open lot quantity during ledger replay.
        $surrender=$rows[$old]['surrender_quantity']??null;
        if($surrender===null ||
           abs((float)$surrender*$rule['n']/$rule['d']-$newShares)<=0.000050001) {
            $rows[$new]['kind']='split_credit';
            $rows[$new]['corporate_action']='cross_ticker_reverse';
            $rows[$new]['credit']=$newShares;
            $rows[$new]['ratio_n']=$rule['n'];$rows[$new]['ratio_d']=$rule['d'];
            $rows[$new]['reported_split_shares']=$newShares;
            $rows[$new]['paired_surrender']=$rows[$old]['id'];
            $rows[$new]['surrender_quantity']=$surrender;
            $rows[$old]['kind']='paired_surrender';
            $rows[$old]['paired_to']=$rows[$new]['id'];
            foreach($rows as &$r) {
                if(($r['ticker']??'')!==$rule['from'])continue;
                $r['source_ticker']=$rule['from'];
                $r['ticker']=$rule['to'];
            }
            unset($r);
        }
    }

    // Only a genuinely fractional whole-account entitlement can be cashed
    // out as a complete disposition of the original lots. The CHKAQ "CIL
    // on 0.500 ... - CHK" source is a payment for previously surrendered CHK,
    // not a free CHKAQ purchase and not another source of investment income.
    $rule=$rules['cash_in_lieu'];
    $old=it_rc_unique_source_index($rows,static fn($r)=>
        ($r['ticker']??'')===$rule['from'] &&
        ($r['date']??'')===$rule['split_date'] &&
        ($r['code']??'')==='SPR' &&
        it_rc_source_cusip($r)===$rule['old_cusip'] &&
        ($r['kind']??'')==='unsupported' &&
        ($r['quantity']??null)===null &&
        ($r['price']??null)===null && ($r['amount']??null)===null);
    $paid=it_rc_unique_source_index($rows,static fn($r)=>
        ($r['ticker']??'')===$rule['settlement_symbol'] &&
        ($r['code']??'')==='CIL' &&
        ($r['kind']??'')==='unsupported' &&
        is_numeric($r['amount']??null) && (float)$r['amount']>0 &&
        ($r['quantity']??null)===null && ($r['price']??null)===null &&
        str_contains((string)($r['description']??''),'- '.$rule['from']));
    if($old!==null && $paid!==null) {
        $days=(strtotime($rows[$paid]['date'].' UTC')-strtotime($rule['split_date'].' UTC'))/86400;
        if($days>=0 && $days<=$rule['max_days'] &&
           preg_match('/^CIL on ([0-9]+(?:\.[0-9]+)?)\s*@\s*\$([0-9]+(?:\.[0-9]+)?)\s*-\s*'.preg_quote($rule['from'],'/').'$/i',
               (string)$rows[$paid]['description'],$m)) {
            $entitlement=(float)$m[1];$price=(float)$m[2];
            $surrender=$entitlement*$rule['ratio_d'];
            $cash=(float)$rows[$paid]['amount'];
            $observed=$rows[$old]['surrender_quantity']??null;
            if($entitlement>0 && $entitlement<1 && $price>0 &&
               abs($entitlement*$price-$cash)<=0.010001 &&
               ($observed===null || abs((float)$observed-$surrender)<=0.000050001)) {
                $rows[$old]['kind']='sell';
                $rows[$old]['quantity']=$surrender;
                $rows[$old]['amount']=$cash;
                $rows[$old]['original_date']=$rows[$old]['date'];
                $rows[$old]['date']=$rows[$paid]['date'];
                $rows[$old]['corporate_action']='cash_in_lieu';
                $rows[$old]['paired_cash_id']=$rows[$paid]['id'];
                $rows[$old]['source_cash_line']=$rows[$paid]['line'];
                $rows[$paid]['kind']='paired_credit';
                $rows[$paid]['paired_to']=$rows[$old]['id'];
                $rows[$paid]['source_ticker']=$rule['settlement_symbol'];
                $rows[$paid]['ticker']=$rule['from'];
            }
        }
    }
    usort($rows,static fn($a,$b)=>
        strcmp($a['date'],$b['date'])
        ?: (($a['kind']==='split_credit'?0:1)<=>($b['kind']==='split_credit'?0:1))
        ?: ($b['line']<=>$a['line']));
    return $rows;
}
