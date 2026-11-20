<?php
require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');
box_require_post();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$data  = loadBoxData();
$boxes = $data['boxes'] ?? [];

$created = 0;

foreach ($boxes as $code => $box) {
    if (!box_owned_by($box, $user)) {
        continue;
    }

    if (!qrExists($code)) {
        generateBoxQR($code);
        $created++;
    }
}

header('Location: index.php?qr_created=' . $created, true, 303);
exit;
