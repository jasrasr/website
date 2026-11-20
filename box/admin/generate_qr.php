<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');
box_require_post();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$code = $_POST['code'] ?? '';
$box = is_string($code) ? getBox($code) : null;
if (!$box || !box_owned_by($box, $user)) {
    http_response_code(404);
    exit('Box not found.');
}

generateBoxQR($code);
header('Location: index.php', true, 303);
exit;
