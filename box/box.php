<?php
/*
===========================================================
 File: box.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-18
 Modified: 2026-01-18
 Revision: 1.1

 Changes:
   Rev 1.0 - Initial public read-only box view
   Rev 1.1 - update time to EST by default
===========================================================
*/

require_once __DIR__ . '/lib/util.php';
date_default_timezone_set('America/New_York');


require_once __DIR__ . '/lib/data.php';

$code = $_GET['c'] ?? '';
$box  = getBox($code);

if (!$box) {
    http_response_code(404);
    echo "Invalid or unknown box code.";
    exit;
}

// Defensive defaults (JSON hygiene)
$name    = $box['name']    ?? 'Unnamed Box';
$items   = $box['items']   ?? [];
$count   = $box['count']   ?? count($items);
$updated = $box['updated'] ?? 'Unknown';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($name)?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  body {
    background-color: #f8f9fa;
  }
  .box-container {
    max-width: 700px;
  }
</style>
</head>

<body>

<div class="container box-container mt-4">

  <div class="card shadow-sm">
    <div class="card-body">

      <h3 class="card-title"><?=htmlspecialchars($name)?></h3>

      <p class="text-muted mb-1">
        <strong>Total items:</strong> <?=intval($count)?>
      </p>

      <p class="text-muted mb-3">
        <strong>Last updated:</strong> <?=htmlspecialchars(formatTimestampLocal($updated))?>

      </p>

      <?php if (empty($items)): ?>
        <div class="alert alert-secondary">
          This box is currently empty.
        </div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($items as $item): ?>
            <li class="list-group-item">
              <?=htmlspecialchars($item)?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>

</div>

</body>
</html>
