<?php
/*
 * File Name: delete_box.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Remove a box owned by the signed-in member.
 * Revision: 1.0.0
 * Created Date: 2026-01-18
 * Modified Date: 2026-11-20
 * Changelog: 1.0.0 - Use the serialized inventory update.
 */
require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');
box_require_post();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

$code = $_POST['code'] ?? '';

$deleted = false;
if (!updateBoxData(static function (array $data) use ($code, $user, &$deleted): array {
    if (is_string($code) && isset($data['boxes'][$code]) && box_owned_by($data['boxes'][$code], $user)) {
        unset($data['boxes'][$code]);
        $deleted = true;
    }
    return $data;
})) {
    http_response_code(500);
    exit('Could not delete the box.');
}
if ($deleted) {
    deleteBoxQR($code);
}

header('Location: index.php', true, 303);
exit;
