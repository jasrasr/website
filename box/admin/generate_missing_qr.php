<?php
require_once __DIR__ . '/../lib/auth.php';
requireLogin();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$currentUser = $_SESSION['user'];

$data  = loadBoxData();
$boxes = $data['boxes'] ?? [];

$created = 0;

foreach ($boxes as $code => $box) {
    if (($box['owner'] ?? '') !== $currentUser) {
        continue;
    }

    if (!qrExists($code)) {
        generateBoxQR($code);
        $created++;
    }
}

header('Location: index.php?qr_created=' . $created);
exit;
