<?php
/*
===========================================================
 File: admin/mfa_setup.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   One-time MFA enrollment page.
   - Generates a TOTP secret.
   - Displays QR code (otpauth:// URI).
   - Verifies first code and saves secret.
===========================================================
*/

require_once __DIR__ . '/mfa_lib.php';

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
global $SECURITY_LOG;

$message   = '';
$secret    = mfa_get_secret();
$justSaved = false;

// If no secret exists yet, generate a new one
if ($secret === null) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}

$issuer   = urlencode('SecureUploadAdmin');
$account  = urlencode('admin@yourdomain');
$label    = $issuer . ':' . $account;
$otpauth  = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";

// Simple external QR code provider
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauth);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code   = $_POST['code'] ?? '';
    $secretToVerify = $secret;

    if (mfa_verify_code($secretToVerify, $code)) {
        mfa_save_secret($secretToVerify);
        $_SESSION['mfa_passed'] = time();
        write_log($SECURITY_LOG, "MFA SETUP SUCCESS | IP=$clientIP");
        $message   = "MFA configured successfully.";
        $justSaved = true;
    } else {
        write_log($SECURITY_LOG, "MFA SETUP FAIL | IP=$clientIP");
        $message = "Invalid code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MFA Setup</title>
</head>
<body>
    <h1>MFA Setup</h1>
    <p>Your IP: <strong><?php echo htmlspecialchars($clientIP); ?></strong></p>

    <?php if ($message): ?>
        <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>

    <?php if ($justSaved): ?>
        <p>MFA is now enabled. You can go back to <a href="admin.php">Admin</a>.</p>
    <?php else: ?>
        <?php if (mfa_is_configured()): ?>
            <p>MFA is already configured. You can still scan the code again if you are adding a new device.</p>
        <?php else: ?>
            <p>Scan this QR code with your authenticator app:</p>
        <?php endif; ?>

        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="MFA QR Code">

        <p>If you prefer manual setup, use this secret:</p>
        <pre><?php echo htmlspecialchars($secret); ?></pre>

        <form method="post">
            <label for="code">Enter current 6-digit code :</label>
            <input type="text" id="code" name="code" maxlength="6" required>
            <button type="submit">Verify &amp; Save</button>
        </form>
    <?php endif; ?>
</body>
</html>
