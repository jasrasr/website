<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
box_require_user('member');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Use the protected box editor.');
}
$code = $_GET['c'] ?? '';
if (!is_string($code) || !preg_match('/^BOX[A-Z0-9]{6}$/D', $code)) {
    http_response_code(404);
    exit('Box not found.');
}
header('Location: admin/edit_box.php?c=' . rawurlencode($code), true, 303);
exit;
