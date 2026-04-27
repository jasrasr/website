<?php include __DIR__ . '/header.php'; ?>
<h1>Timeclock Photo Logger</h1>
<div class="card">
    <p>Upload a clock-out slip photo. The app saves the image, attempts OCR if configured, then sends you to a review screen before writing to JSON.</p>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <label for="slip">Clock-out slip photo</label>
        <input type="file" id="slip" name="slip" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit">Upload and Review</button>
    </form>
</div>
<div class="notice">
    OCR mode is currently set to <strong><?= h(OCR_MODE) ?></strong>. Manual mode is safe on shared hosting and avoids hallucinated payroll math. Tiny receipt printers are basically gremlins with toner.
</div>
<?php include __DIR__ . '/footer.php'; ?>
