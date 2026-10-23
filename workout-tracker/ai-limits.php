<?php
/* File Name: ai-limits.php
Author: Jason Lamb (with help from ChatGPT)
Description: Persisted per-account limits for optional paid AI interpretation.
Revision: 1.0.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.0 - Reserve rolling request budgets atomically with trusted identity exemptions. */
declare(strict_types=1);

/** Old private configs receive safe defaults without being overwritten. */
function workout_ai_limits(array $config): array
{
    $limits = $config['request_limits'] ?? [];
    if (!is_array($limits)) throw new RuntimeException('Invalid AI request limits.');
    $limits += ['per_minute' => 5, 'per_day' => 50, 'exempt_user_ids' => []];
    foreach (['per_minute', 'per_day'] as $key) {
        if (!is_int($limits[$key]) || $limits[$key] < 1 || $limits[$key] > 10000) {
            throw new RuntimeException('Invalid AI request limits.');
        }
    }
    if (!is_array($limits['exempt_user_ids']) || !array_is_list($limits['exempt_user_ids'])) {
        throw new RuntimeException('Invalid AI limit exemptions.');
    }
    foreach ($limits['exempt_user_ids'] as $id) {
        if (!is_string($id) || trim($id) === '') throw new RuntimeException('Invalid AI limit exemptions.');
    }
    return $limits;
}

/** Call only with the identity returned by requireProject(), never request fields. */
function workout_ai_exempt(array $user, array $limits): bool
{
    // Demo Super Admin accounts are shared examples, not quota administrators.
    if (!empty($user['demo'])) return false;
    return ($user['role'] ?? null) === 'super_admin'
        || in_array((string)($user['id'] ?? ''), $limits['exempt_user_ids'], true);
}

/** Returns seconds to retry, or zero after reserving one provider attempt. */
function workout_ai_reserve(string $storage, array $user, array $limits, ?int $now = null): int
{
    if (workout_ai_exempt($user, $limits)) return 0;
    $id = $user['id'] ?? null;
    if (!is_string($id) || $id === '') throw new RuntimeException('Missing AI account identity.');
    $now ??= time();
    $retry = 0;
    // Separate from workout history, under the same HTTP-denied private directory.
    $path = $storage . '/ai-requests-' . hash('sha256', $id) . '.json';
    \Jasr\Framework\JsonStore::update($path, static function (array $records) use ($limits, $now, &$retry): array {
        foreach ($records as $stamp) {
            if (!is_int($stamp) || $stamp < 0) throw new RuntimeException('Invalid AI request history.');
        }
        $records = array_values(array_filter($records, static fn(int $stamp): bool => $stamp > $now - 86400));
        sort($records, SORT_NUMERIC);
        foreach ([60 => $limits['per_minute'], 86400 => $limits['per_day']] as $window => $limit) {
            $recent = array_values(array_filter($records, static fn(int $stamp): bool => $stamp > $now - $window));
            if (count($recent) >= $limit) {
                $retry = max($retry, $recent[count($recent) - $limit] + $window - $now);
            }
        }
        if ($retry === 0) $records[] = $now;
        return $records;
    });
    return $retry;
}
