<?php
/* File Name: entries.php
Author: Jason Lamb (with help from ChatGPT)
Description: Validated per-entry edits preserving all existing workout records and prior values.
Revision: 1.1.0
Created Date: 2026-10-22
Modified Date: 2026-10-27
Changelog: 1.1.0 - Preserve/validate optional calories, custom metrics and individual set arrays in revision-locked edits. 1.0.0 - Add optimistic-lock edits, tags, incline, and reversible edit history. */
declare(strict_types=1);
require_once __DIR__ . '/measurements.php';

final class WorkoutEditConflict extends RuntimeException {}
final class WorkoutEntryNotFound extends RuntimeException {}

function workout_record_revision(array $record): string {
    return hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** Shared validation for new and edited optional measurements, without touching older records. */
function workout_optional_fields(array $input): array {
    $tag = $input['tag'] ?? '';
    if (!is_string($tag)) throw new InvalidArgumentException('Invalid tracking tag.');
    $tag = trim($tag);
    if ($tag !== '' && (strlen($tag) > 64 || !preg_match('~^[\p{L}\p{N}][\p{L}\p{N} _./#-]*$~uD', $tag))) {
        throw new InvalidArgumentException('Use a tag up to 64 characters with letters, numbers, spaces, dots, slashes, or hyphens.');
    }
    $incline = $input['incline_percent'] ?? null;
    if ($incline === '' || $incline === null) $incline = null;
    elseif (!is_scalar($incline) || !is_numeric($incline) || !is_finite((float)$incline)
        || (float)$incline < 0 || (float)$incline > 100) {
        throw new InvalidArgumentException('Incline must be between 0 and 100 percent.');
    } else $incline = (float)$incline;
    return ['tag'=>$tag, 'incline_percent'=>$incline];
}

/** Editing requires explicit fields; unknown/legacy metadata is never overwritten. */
function workout_validate_entry_edit(array $input): array {
    $exercise = $input['exercise'] ?? null;
    if (!is_string($exercise)) throw new InvalidArgumentException('Enter an exercise.');
    $exercise = strtolower(trim($exercise));
    if (!preg_match('/^[a-z0-9][a-z0-9 _-]{1,79}$/D', $exercise)) {
        throw new InvalidArgumentException('Invalid exercise.');
    }
    $date = $input['date'] ?? null;
    if (!is_string($date)) throw new InvalidArgumentException('Invalid workout date.');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid workout date.');
    $notes = $input['notes'] ?? '';
    if (!is_string($notes) || strlen($notes) > 2000) throw new InvalidArgumentException('Notes must be under 2,000 characters.');

    $changes = ['exercise'=>$exercise, 'date'=>$date, 'notes'=>trim($notes)] + workout_optional_fields($input) + workout_advanced_fields($input);
    foreach (['distance_miles','duration_minutes','speed_mph','reps','sets','weight_lb'] as $field) {
        $value = $input[$field] ?? null;
        if ($value === null || $value === '') { $changes[$field] = null; continue; }
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value)
            || (float)$value < 0 || (float)$value > 100000) {
            throw new InvalidArgumentException('Invalid '.$field.'.');
        }
        $changes[$field] = (float)$value;
    }
    $time = $input['time_local'] ?? null;
    if ($time !== null && $time !== '') {
        if (!is_string($time) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) {
            throw new InvalidArgumentException('Invalid workout time.');
        }
        $offset = $input['utc_offset'] ?? null;
        if (!is_string($offset) || !preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/D', $offset)) {
            throw new InvalidArgumentException('Invalid timezone offset.');
        }
        $changes['performed_at'] = $date.'T'.$time.':00'.$offset;
    }
    return $changes;
}

/**
 * Transform only the requested record while preserving all other records, metadata,
 * original creation time, session ID, and photo. Concurrent edits fail with 409.
 * Previous record values remain in edit_history for recovery.
 */
function workout_apply_entry_edit(array $records, string $id, string $expectedRevision, array $changes): array {
    if (!preg_match('/^[a-f0-9]{24}$/D', $id)
        || !preg_match('/^[a-f0-9]{64}$/D', $expectedRevision)) {
        throw new InvalidArgumentException('Invalid entry revision.');
    }
    $found = false;
    foreach ($records as $i => $record) {
        if (!is_array($record) || ($record['id'] ?? null) !== $id) continue;
        if ($found) throw new RuntimeException('Duplicate workout entry IDs.');
        $found = true;
        if (!hash_equals(workout_record_revision($record), $expectedRevision)) {
            throw new WorkoutEditConflict('This workout changed elsewhere. Reload before editing.');
        }
        $previous = $record;
        unset($previous['edit_history']);
        $history = $record['edit_history'] ?? [];
        if (!is_array($history)) throw new RuntimeException('Invalid workout edit history.');
        $changed = false;
        foreach ($changes as $key => $value) {
            if ($key === 'performed_at' || in_array($key, ['exercise','date','notes','tag','incline_percent',
                'distance_miles','duration_minutes','speed_mph','reps','sets','weight_lb','calories_kcal','custom_metrics','sets_detail'], true)) {
                if (($record[$key] ?? null) !== $value && !($value === '' && !isset($record[$key]))) $changed = true;
                $record[$key] = $value;
            }
        }
        if (!$changed) { $records[$i] = $record; continue; }
        // Retain the original entry in a local audit history instead of destroying it.
        $history[] = ['edited_at'=>gmdate('c'), 'previous'=>$previous];
        $record['edit_history'] = $history;
        $record['updated_at'] = gmdate('c');
        // If only date was edited, preserve original time-of-day and offset when present.
        if (!isset($changes['performed_at']) && !empty($previous['performed_at'])
            && is_string($previous['performed_at']) && isset($changes['date'])) {
            $record['performed_at'] = $changes['date'].substr($previous['performed_at'], 10);
        }
        $records[$i] = $record;
    }
    if (!$found) throw new WorkoutEntryNotFound('Workout entry not found.');
    return $records;
}
