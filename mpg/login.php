<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Script        : login.php
# Revision      : 1.2
# Created Date  : 2025-10-25
# Modified Date : 2025-10-25
# Description   : Secure login form that reads hashed admin password from .env file
#                 and verifies using password_verify().
*/

if (file_exists(__DIR__ . '/.env') && strpos($_SERVER['REQUEST_URI'], '.env') !== false) {
    http_response_code(403);
    exit('Forbidden');
}


session_start();

// ──────────────── LOAD .ENV FILE ────────────────
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    $hashedPassword = $env['ADMIN_PASSWORD_HASH'] ?? '';
} else {
    $hashedPassword = '';
}

// ──────────────── FALLBACK (no .env found) ────────────────
if (empty($hashedPassword)) {
    echo "⚠️ No .env file or ADMIN_PASSWORD_HASH found. Please set one.";
    exit;
}

// ──────────────── LOGIN HANDLER ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['password'];

    if (password_verify($input, $hashedPassword)) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "❌ Incorrect password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 5rem; }
        input[type=password] { padding: 0.5rem; width: 250px; }
        button { padding: 0.5rem 1rem; margin-left: 10px; }
        p { color: red; }
    </style>
</head>
<body>

<h2>Admin Login</h2>

<?php if (!empty($error)) echo "<p>$error</p>"; ?>

<form method="POST">
    <input type="password" name="password" placeholder="Enter admin password" required>
    <button type="submit">Login</button>
</form>

</body>
</html>
