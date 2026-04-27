<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
$file = basename($_GET['file'] ?? '');
$parsedPath = DATA_DIR . '/' . $file . '.parsed.json';
$parsed = file_exists($parsedPath) ? json_decode(file_get_contents($parsedPath), true) : [];
$defaults = parseClockSlipText('');
$data = array_merge($defaults, is_array($parsed) ? $parsed : []);
include __DIR__ . '/header.php';
?>
<h1>Review Entry</h1>
<div class="notice">Review every field before saving. OCR is helpful, but it also occasionally reads “10:10 PM” as “banana cannon.”</div>
<div class="card">
    <?php if ($file && file_exists(UPLOAD_DIR . '/' . $file)): ?>
        <p><img class="preview" src="uploads/<?= h($file) ?>" alt="Uploaded slip preview"></p>
    <?php endif; ?>
    <form action="save_entry.php" method="post">
        <input type="hidden" name="source_file" value="<?= h($file) ?>">
        <label>Employee</label><input name="employee" value="<?= h($data['employee']) ?>" required>
        <label>Date</label><input type="date" name="date" value="<?= h($data['date']) ?>" required>
        <label>Time In</label><input name="time_in" value="<?= h($data['time_in']) ?>" placeholder="3:35 PM" required>
        <label>Time Out</label><input name="time_out" value="<?= h($data['time_out']) ?>" placeholder="10:10 PM" required>
        <label>Printed Hours This Shift</label><input name="printed_shift_hours" value="<?= h($data['printed_shift_hours']) ?>" placeholder="06:35">
        <label>Printed Hours This Week</label><input name="printed_week_hours" value="<?= h($data['printed_week_hours']) ?>" placeholder="31:27">
        <label>OCR Text</label><textarea name="ocr_text" rows="8"><?= h($data['ocr_text']) ?></textarea>
        <button type="submit">Save to JSON Log</button>
    </form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
