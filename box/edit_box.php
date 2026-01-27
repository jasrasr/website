<?php

date_default_timezone_set('America/New_York');

require_once 'lib.php';

$code = $_GET['c'] ?? '';
$data = loadData();
$box = &$data['boxes'][$code];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = array_map('trim', explode("\n", $_POST['items'] ?? ''));
    $box['items'] = $items;
    $box['updated'] = date('c'); // ISO datetime
    saveData($data);
    header("Location: box.php?c=$code");
    exit;
}
?>

<form method="post">
    <h3>Edit <?=htmlspecialchars($box['name'])?></h3>
    <textarea name="items" rows="8" class="form-control"><?=htmlspecialchars(implode("\n",$box['items']))?></textarea>
    <button class="btn btn-primary mt-2">Save</button>
</form>
