<?php
/*
    Filename    : manual.php
    Revision    : 1.2.0
    Description : Manual time entry form — submit a shift without uploading a photo
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.2.0 removed unit, job, and tip fields
*/
include __DIR__ . '/header.php';
?>
<h1>Manual Entry</h1>
<div class="card">
<form action="save_entry.php" method="post">
    <input type="hidden" name="source_file" value="manual-entry">
    <label>Employee</label><input name="employee" required>
    <label>Date</label><input type="date" name="date" value="<?= h(date('Y-m-d')) ?>" required>
    <label>Time In</label><input name="time_in" placeholder="3:35 PM" required>
    <label>Time Out</label><input name="time_out" placeholder="10:10 PM" required>
    <label>Printed Hours This Shift</label><input name="printed_shift_hours" placeholder="06:35">
    <label>Printed Hours This Week</label><input name="printed_week_hours" placeholder="31:27">
    <label>Notes</label><textarea name="ocr_text" rows="5"></textarea>
    <button type="submit">Save Manual Entry</button>
</form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
