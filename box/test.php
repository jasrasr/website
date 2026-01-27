<?php

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/lib/data.php';

$code = generateBoxCode();

$box = [
    'name'    => 'Test Box',
    'items'   => ['Item A', 'Item B'],
    'count'   => 2,
    'updated' => date('c'),
    'owner'   => 'jason'
];

saveBox($code, $box);

echo "Created box: $code\n";
print_r(getBox($code));
