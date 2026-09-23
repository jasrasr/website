<?php
declare(strict_types=1);

// Run: php fs/tests/org-domain-test.php
// Load only pure collector helpers; do not execute authentication or API collection.
$source = (string) file_get_contents(__DIR__ . '/../api/collect.php');
$start = strpos($source, 'function emailDomain(');
$end = strpos($source, 'if (!is_file($configFile))');
if ($start === false || $end === false || $end <= $start) {
    throw new RuntimeException('Collector helper block not found.');
}
eval(substr($source, $start, $end - $start));

function expectSame(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(var_export($actual, true) . ' != ' . var_export($expected, true));
    }
}

expectSame(emailDomain('someone@example.com'), 'example.com');
expectSame(emailDomain('Someone@Example.COM'), 'example.com');
expectSame(emailDomain(' someone@example.com '), 'example.com');
expectSame(emailDomain('not-an-email'), 'Unknown');
expectSame(emailDomain(''), 'Unknown');
expectSame(emailDomain(null), 'Unknown');

$counts = [
    'a.com' => 5,
    'b.com' => 4,
    'c.com' => 3,
    'd.com' => 2,
    'e.com' => 2,
    'f.com' => 1,
    'g.com' => 1,
    'h.com' => 1,
    'i.com' => 1,
];
expectSame(boundedOrgCounts($counts, 8), [
    'a.com' => 5,
    'b.com' => 4,
    'c.com' => 3,
    'd.com' => 2,
    'e.com' => 2,
    'f.com' => 1,
    'g.com' => 1,
    'h.com' => 1,
    'Other' => 1,
]);
expectSame(boundedOrgCounts(['only.com' => 2], 8), ['only.com' => 2]);

echo "Organization domain tests passed.\n";
