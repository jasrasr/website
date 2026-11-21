<?php
declare(strict_types=1);

if (!isset($loginUrl, $registrationUrl)) {
    http_response_code(404);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Refill Tracker</title>
    <link rel="stylesheet" href="assets/css/app.css?v=1.5.1">
</head>
<body>
<main>
    <h1>Refill Tracker</h1>
    <section aria-label="Get started">
        <p>Track household supplies, see where your spares are stored, and know when to buy more.</p>
        <p><a href="<?= refill_escape($loginUrl) ?>">Sign in</a></p>
        <p><a href="<?= refill_escape($registrationUrl) ?>">Request an account</a></p>
    </section>
    <footer>Revision 1.5.1</footer>
</main>
</body>
</html>
