<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/finances/bootstrap.php';
// Explicit server-owned registry. No browser-controlled include paths.
function jasr_linking_adapters(): array
{
    $auth = jasr_users_auth();
    return ['finances' => finances_links($auth->directory)];
}
