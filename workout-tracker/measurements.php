<?php
/* File Name: measurements.php
Author: Jason Lamb (with help from ChatGPT)
Description: Backward-compatible optional workout metrics and ordered strength set validation.
Revision: 1.2.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.2.0 - Validate optional 0–59 duration seconds and whole minutes when combined. 1.1.0 - Validate optional heart rate BPM (integer 1–300) without backfilling old records. 1.0.0 - Validate calories, flexible named numeric measurements and individual weighted sets without schema migrations. */
declare(strict_types=1);

/**
 * Parse only submitted optional fields. This is important for older clients:
 * omitting a field must never wipe data previously saved in that field.
 */
function workout_advanced_fields(array $input): array {
    $out = [];

    // New duration_seconds stores the *remaining* seconds (0–59). Existing
    // duration_minutes without this field remains unchanged and valid.
    if (array_key_exists('duration_seconds', $input)) {
        $seconds=$input['duration_seconds'];
        if ($seconds === '' || $seconds === null) {
            $out['duration_seconds']=null;
        } elseif ((is_int($seconds)
                || (is_string($seconds) && preg_match('/^[0-9]{1,2}$/D',trim($seconds))===1))
            && (int)$seconds>=0 && (int)$seconds<=59) {
            $out['duration_seconds']=(int)$seconds;
        } else {
            throw new InvalidArgumentException('Duration seconds must be a whole number from 0 to 59.');
        }
        if ($out['duration_seconds'] !== null) {
            $minutes=$input['duration_minutes']??null;
            if ($minutes !== null && $minutes !== '' &&
                (!is_scalar($minutes) || !is_numeric($minutes) || !is_finite((float)$minutes)
                || (float)$minutes<0 || floor((float)$minutes)!==(float)$minutes)) {
                throw new InvalidArgumentException('When entering seconds, minutes must be a whole number.');
            }
        }
    }

    if (array_key_exists('calories_kcal', $input)) {
        $value = $input['calories_kcal'];
        if ($value === '' || $value === null) {
            $out['calories_kcal'] = null;
        } elseif (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new InvalidArgumentException('Calories must be a number.');
        } elseif (!is_numeric($value) || !is_finite((float)$value)
            || (float)$value < 0 || (float)$value > 100000) {
            throw new InvalidArgumentException('Calories must be between 0 and 100000.');
        } else {
            $out['calories_kcal'] = round((float)$value, 2);
        }
    }

    // A dedicated heart-rate field is separate from arbitrary custom metrics.
    // Only process it when supplied: old workouts and older clients keep their
    // original JSON shape and any previously recorded heart rate.
    if (array_key_exists('heart_rate_bpm', $input)) {
        $value = $input['heart_rate_bpm'];
        if ($value === null || $value === '') {
            $out['heart_rate_bpm'] = null;
        } elseif (
            (is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value)
                || (is_string($value) && preg_match('/^[0-9]{1,3}$/D', trim($value)) === 1))
            && (int)$value >= 1 && (int)$value <= 300
        ) {
            $out['heart_rate_bpm'] = (int)$value;
        } else {
            throw new InvalidArgumentException('Heart rate must be a whole number between 1 and 300 BPM.');
        }
    }

    if (array_key_exists('custom_metrics', $input)) {
        $metrics = $input['custom_metrics'];
        if (!is_array($metrics) || !array_is_list($metrics) || count($metrics) > 12) {
            throw new InvalidArgumentException('Add up to twelve custom measurements.');
        }
        $normalized = [];
        $seen = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric) || array_is_list($metric)) {
                throw new InvalidArgumentException('Invalid custom measurement.');
            }
            $name = $metric['name'] ?? null;
            $unit = $metric['unit'] ?? '';
            $value = $metric['value'] ?? null;
            if (!is_string($name) || !is_string($unit)) {
                throw new InvalidArgumentException('Measurement name and unit must be text.');
            }
            $name = trim($name);
            $unit = trim($unit);
            if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _\/().%-]{0,47}$/uD', $name)
                || strlen($name) > 72) {
                throw new InvalidArgumentException('Measurement name must be 1–48 characters (letters/numbers and common punctuation).');
            }
            if (strlen($unit) > 16 || ($unit !== '' && !preg_match('/^[\p{L}\p{N}%°\/._-]+$/uD', $unit))) {
                throw new InvalidArgumentException('Measurement unit must be 16 characters or fewer.');
            }
            if (!(is_float($value) || is_int($value) || is_string($value))
                || !is_numeric($value) || !is_finite((float)$value)
                || abs((float)$value) > 1000000000) {
                throw new InvalidArgumentException('Measurement value must be a finite number.');
            }
            $key = strtolower($name).'|'.strtolower($unit);
            if (isset($seen[$key])) throw new InvalidArgumentException('Duplicate measurement name and unit.');
            $seen[$key] = true;
            $normalized[] = ['name'=>$name,'unit'=>$unit,'value'=>round((float)$value, 4)];
        }
        $out['custom_metrics'] = $normalized;
    }

    if (array_key_exists('sets_detail', $input)) {
        $sets = $input['sets_detail'];
        if (!is_array($sets) || !array_is_list($sets) || count($sets) > 40) {
            throw new InvalidArgumentException('Enter up to 40 individual sets.');
        }
        $normalized = [];
        foreach ($sets as $set) {
            if (!is_array($set) || array_is_list($set)) {
                throw new InvalidArgumentException('Invalid set data.');
            }
            $reps = $set['reps'] ?? null;
            $weight = $set['weight_lb'] ?? null;
            $note = $set['note'] ?? '';
            if (filter_var($reps, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000]]) === false) {
                throw new InvalidArgumentException('Each set needs 1 to 1000 repetitions.');
            }
            if ($weight === '' || $weight === null) {
                $weight = null; // Bodyweight or no load stated.
            } elseif ((!is_int($weight) && !is_float($weight) && !is_string($weight))
                || !is_numeric($weight) || !is_finite((float)$weight)
                || (float)$weight < 0 || (float)$weight > 10000) {
                throw new InvalidArgumentException('Set weight must be between 0 and 10000 lb.');
            } else {
                $weight = round((float)$weight, 3);
            }
            if (!is_string($note) || strlen($note) > 120) {
                throw new InvalidArgumentException('Each set note must be 120 characters or fewer.');
            }
            $normalized[] = [
                'reps'=>(int)$reps, 'weight_lb'=>$weight, 'note'=>trim($note)
            ];
        }
        $out['sets_detail'] = $normalized;
    }
    return $out;
}

/** Derive strength progress from each actual set, not a misleading average weight. */
function workout_set_stats(array $record): ?array {
    $sets = $record['sets_detail'] ?? null;
    if (!is_array($sets) || count($sets) === 0) return null;
    $reps = 0;
    $volume = 0.0;
    $maxWeight = null;
    foreach ($sets as $set) {
        if (!is_array($set)) continue;
        $count = (int)($set['reps'] ?? 0);
        $load = $set['weight_lb'] ?? null;
        if ($count < 1 || $count > 1000) continue;
        $reps += $count;
        if (is_numeric($load) && is_finite((float)$load) && (float)$load >= 0) {
            $volume += $count * (float)$load;
            $maxWeight = $maxWeight === null ? (float)$load : max($maxWeight, (float)$load);
        }
    }
    return [
        'set_count'=>count($sets),
        'total_reps'=>$reps,
        'max_weight_lb'=>$maxWeight,
        'total_volume_lb'=>round($volume,2)
    ];
}
