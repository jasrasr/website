<?php
/*
===========================================================
 File Name: admin/index.php
 Author: Jason Lamb (with help from ChatGPT)
 Created Date: 2026-01-18
 Modified Date: 2026-11-20
 Revision: 1.3.1

 Description:
   Admin dashboard for managing storage boxes.
   Displays all boxes owned by the logged-in user, including
   item count, last updated timestamp (local timezone),
   and available actions (edit, generate QR).

 Changelog:
   Rev 1.3.1 - Show batch creation results.
   Rev 1.0 - Initial admin dashboard listing boxes
   Rev 1.1 - Added local timezone timestamp formatting
   Rev 1.2 - add links for mulitple print sizes
   Rev 1.3 - add authorization
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user();
$currentUser = $user['username'];
$canEdit = box_auth()->can('box', 'member');
$csrf = htmlspecialchars(box_auth()->csrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/util.php';


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

<div class="mb-3 text-end">
  Logged in as <strong><?=htmlspecialchars($currentUser)?></strong> |
  <form method="post" action="logout.php" class="d-inline">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <button type="submit" class="btn btn-link p-0">Logout</button>
  </form>
</div>


<h2>Your Storage Boxes</h2>

<?php
$created = filter_input(INPUT_GET, 'created', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
$missing = filter_input(INPUT_GET, 'missing_qr', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]);
?>
<?php if ($created): ?>
  <div class="alert alert-success" role="status"><?=$created === 1 ? '1 box created.' : $created . ' boxes created.'?></div>
  <?php if ($missing > 0 && $missing <= $created): ?>
    <div class="alert alert-warning" role="alert">
      Some QR codes could not be generated. Use Generate Missing QR Codes to try again.
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($canEdit): ?><a href="create_box.php" class="btn btn-success mb-3">+ Create Boxes</a><?php endif; ?>

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
  <?php if ($canEdit): ?>
  <form method="post" action="generate_missing_qr.php" class="d-inline">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <button class="btn btn-outline-secondary mb-3">Generate Missing QR Codes</button>
  </form>
  <?php endif; ?>

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
  <?php if (!box_owned_by($box, $user)) continue; ?>
  <tr>
    <td><?=htmlspecialchars($box['name'])?></td>
    <td><?=htmlspecialchars($code)?></td>
    <td><?=intval($box['count'])?></td>
    <td><?=htmlspecialchars(formatTimestampLocal($box['updated']))?></td>
    
    <td>
  <?php if ($canEdit): ?>
  <a href="edit_box.php?c=<?=urlencode($code)?>" class="btn btn-sm btn-primary mb-1">
    Edit Items
  </a>
  <form method="post" action="generate_qr.php" class="d-inline">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="code" value="<?=htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>">
    <button class="btn btn-sm btn-outline-secondary">Generate QR</button>
  </form>
  <form method="post" action="delete_box.php" class="d-inline" onsubmit="return confirm('Delete this box permanently?');">
    <input type="hidden" name="csrf" value="<?=$csrf?>">
    <input type="hidden" name="code" value="<?=htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>">
    <button class="btn btn-sm btn-danger">Delete</button>
  </form>
  <?php endif; ?>
</td>

  </tr>
<?php endforeach; ?>

</tbody>
</table>

</body>
</html>
