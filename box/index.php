<?php
/*
===========================================================
 File: box/index.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-14
 Modified: 2026-07-16
 Revision: 1.3

 Description:
   Router + landing page for the QR box viewer.
   - ?c=BOXxxxxxx or a pretty URL whose last segment is the code
     loads the box view (box.php). Prefix-agnostic: works at
     /box/ and /github/box/.
   - Any other request shows a friendly landing page with a
     code-entry form.

 Changes:
   1.1 - pretty URL router
   1.2 - remove stray box.php require; prefix-agnostic code parse
   1.3 - branded landing page + code-entry form for bare /box/
===========================================================
*/

require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/data.php';

// Resolve the box code from a query string or the last path segment.
$boxCode = $_GET['c'] ?? '';

if ($boxCode === '') {
    $path     = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    $last     = end($segments);
    if ($last !== false && $last !== 'box' && $last !== 'index.php') {
        $boxCode = $last;
    }
}

$boxCode  = strtoupper(trim($boxCode));
$attempted = $boxCode !== '';            // did the visitor supply a code?
$box       = null;

if (preg_match('/^BOX[A-Z0-9]{6}$/', $boxCode)) {
    $box = getBox($boxCode);
    if ($box) {
        // Valid, existing box -> render the read-only box view.
        $_GET['c'] = $boxCode;
        require __DIR__ . '/box.php';
        exit;
    }
}

// No valid box to show -> landing page (with an error note if they tried a code).
http_response_code($attempted ? 404 : 200);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Box Lookup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background-color: #f8f9fa; }
  .box-container { max-width: 700px; }
  .box-emoji { font-size: 2.5rem; line-height: 1; }
  .code-input { text-transform: uppercase; letter-spacing: .05em; }
</style>
</head>
<body>

<div class="container box-container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">

      <div class="d-flex align-items-center gap-3 mb-3">
        <span class="box-emoji">📦</span>
        <div>
          <h3 class="card-title mb-0">Box Lookup</h3>
          <div class="text-muted">Find what's inside a storage box</div>
        </div>
      </div>

      <?php if ($attempted): ?>
        <div class="alert alert-warning">
          We couldn't find a box with the code
          <strong><?=htmlspecialchars($boxCode)?></strong>.
          Double-check it and try again.
        </div>
      <?php endif; ?>

      <p class="text-muted">
        Every storage box has a QR sticker. <strong>Scan the sticker</strong> to
        jump straight to that box, or enter its code below. Codes look like
        <code>BOXAB12CD</code>.
      </p>

      <form method="get" action="" class="row g-2 align-items-center mt-2">
        <div class="col-12 col-sm-auto flex-grow-1">
          <label for="c" class="visually-hidden">Box code</label>
          <input
            type="text"
            id="c"
            name="c"
            class="form-control code-input"
            placeholder="BOXAB12CD"
            value="<?=$attempted ? htmlspecialchars($boxCode) : ''?>"
            pattern="[Bb][Oo][Xx][A-Za-z0-9]{6}"
            title="A box code is BOX followed by 6 letters or numbers."
            autocomplete="off"
            autofocus
            required>
        </div>
        <div class="col-12 col-sm-auto">
          <button type="submit" class="btn btn-primary w-100">View box</button>
        </div>
      </form>

    </div>
  </div>

  <p class="text-center text-muted small mt-3 mb-4">
    Tip: bookmark a box by its code URL, e.g. <code>?c=BOXAB12CD</code>.
  </p>
</div>

</body>
</html>
