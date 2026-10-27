<?php
/* File Name: parse.php
Author: Jason Lamb (with help from ChatGPT)
Description: API-key-free natural-language extraction for workout tracking.
Revision: 1.3.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.3.0 - Recognize explicit calories and ordered varying weight-rep pairs. 1.2.0 - Extract optional tracking tags and treadmill incline percentage. 1.1.0 - Extract workout activities, dates, and metrics without external services. */
declare(strict_types=1);

/** Recognize common exercise descriptions conservatively; never invent missing measurements. */
function workout_parse_text(string $note, string $today): array
{
    $note = trim(preg_replace('/\s+/u', ' ', $note) ?? $note);
    if ($note === '' || strlen($note) > 4000) throw new InvalidArgumentException('Describe your workout in up to 4,000 characters.');
    $reference = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$reference || $reference->format('Y-m-d') !== $today) throw new InvalidArgumentException('Invalid local date.');
    $date = workout_parse_date($note, $reference);
    $pattern = '~\b(?:stationary\s+bik(?:e|ing)|bench\s+press(?:es)?|jumping\s+jacks?|push[\s-]?ups?|sit[\s-]?ups?|weight\s*(?:lifting|training)|tread\s?mill|elliptical|walk(?:ed|ing|s)?|hik(?:e|ed|ing)|runn?ing|ran|runs?|jogg?(?:ed|ing)?|cycl(?:e|ed|ing)|bik(?:e|ed|ing)|row(?:ed|ing)?|sw(?:im|am|imming)|squat(?:s|ted|ting)?|lunge(?:s|d)?|crunch(?:es)?|plank(?:s)?|deadlift(?:s|ed|ing)?|stair(?:s|master)?|yoga)\b~i';
    preg_match_all($pattern, $note, $matches, PREG_OFFSET_CAPTURE);
    $found = $matches[0];
    // "Walked on the treadmill" describes one activity, not separate walking and treadmill records.
    foreach ($found as $i => $match) {
        $next = $found[$i + 1] ?? null;
        if (!$next || !in_array(workout_exercise_name($match[0]), ['walking', 'running'], true) || workout_exercise_name($next[0]) !== 'treadmill') continue;
        $between = substr($note, $match[1] + strlen($match[0]), $next[1] - $match[1] - strlen($match[0]));
        if (!preg_match('~\b(?:and|then|afterwards?)\b|[;,]~i', $between)) unset($found[$i]);
    }
    $found = array_values($found);
    if ($found === []) throw new InvalidArgumentException('I could not identify an exercise. Try “treadmill 1 mile for 20 minutes” or “20 push-ups”.');
    if (count($found) > 20) throw new InvalidArgumentException('Limit each entry to 20 exercises.');
    $entries = [];
    $start = 0;
    foreach ($found as $i => [$alias, $position]) {
        if ($i > 0 && $position < $start) continue;
        $next = $found[$i + 1][1] ?? strlen($note);
        $end = $next;
        if ($i + 1 < count($found)) {
            $between = substr($note, $position + strlen($alias), $next - $position - strlen($alias));
            preg_match_all('~\b(?:and\s+then|then|afterwards?|and)\b|[,;]~i', $between, $separators, PREG_OFFSET_CAPTURE);
            if ($separators[0] !== []) {
                $last = end($separators[0]);
                $end = $position + strlen($alias) + $last[1];
                $startNext = $end + strlen($last[0]);
            } else {
                $startNext = $next;
            }
        }
        $segment = trim(substr($note, $start, max(0, $end - $start)), " \t\n\r,;.-");
        if ($segment !== '') $entries[] = workout_parse_segment($segment, workout_exercise_name($alias), $date);
        $start = $startNext ?? $end;
        unset($startNext);
    }
    if ($entries === []) throw new InvalidArgumentException('Could not extract an exercise.');
    return $entries;
}

