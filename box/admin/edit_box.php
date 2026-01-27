<?php
/*
===========================================================
 File: edit_box.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-18
 Modified: 2026-01-18
 Revision: 1.1

 Changes:
   Rev 1.0 - Initial version, edit box contents
   Rev 1.1 - Added Ctrl+Enter / Cmd+Enter keyboard shortcut to save
===========================================================
*/

// start auth
require_once __DIR__ . '/../lib/auth.php';
requireLogin();
$currentUser = $_SESSION['user'];
// end auth

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/../lib/data.php';

// TEMP user placeholder (authentication added later)
$currentUser = 'jason';

$code = $_GET['c'] ?? '';
$box  = getBox($code);

if (!$box || $box['owner'] !== $currentUser) {
    echo "Box not found or access denied.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawItems = $_POST['items'] ?? '';

    // Convert textarea lines into clean array
    $items = array_filter(
        array_map('trim', explode("\n", $rawItems))
    );

    $box['items']   = $items;
    $box['count']   = count($items);
    $box['updated'] = date('c');

    saveBox($code, $box);

    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Box</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h2>Edit Box: <?=htmlspecialchars($box['name'])?></h2>

<form method="post" id="editForm">

  <div class="mb-3">
    <label class="form-label">Items (one per line)</label>
    <textarea
      name="items"
      id="itemsTextarea"
      rows="10"
      class="form-control"
    ><?=htmlspecialchars(implode("\n", $box['items']))?></textarea>

    <div class="form-text">
      Tip: Press <strong>Ctrl + Enter</strong> (or <strong>Cmd + Enter</strong> on Mac) to save
    </div>
  </div>

  <button class="btn btn-primary">Save Items</button>
  <a href="index.php" class="btn btn-secondary">Cancel</a>

</form>

<script>
document.getElementById('itemsTextarea').addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('editForm').submit();
    }
});
</script>

</body>
</html>
