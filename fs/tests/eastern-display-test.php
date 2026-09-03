<?php
declare(strict_types=1);

// Run: php fs/tests/eastern-display-test.php
// Load only pure display helpers; do not read deployment snapshots or private state.
$source = (string) file_get_contents(__DIR__ . '/../index.php');
$start = strpos($source, 'function easternDate(');
$end = strpos($source, 'function analyticsRows(');
if ($start === false || $end === false || $end <= $start) {
    throw new RuntimeException('Display helper block not found.');
}
eval(substr($source, $start, $end - $start));

function expectSame(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(var_export($actual, true) . ' != ' . var_export($expected, true));
    }
}

// Explicit conversion must not depend on the server timezone.
date_default_timezone_set('Asia/Tokyo');
foreach ([
    ['2026-09-03T09:19:00Z', 'Sep 3, 2026 5:19 AM'],
    ['2026-09-03T05:19:00-04:00', 'Sep 3, 2026 5:19 AM'],
    ['2026-01-03T10:19:00Z', 'Jan 3, 2026 5:19 AM'],
    ['2026-03-08T06:59:00Z', 'Mar 8, 2026 1:59 AM'],
    ['2026-03-08T07:00:00Z', 'Mar 8, 2026 3:00 AM'],
    ['2026-11-01T05:30:00Z', 'Nov 1, 2026 1:30 AM'],
    ['2026-11-01T06:30:00Z', 'Nov 1, 2026 1:30 AM'],
    ['2026-09-03T02:00:00Z', 'Sep 2, 2026 10:00 PM'],
    [null, '—'], ['', '—'], ['not a timestamp', '—'],
] as [$timestamp, $expected]) {
    expectSame(displayDate($timestamp), $expected);
}
expectSame(easternDate('2026-07-01T12:00:00Z')?->format('T'), 'EDT');
expectSame(easternDate('2026-01-01T12:00:00Z')?->format('T'), 'EST');
expectSame(easternDate('2026-09-03T02:00:00Z')?->format('Y-m-d'), '2026-09-02');
expectSame(easternDate('2026-09-03T04:00:00Z')?->format('Y-m-d'), '2026-09-03');
// Offset normalization preserves the recorded instant and fall-back ordering.
$early = easternDate('2026-11-01T01:45:00-04:00');
$late = easternDate('2026-11-01T01:15:00-05:00');
expectSame($late->getTimestamp() - $early->getTimestamp(), 1800);
expectSame(easternDate('2026-09-03T09:19:00Z')?->getTimestamp(), strtotime('2026-09-03T09:19:00Z'));
echo "Eastern display tests passed.\n";
