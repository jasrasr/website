<?php
require_once __DIR__ . '/../lib/auth.php';
requireLogin();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$currentUser = $_SESSION['user'];
$code = $_POST['code'] ?? '';

$data = loadBoxData();

if (
    isset($data['boxes'][$code]) &&
    ($data['boxes'][$code]['owner'] ?? '') === $currentUser
) {
    // Delete QR first
    deleteBoxQR($code);

    // Delete box
    unset($data['boxes'][$code]);

    saveBoxData($data);
}

header('Location: index.php');
exit;
