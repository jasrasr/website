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
    <link rel="stylesheet" href="assets/css/app.css?v=1.5.2">
</head>
<body class="landing">
<main>
    <header class="landing-hero">
        <h1>Refill Tracker</h1>
        <p>Know what’s on hand and when to buy more.</p>
        <nav class="landing-actions" aria-label="Account">
            <a href="<?= refill_escape($loginUrl) ?>">Sign in</a>
            <a href="<?= refill_escape($registrationUrl) ?>">Request an account</a>
        </nav>
    </header>
    <dl class="landing-features">
        <div><dt>Inventory by location</dt><dd>See how many spares you have in each closet, cabinet, or room.</dd></div>
        <div><dt>Usage and restock</dt><dd>Log openings and replacements to estimate when you’ll need more.</dd></div>
        <div><dt>Purchase history</dt><dd>Record multipacks, unit prices, and several items from one receipt.</dd></div>
    </dl>
    <footer>Revision 1.5.2 <span aria-hidden="true">·</span> Modified <time datetime="2026-11-21">Nov 21, 2026</time></footer>
</main>
</body>
</html>
