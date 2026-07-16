<?php
/*
 * next.php — the protected page revealed only after verification.
 *
 * This page checks the session server-side. A visitor who tries to open
 * next.php directly (typed URL, guessed link, read the source of index.html)
 * has no verified session and gets bounced back to the gate. The content
 * below is never sent to unverified visitors.
 */

session_start();

$ok = !empty($_SESSION['human_verified'])
    && (time() - ($_SESSION['human_verified_time'] ?? 0) <= 600); // valid 10 minutes

if (!$ok) {
    // Not verified (or expired) — send them back to the gate.
    header('Location: index.html');
    exit;
}

// Single use: require passing the gate again next time.
unset($_SESSION['human_verified'], $_SESSION['human_verified_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome, human</title>
<style>
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: radial-gradient(1000px 600px at 50% -10%, #26406b, #12172a);
    color: #fff; text-align: center; padding: 24px;
  }
  h1 { font-size: clamp(32px, 6vw, 56px); margin: 0 0 12px; }
  p  { font-size: clamp(16px, 2.5vw, 20px); color: #c7cfe0; max-width: 560px; }
</style>
</head>
<body>
  <div>
    <h1>🎉 You made it through</h1>
    <p>This page only rendered because your session was verified on the server.
       Replace this with whatever the next step of your site should be.</p>
  </div>
</body>
</html>
