<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';

$auth = \Jasr\Framework\SharedIdentity::connect();
$auth->requireProject('how-much-time-worked', 'member');

function requireTimeclockFormToken(\Jasr\Users\Auth $auth): void
{
    if (!$auth->validCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid form token.');
    }
}
