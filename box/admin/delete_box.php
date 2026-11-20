<?php
require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');
box_require_post();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$code = $_POST['code'] ?? '';

$data = loadBoxData();

if (
    isset($data['boxes'][$code]) &&
    box_owned_by($data['boxes'][$code], $user)
) {
    // Delete QR first
    deleteBoxQR($code);

    // Delete box
    unset($data['boxes'][$code]);

    saveBoxData($data);
}

header('Location: index.php', true, 303);
exit;
