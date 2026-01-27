<?php
/*
===========================================================
 File: admin/index.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-18
 Modified: 2026-01-18
 Revision: 1.3

 Description:
   Admin dashboard for managing storage boxes.
   Displays all boxes owned by the logged-in user, including
   item count, last updated timestamp (local timezone),
   and available actions (edit, generate QR).

 Changes:
   Rev 1.0 - Initial admin dashboard listing boxes
   Rev 1.1 - Added local timezone timestamp formatting
   Rev 1.2 - add links for mulitple print sizes
   Rev 1.3 - add authorization
===========================================================
*/

// start auth
require_once __DIR__ . '/../lib/auth.php';
requireLogin();
$currentUser = $_SESSION['user'];
// end auth

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/util.php';


// TEMP user (auth comes later)
// $currentUser = 'jason';

$data = loadBoxData();
$boxes = $data['boxes'] ?? [];
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Box Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<!-- // start logout -->
<div class="mb-3 text-end">
  Logged in as <strong><?=htmlspecialchars($currentUser)?></strong> |
  <a href="logout.php">Logout</a>
</div>
<!-- //  end logout -->


<h2>Your Storage Boxes</h2>

<a href="create_box.php" class="btn btn-success mb-3">+ Create New Box</a>

<div class="mb-3">
  <a href="print_qr_pdf.php?size=small" class="btn btn-outline-primary me-2">
    Print Small Labels (16 / page)
  </a>

  <a href="print_qr_pdf.php?size=medium" class="btn btn-outline-primary me-2">
    Print Medium Labels (6 / page)
  </a>

  <a href="print_qr_pdf.php?size=large" class="btn btn-outline-primary">
    Print Large Labels (4 / page)
  </a>
  <a href="generate_missing_qr.php"
   class="btn btn-outline-secondary mb-3">
  Generate Missing QR Codes
</a>

</div>



<table class="table table-bordered table-striped">
<thead>
<tr>
  <th>Box Name</th>
  <th>Code</th>
  <th>Items</th>
  <th>Last Updated</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($boxes as $code => $box): ?>
  <?php if ($box['owner'] !== $currentUser) continue; ?>
  <tr>
    <td><?=htmlspecialchars($box['name'])?></td>
    <td><?=htmlspecialchars($code)?></td>
    <td><?=intval($box['count'])?></td>
    <td><?=htmlspecialchars(formatTimestampLocal($box['updated']))?></td>
    
    <td>
  <a href="edit_box.php?c=<?=urlencode($code)?>" class="btn btn-sm btn-primary mb-1">
    Edit Items
  </a>

  <a href="generate_qr.php?c=<?=urlencode($code)?>" class="btn btn-sm btn-outline-secondary">
    Generate QR
  </a>
</td>

  </tr>
<?php endforeach; ?>

</tbody>
</table>
<form method="post" action="delete_box.php"
      style="display:inline"
      onsubmit="return confirm('Delete this box permanently?');">

  <input type="hidden" name="code" value="<?=htmlspecialchars($code)?>">
  <button class="btn btn-sm btn-danger">
    Delete
  </button>
</form>

</body>
</html>
