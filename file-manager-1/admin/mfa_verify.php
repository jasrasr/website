<?php
/*
===========================================================
 File: admin/mfa_verify.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   MFA verification gateway.
   - Prompts for 6-digit TOTP code.
   - On success, marks session as MFA-verified
     and redirects to the requested target.
===========================================================
*/

require_once __DIR__ . '/mfa_lib.php';

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
global $SECURITY_LOG;

$redirect = $_GET['redirect'] ?? 'admin.php';
$message  = '';

if (!mfa_is_configured()) {
    header('Location: mfa_setup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $secret = mfa_get_secret();
    if ($secret && mfa_verify_code($secret, $code)) {
        $_SESSION['mfa_passed'] = time();
        write_log($SECURITY_LOG, "MFA VERIFY SUCCESS | IP=$clientIP");
        $target = $_POST['redirect'] ?? $redirect;
        header('Location: ' . $target);
        exit;
    } else {
        write_log($SECURITY_LOG, "MFA VERIFY FAIL | IP=$clientIP");
        $message = "Invalid code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MFA Verification</title>
</head>
<body>
    <h1>MFA Verification</h1>
    <p>Your IP: <strong><?php echo htmlspecialchars($clientIP); ?></strong></p>

    <?php if ($message): ?>
        <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>

    <form method="post">
        <label for="code">Enter 6-digit code from your Authenticator :</label>
        <input type="text" id="code" name="code" maxlength="6" required>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        <button type="submit">Verify</button>
    </form>
</body>
</html>
