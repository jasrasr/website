<?php
/*
===========================================================
 File: admin/login.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-19
 Modified: 2026-01-19
 Revision: 1.0

 Description:
   Admin login page.
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password';
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">

<h3>Admin Login</h3>

<?php if ($error): ?>
<div class="alert alert-danger"><?=$error?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input name="username" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Password</label>
    <input name="password" type="password" class="form-control" required>
  </div>

  <button class="btn btn-primary">Login</button>
</form>

</body>
</html>
