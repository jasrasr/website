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
    $user = box_auth()->requireProject('box', $minimum);
    try {
        box_legacy_owner($user); // Validate private links before any protected page sends output.
    } catch (Throwable $error) {
        error_log('Box owner mapping: ' . $error->getMessage());
        http_response_code(503);
        exit('Box owner mapping is unavailable.');
    }
    return $user;
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

/** A private, administrator-reviewed mapping; never infer ownership from a matching username. */
function box_legacy_owner(array $user): ?string
{
    $path = dirname(__DIR__) . '/data/owner-links.json';
    if (!is_file($path)) return null;
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $links = $data['links'] ?? null;
    if (!is_array($links)) throw new RuntimeException('Invalid box owner links.');
    $owners = [];
    foreach ($links as $id => $owner) {
        if (!is_string($id) || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 ||
            !is_string($owner) || trim($owner) === '' || isset($owners[$owner])) {
            throw new RuntimeException('Invalid or duplicate box owner link.');
        }
        $owners[$owner] = true;
    }
    return $links[$user['id']] ?? null;
}

function box_owned_by(array $box, array $user): bool
{
    $owner = $box['owner'] ?? null;
    if (!is_string($owner)) return false;
    if ($owner === 'id:' . $user['id']) return true;
    $legacyOwner = box_legacy_owner($user);
    return $legacyOwner !== null && $owner === $legacyOwner;
}

function box_new_owner(array $user): string
{
    return 'id:' . $user['id'];
}
