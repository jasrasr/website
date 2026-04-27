<?php include __DIR__ . '/header.php'; ?>
<h1>Manual Entry</h1>
<div class="card">
<form action="save_entry.php" method="post">
    <input type="hidden" name="source_file" value="manual-entry">
    <label>Employee</label><input name="employee" required>
    <label>Unit</label><input name="unit">
    <label>Date</label><input type="date" name="date" value="<?= h(date('Y-m-d')) ?>" required>
    <label>Job</label><input name="job" value="Crew">
    <label>Time In</label><input name="time_in" placeholder="3:35 PM" required>
    <label>Time Out</label><input name="time_out" placeholder="10:10 PM" required>
    <label>Printed Hours This Shift</label><input name="printed_shift_hours" placeholder="06:35">
    <label>Printed Hours This Week</label><input name="printed_week_hours" placeholder="31:27">
    <label>Carry Over Tips</label><input name="carry_over_tips" value="0.00">
    <label>Declared Tips</label><input name="declared_tips" value="0.00">
    <label>Charge Tips</label><input name="charge_tips" value="0.00">
    <label>Notes / OCR Text</label><textarea name="ocr_text" rows="5"></textarea>
    <button type="submit">Save Manual Entry</button>
</form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