function workout_parse_date(string $note, DateTimeImmutable $today): string
{
    if (preg_match('~\b(20\d\d-\d\d-\d\d)\b~', $note, $m)) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $m[1]);
        if (!$d || $d->format('Y-m-d') !== $m[1]) throw new InvalidArgumentException('Invalid workout date.');
        return $d->format('Y-m-d');
    }
    if (preg_match('~\b(\d{1,2})/(\d{1,2})/(20\d{2})\b~', $note, $m)) {
        $d = DateTimeImmutable::createFromFormat('!n/j/Y', "$m[1]/$m[2]/$m[3]");
        if (!$d || $d->format('n/j/Y') !== "$m[1]/$m[2]/$m[3]") throw new InvalidArgumentException('Invalid workout date.');
        return $d->format('Y-m-d');
    }
    if (preg_match('~\b(?:yesterday|last\s+night)\b~i', $note)) return $today->modify('-1 day')->format('Y-m-d');
    if (preg_match('~\b(\d{1,2})\s+days?\s+ago\b~i', $note, $m)) return $today->modify('-'.min((int)$m[1], 60).' days')->format('Y-m-d');
    if (preg_match('~\blast\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b~i', $note, $m)) {
        return $today->modify('last '.strtolower($m[1]))->format('Y-m-d');
    }
    return $today->format('Y-m-d');
}

function workout_exercise_name(string $raw): string
{
    $s = strtolower(preg_replace('/\s+/', ' ', $raw));
    if (preg_match('/tread\s?mill/', $s)) return 'treadmill';
    if (preg_match('/push[\s-]?up/', $s)) return 'push-ups';
    if (preg_match('/sit[\s-]?up/', $s)) return 'sit-ups';
    if (str_contains($s, 'bench')) return 'bench press';
    if (str_contains($s, 'deadlift')) return 'deadlift';
    if (str_contains($s, 'jumping')) return 'jumping jacks';
    if (str_contains($s, 'weight')) return 'weight training';
    if (preg_match('/walk|hik/', $s)) return 'walking';
    if (preg_match('/run|ran|jog/', $s)) return 'running';
    if (preg_match('/bik|cycl/', $s)) return 'cycling';
    if (preg_match('/row/', $s)) return 'rowing';
    if (preg_match('/swim|swam/', $s)) return 'swimming';
    if (preg_match('/squat/', $s)) return 'squats';
    if (preg_match('/lung/', $s)) return 'lunges';
    if (preg_match('/crunch/', $s)) return 'crunches';
    if (preg_match('/plank/', $s)) return 'plank';
    if (preg_match('/stair/', $s)) return 'stairs';
    return $s;
}

