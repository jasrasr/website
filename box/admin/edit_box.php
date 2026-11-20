<?php
/*
===========================================================
 File Name: edit_box.php
 Author: Jason Lamb (with help from ChatGPT)
 Created Date: 2026-01-18
 Modified Date: 2026-11-20
 Revision: 1.2.0

 Changelog:
   Rev 1.2.0 - Remove individual items from the draft and undo the latest removal.
   Rev 1.0 - Initial version, edit box contents
   Rev 1.1 - Added Ctrl+Enter / Cmd+Enter keyboard shortcut to save
===========================================================
*/

require_once __DIR__ . '/../lib/auth.php';
$user = box_require_user('member');

date_default_timezone_set('America/New_York');

require_once __DIR__ . '/../lib/data.php';

$code = $_GET['c'] ?? '';
$box  = getBox($code);

if (!$box || !box_owned_by($box, $user)) {
    http_response_code(404);
    echo 'Box not found.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    box_require_post();
    $rawItems = $_POST['items'] ?? '';

    // Convert textarea lines into clean array
    $items = array_filter(
        array_map('trim', explode("\n", $rawItems)),
        static fn(string $item): bool => $item !== ''
    );

    $box['items']   = $items;
    $box['count']   = count($items);
    $box['updated'] = date('c');

    if (!saveBox($code, $box)) {
        http_response_code(500);
        exit('Could not save the box.');
    }

    header('Location: index.php', true, 303);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Box</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h2>Edit Box: <?=htmlspecialchars($box['name'])?></h2>

<form method="post" id="editForm">
  <input type="hidden" name="csrf" value="<?=htmlspecialchars(box_auth()->csrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>">

  <div class="mb-3">
    <label for="itemsTextarea" class="form-label">Items (one per line)</label>
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

  <section id="item-removal" class="mb-3" aria-labelledby="item-removal-heading" hidden>
    <h3 id="item-removal-heading" class="h5">Remove individual items</h3>
    <p class="text-body-secondary small mb-2">Removals are saved when you select Save Items.</p>
    <ul id="draft-items" class="list-group mb-2"></ul>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span id="item-removal-status" class="small" role="status"></span>
      <button id="undo-item-removal" type="button" class="btn btn-outline-secondary btn-sm" hidden>Undo last removal</button>
    </div>
  </section>

  <button type="submit" class="btn btn-primary">Save Items</button>
  <a href="index.php" class="btn btn-secondary">Cancel</a>

</form>

<script>
const form = document.getElementById('editForm');
const textarea = document.getElementById('itemsTextarea');
const removal = document.getElementById('item-removal');
const list = document.getElementById('draft-items');
const removalStatus = document.getElementById('item-removal-status');
const undo = document.getElementById('undo-item-removal');
let previousDraft = null;

function renderDraftItems() {
    list.replaceChildren();
    textarea.value.split('\n').forEach((line, index) => {
        const name = line.trim();
        if (!name) return;

        const item = document.createElement('li');
        item.className = 'list-group-item d-flex align-items-center justify-content-between gap-3';
        const label = document.createElement('span');
        label.className = 'text-break';
        label.textContent = name;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-danger btn-sm flex-shrink-0';
        button.textContent = 'Remove';
        button.setAttribute('aria-label', 'Remove ' + name + ' (line ' + (index + 1) + ')');
        button.addEventListener('click', () => {
            const lines = textarea.value.split('\n');
            previousDraft = textarea.value;
            lines.splice(index, 1);
            textarea.value = lines.join('\n');
            renderDraftItems();
            removalStatus.textContent = 'Removed ' + name + ' from the draft.';
            undo.focus();
        });

        item.append(label, button);
        list.append(item);
    });
    undo.hidden = previousDraft === null;
    removal.hidden = list.childElementCount === 0 && undo.hidden;
}

textarea.addEventListener('input', () => {
    previousDraft = null;
    removalStatus.textContent = '';
    renderDraftItems();
});
undo.addEventListener('click', () => {
    textarea.value = previousDraft;
    previousDraft = null;
    renderDraftItems();
    removalStatus.textContent = 'Item restored to the draft.';
    textarea.focus();
});
textarea.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        form.requestSubmit();
    }
});
renderDraftItems();
</script>

<p class="text-body-secondary small mt-3">Version 1.2.0 · Updated 2026-11-20</p>

</body>
</html>
