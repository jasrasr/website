<?php
/*
===========================================================
 File: admin/whitelistme.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   IP self-whitelisting page.
   - Requires a shared password.
   - Adds current IP to dynamic allowlist.
   - Enforces 1 action per hour per IP.
===========================================================
*/

require_once __DIR__ . '/../config.php';

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$message  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password !== WHITELIST_PASSWORD) {
        write_log($WHITELIST_LOG, "WHITELIST FAIL PASSWORD | IP=$clientIP");
        $message = "Incorrect password.";
    } else {
        // 1/hour rate limit per IP via SOURCE=page entries
        $now     = time();
        $entries = file_exists($WHITELIST_LOG)
            ? file($WHITELIST_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : [];
        $recent  = false;

        foreach ($entries as $line) {
            if (strpos($line, "IP=$clientIP") !== false && strpos($line, "SOURCE=page") !== false) {
                if (preg_match('/\[(.*?)\]/', $line, $m)) {
                    $ts = strtotime($m[1]);
                    if ($ts !== false && ($now - $ts) <= 3600) {
                        $recent = true;
                        break;
                    }
                }
            }
        }

        if ($recent) {
            write_log($WHITELIST_LOG, "WHITELIST RATE LIMIT | IP=$clientIP | SOURCE=page");
            $message = "Rate limited: you can only whitelist this IP once per hour.";
        } else {
            add_ip_to_allowed_list($clientIP, 'page');
            $message = "Success: IP $clientIP has been whitelisted.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Whitelist My IP</title>
</head>
<body>
    <h1>Whitelist My IP</h1>
    <p>Your current IP: <strong><?php echo htmlspecialchars($clientIP); ?></strong></p>

    <?php if ($message): ?>
        <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>

    <form method="post">
        <label for="password">Whitelist password :</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Whitelist this IP</button>
    </form>
</body>
</html>
