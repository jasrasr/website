<?php
/*
===========================================================
 File Name: create_box.php
 Author: Jason Lamb (with help from ChatGPT)
 Created Date: 2026-01-10
 Modified Date: 2026-11-20
 Revision: 1.3.0

 Description:
   Creates one or more storage boxes owned by the logged-in user.
   Automatically generates a QR code PNG at creation time.
   Box ID is immutable; box name is editable later.

 Changelog:
   Rev 1.3.0 - Create multiple boxes from one name per line.
   Rev 1.2.1 - Match the Box lookup and dashboard styling.
   Rev 1.0 - Initial box creation
   Rev 1.1 - Added ownership and timestamp handling
   Rev 1.2 - Auto-generate QR code on box creation
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');

require_once __DIR__ . '/../lib/data.php';
require_once __DIR__ . '/../lib/qr.php';

date_default_timezone_set('America/New_York');

$error = '';
$entry = '';
$limit = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    box_require_post();
    $submitted = $_POST['box_name'] ?? '';
    $entry = is_string($submitted) ? $submitted : '';
    $names = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $entry)),
        static fn(string $name): bool => $name !== ''
    ));

    if ($names === []) {
        $error = 'Enter at least one box name.';
    } elseif (count($names) > $limit) {
        $error = "Enter up to $limit box names at a time.";
    } else {
        $owner = box_new_owner($user);
        $updated = date('c');
        $codes = [];
        if (updateBoxData(static function (array $data) use ($names, $owner, $updated, &$codes): array {
            foreach ($names as $name) {
                $code = generateBoxCode($data['boxes']);
                $data['boxes'][$code] = [
                    'name' => $name,
                    'owner' => $owner,
                    'items' => [],
                    'count' => 0,
                    'updated' => $updated,
                ];
                $codes[] = $code;
            }
            return $data;
        })) {
            $missing = 0;
            foreach ($codes as $code) {
                try {
                    generateBoxQR($code);
                    if (!qrExists($code)) {
                        $missing++;
                    }
                } catch (Throwable $exception) {
                    $missing++;
                    error_log('Could not generate a Box QR code: ' . $code);
                }
            }
            header('Location: index.php?created=' . count($codes) . '&missing_qr=' . $missing, true, 303);
            exit;
        }

        http_response_code(500);
        $error = 'Could not save the boxes. Your names are still in the form.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create New Boxes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .create-box-container { max-width: 640px; }
    .create-box-icon {
      width: 3.5rem;
      height: 3.5rem;
      display: grid;
      place-items: center;
      font-size: 1.75rem;
    }
  </style>
</head>
<body class="bg-body-tertiary">

<main class="container create-box-container py-4 py-sm-5">
  <a href="index.php" class="d-inline-block fw-semibold text-decoration-none mb-3">
    <span aria-hidden="true">←</span> Back to dashboard
  </a>

  <div class="card shadow-sm overflow-hidden">
    <header class="bg-success-subtle border-bottom p-3 p-sm-4 d-flex align-items-center gap-3">
      <span class="create-box-icon bg-white border rounded-3 flex-shrink-0" aria-hidden="true">📦</span>
      <div>
        <h1 class="h3 mb-1">Create New Boxes</h1>
        <p class="text-body-secondary mb-0">Create storage boxes and generate a QR code for each one.</p>
      </div>
    </header>

    <div class="card-body p-3 p-sm-4">
      <?php if ($error): ?>
        <div id="box-name-error" class="alert alert-danger" role="alert"><?=htmlspecialchars($error)?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(box_auth()->csrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>">
        <label for="box-name" class="form-label fw-semibold">Box names</label>
        <textarea
          rows="5"
          id="box-name"
          name="box_name"
          class="form-control form-control-lg<?=$error ? ' is-invalid' : ''?>"
          <?php if ($error): ?>aria-invalid="true" <?php endif; ?>
          required
          aria-describedby="box-name-help<?=$error ? ' box-name-error' : ''?>"
        ><?=htmlspecialchars($entry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></textarea>
        <div id="box-name-help" class="form-text">Enter one name per line, up to <?=$limit?> boxes. Blank lines are skipped.</div>
        <div class="d-grid d-sm-flex justify-content-sm-end mt-4">
          <button type="submit" class="btn btn-success px-4">Create Boxes</button>
        </div>
      </form>
    </div>
  </div>

  <p class="text-center text-body-secondary small mt-3 mb-0">Version 1.3.0 · Updated 2026-11-20</p>
</main>

</body>
</html>
