<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/lib/Accounts.php';
// Only the linking portal or shared-auth initialization loads the adapter interface.
function finances_links(\Jasr\Users\Directory $directory): \Jasr\Users\AccountLinks
{
    require_once __DIR__ . '/lib/AccountLinkAdapter.php';
    return new \Jasr\Users\AccountLinks($directory, new \Jasr\Finances\AccountLinkAdapter());
}
