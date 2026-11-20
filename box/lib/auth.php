<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/1-Framework/bootstrap.php';

function box_auth(): \Jasr\Users\Auth
{
    static $auth = null;
    return $auth ??= \Jasr\Framework\SharedIdentity::connect();
}

function box_require_user(string $minimum = 'viewer'): array
{
    return box_auth()->requireProject('box', $minimum);
}

function box_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('POST required.');
    }
    if (!box_auth()->validCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid form token.');
    }
}

function box_owned_by(array $box, array $user): bool
{
    return ($box['owner'] ?? null) === 'id:' . $user['id'];
}

function box_new_owner(array $user): string
{
    return 'id:' . $user['id'];
}