function workout_parse_segment(string $segment, string $exercise, string $date): array
{
    $cardio = in_array($exercise, ['treadmill','walking','running','cycling','rowing','swimming','elliptical','stairs'], true);
    $strength = in_array($exercise, ['push-ups','sit-ups','squats','lunges','crunches','plank','bench press','deadlift','jumping jacks','weight training'], true);
    $out = ['exercise' => $exercise, 'date' => $date, 'distance_miles'=>null, 'duration_minutes'=>null,
        'speed_mph'=>null, 'reps'=>null, 'sets'=>null, 'weight_lb'=>null, 'incline_percent'=>null, 'calories_kcal'=>null, 'tag'=>'', 'notes'=>$segment];
    if (preg_match('~\b(\d+(?:\.\d+)?)\s*(miles?|mi|kilomet(?:er|re)s?|km)\b~i', $segment, $m)) {
        $out['distance_miles'] = round((float)$m[1] * (str_starts_with(strtolower($m[2]), 'k') ? 0.621371 : 1), 3);
    }
    if (preg_match_all('~\b(\d+(?:\.\d+)?)\s*(hours?|hrs?|hr|minutes?|mins?|min|seconds?|secs?|sec)\b~i', $segment, $parts, PREG_SET_ORDER)) {
        $minutes=0.0;
        foreach ($parts as $part) {
            $unit=strtolower($part[2]);
            $minutes += (float)$part[1] * (str_starts_with($unit,'h') ? 60 : (str_starts_with($unit,'s') ? 1/60 : 1));
        }
        $out['duration_minutes']=round($minutes, 2);
    }
    if (preg_match('~\b(\d+(?:\.\d+)?)\s*(mph|km/h|kph)\b~i', $segment, $m)) {
        $out['speed_mph']=round((float)$m[1]*(strtolower($m[2]) === 'mph'?1:0.621371),3);
    } elseif ($cardio && preg_match('~\b(?:at|speed(?:\s+of)?|set(?:\s+to)?)\s*(\d+(?:\.\d+)?)\b(?!\s*(?:mi|mile|km|h|hour|min|sec|lb|kg))~i', $segment, $m)) {
        $out['speed_mph']=(float)$m[1];
    }
    if ($strength) {
        if (preg_match('~\b(\d+)\s*(?:sets?\s*(?:of|x|×)\s*|[x×]\s*)(\d+)\b~i', $segment, $m)) {
            $out['sets']=(float)$m[1]; $out['reps']=(float)$m[2];
        } else {
            if (preg_match('~\b(\d+)\s*sets?\b~i', $segment, $m)) $out['sets']=(float)$m[1];
            if (preg_match('~\b(\d+)\s*(?:reps?|repetitions?)\b~i', $segment, $m)) $out['reps']=(float)$m[1];
            elseif (preg_match('~\b(\d+)\s*(?:push[\s-]?ups?|sit[\s-]?ups?|squats?|lunges?|crunches?|jumping\s+jacks?)\b~i', $segment, $m)) $out['reps']=(float)$m[1];
        }
        if (preg_match('~\b(\d+(?:\.\d+)?)\s*(?:lb|lbs|pounds?)\b~i', $segment, $m)) $out['weight_lb']=(float)$m[1];
        elseif (preg_match('~\b(\d+(?:\.\d+)?)\s*(?:kg|kilograms?)\b~i', $segment, $m)) $out['weight_lb']=round((float)$m[1]*2.20462,2);
    }
    if (preg_match('~\b(\d+(?:\.\d+)?)\s*(?:calories?|kcals?|kcal)\b~i', $segment, $m)
        || preg_match('~\b(?:calories?|kcals?|kcal)\s*(?:burned|of|:)?\s*(\d+(?:\.\d+)?)\b~i', $segment, $m)) {
        $out['calories_kcal'] = (float)$m[1];
    }
    // Ordered weight/repetition pairs are a single exercise with multiple sets,
    // not separate workout entries. Never calculate an average load.
    if (in_array($exercise, ['bench press','deadlift','weight training'], true)) {
        $detail = [];
        preg_match_all('~\b(\d+(?:\.\d+)?)\s*(lb|lbs|pounds?|kg)\s*(?:[x×]\s*|for\s*)(\d{1,3})\s*(?:reps?)?\b~i', $segment, $pairs, PREG_SET_ORDER);
        foreach ($pairs as $pair) {
            $load=(float)$pair[1];
            if (strtolower($pair[2])==='kg') $load*=2.20462;
            if ((int)$pair[3]>0 && $load<=10000) $detail[]=[
                'reps'=>(int)$pair[3], 'weight_lb'=>round($load,3), 'note'=>''
            ];
        }
        if ($detail === []) {
            preg_match_all('~\b(\d{2,4}(?:\.\d+)?)\s*[x×]\s*(\d{1,3})\b~i', $segment, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair) {
                if ((float)$pair[1]<=10000 && (int)$pair[2]>0)
                    $detail[]=['reps'=>(int)$pair[2],'weight_lb'=>(float)$pair[1],'note'=>''];
            }
        }
        if ($detail !== [] && count($detail)<=40) {
            $out['sets_detail']=$detail;
            // Scalar reps/weight summarize a single repeated-set value poorly.
            // Keep them unset for freshly parsed mixed-load sessions.
            $out['sets']=null;
            $out['reps']=null;
            $out['weight_lb']=null;
        }
    }
    if ($exercise === 'treadmill') {
        if (preg_match('~\b(?:incline|grade)\s*(?:at|of|=|:)?\s*(\d{1,3}(?:\.\d+)?)\s*%?~i', $segment, $m)
            || preg_match('~\b(\d{1,3}(?:\.\d+)?)\s*%\s*(?:incline|grade)\b~i', $segment, $m)) {
            if ((float)$m[1] <= 100) $out['incline_percent'] = (float)$m[1];
        }
    }
    if (preg_match('~(?:^|\s)#([\p{L}\p{N}][\p{L}\p{N}_-]{0,63})\b~u', $segment, $m)) {
        $out['tag'] = $m[1];
    }
    return $out;
}
