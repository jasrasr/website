<?php
declare(strict_types=1);

// Run: php fs/tests/hierarchical-analytics-test.php
// Load only pure collector helpers; do not execute authentication or API collection.
$source = (string) file_get_contents(__DIR__ . '/../api/collect.php');
$start = strpos($source, 'function incrementCount(');
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

function sampleTicket(int $id, string $category, string $subcategory, string $item): array
{
    return [
        'id' => $id,
        'status' => 2,
        'requesterId' => $id,
        'priority' => 1,
        'category' => $category,
        'subCategory' => $subcategory,
        'itemCategory' => $item,
        'createdAt' => '2026-09-01T12:00:00-04:00',
    ];
}

expectSame(hierarchyLabel(['Software', 'Microsoft 365']), 'Software › Microsoft 365');
expectSame(hierarchyLabel(['', '', '']), 'Uncategorized › No subcategory › No item');

$tickets = [];
$id = 1;
for ($i = 0; $i < 3; $i++) $tickets[] = sampleTicket($id++, 'Software', 'Microsoft 365', 'Outlook');
for ($i = 0; $i < 4; $i++) $tickets[] = sampleTicket($id++, 'Account Management', 'Microsoft 365', 'Outlook');
for ($i = 0; $i < 2; $i++) $tickets[] = sampleTicket($id++, 'Hardware', 'Laptop', 'Dell');
$tickets[] = sampleTicket($id++, '', '', '');

$analytics = aggregateAnalytics(
    $tickets,
    [4, 5],
    new DateTimeImmutable('2026-09-06T12:00:00-04:00'),
    [2 => 'Open']
);
expectSame($analytics['subcategory'], [
    'Account Management › Microsoft 365' => 4,
    'Software › Microsoft 365' => 3,
    'Other' => 3,
]);
expectSame($analytics['itemCategory'], [
    'Account Management › Microsoft 365 › Outlook' => 4,
    'Software › Microsoft 365 › Outlook' => 3,
    'Other' => 3,
]);

echo "Hierarchical analytics tests passed.\n";
