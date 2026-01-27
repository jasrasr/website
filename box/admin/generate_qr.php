<?php
/*
===========================================================
 File: create_box.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-10
 Modified: 2026-01-19
 Revision: 1.2

 Description:
   Creates a new storage box owned by the logged-in user.
   Automatically generates a QR code PNG at creation time.
   Box ID is immutable; box name is editable later.

 Changes:
   Rev 1.0 - Initial box creation
   Rev 1.1 - Added ownership and timestamp handling
   Rev 1.2 - Auto-generate QR code on box creation
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';
requireLogin();

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

date_default_timezone_set('America/New_York');

$currentUser = $_SESSION['user'];

$error = '';

/**
 * Generate a unique, non-guessable box ID
 * Format: BOX + 6 uppercase alphanumeric chars
 */
function generateBoxCode(array $existingBoxes): string {
    do {
        $code = 'BOX' . strtoupper(bin2hex(random_bytes(3)));
    } while (isset($existingBoxes[$code]));

    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $boxName = trim($_POST['box_name'] ?? '');

    if ($boxName === '') {
        $error = 'Box name is required.';
    } else {
        $data = loadBoxData();
        $boxes = $data['boxes'] ?? [];

        $boxCode = generateBoxCode($boxes);

        $boxes[$boxCode] = [
            'name'    => $boxName,
            'owner'   => $currentUser,
            'items'   => [],
            'count'   => 0,
            'updated' => date('c') // ISO-8601 in EST
        ];

        $data['boxes'] = $boxes;
        saveBoxData($data);

        // 🔑 AUTO-GENERATE QR CODE
        generateBoxQR($boxCode);

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create New Box</title>
  <link rel="stylesheet" href="../lib/admin.css">
</head>
<body>

<h1>Create New Box</h1>

<?php if ($error): ?>
  <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Box Name</label>
    <input
      type="text"
      name="box_name"
      class="form-control"
      required
      autofocus
      placeholder="e.g. Garage Tools"
    >
  </div>

  <button class="btn btn-success">Create Box</button>
  <a href="index.php" class="btn btn-secondary">Cancel</a>
</form>

</body>
</html>
