<?php
declare(strict_types=1);
// This example runs inside 1-Framework/examples. When copying to a sibling
// project as auth.php, use dirname(__DIR__) . '/1-Framework/bootstrap.php'
// below and change 'example' to that project's registered ID.
require_once dirname(__DIR__) . '/bootstrap.php';
$auth = \Jasr\Framework\SharedIdentity::connect();
$user = $auth->requireProject('example', 'viewer');
// $user['id'] is the stable identity key. Never use a display name as ownership.
// Every write handler also needs a member/admin gate and a CSRF check:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireProject('example', 'member', true);
    if (!$auth->validCsrf($_POST['csrf'] ?? null)) {
        \Jasr\Framework\Response::json(403, 'Invalid form token.');
    }
}
