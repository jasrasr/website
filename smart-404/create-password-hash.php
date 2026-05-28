<?php
/*
Filename: create-password-hash.php
Revision: 1.0.0
Description: Temporary HTTPS-only helper form for generating PHP password_hash() values.
Author: Jason Lamb (with help from Codex CLI)
Created Date: 2026-05-28
Modified Date: 2026-05-28
Changelog:
1.0.0 initial release
*/

declare(strict_types=1);

function is_https_request(): bool
{
    if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTPS'] ?? '') === '1') {
        return true;
    }

    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!is_https_request()) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/create-password-hash.php';
    if ($host !== '') {
        header('Location: https://' . $host . $uri, true, 302);
        exit;
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'HTTPS is required.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$hash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    if ($password === '') {
        $error = 'Enter a password to hash.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Password Hash</title>
    <style>
        :root { color-scheme: light dark; font-family: Arial, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f5f7fa; color: #18202a; }
        main { width: min(680px, 100%); background: #fff; border: 1px solid #ccd5df; border-radius: 8px; padding: 22px; }
        h1 { margin: 0 0 16px; font-size: 26px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; }
        input, textarea, button { width: 100%; box-sizing: border-box; font: inherit; padding: 10px; }
        textarea { min-height: 90px; resize: vertical; }
        button { margin-top: 12px; cursor: pointer; font-weight: 700; }
        .error { color: #a40000; }
        .note { color: #657384; line-height: 1.45; }
        @media (prefers-color-scheme: dark) {
            body { background: #10151b; color: #e8edf2; }
            main { background: #161d25; border-color: #2d3a47; }
            .note { color: #aab4bf; }
            .error { color: #ff8b8b; }
        }
    </style>
</head>
<body>
<main>
    <h1>Create Password Hash</h1>
    <p class="note">This page does not save the password or generated hash. Keep this helper protected and available only over HTTPS.</p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required autofocus>
        <button type="submit">Generate hash</button>
    </form>

    <?php if ($hash !== ''): ?>
        <h2>Hash</h2>
        <textarea readonly><?= h($hash) ?></textarea>
    <?php endif; ?>
</main>
</body>
</html>
