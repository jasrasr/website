<?php
/*
===========================================================
 File: admin/upload_form.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   Web upload form (admin-only).
   - Submits to /api/upload.php.
   - Lets you select target directory.
   - Identifies source as "web".
===========================================================
*/

require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Secure Upload Form</title>
</head>
<body>
    <h1>Secure Upload Form</h1>
    <p>This form is intended for admin use only and submits to the JSON API endpoint.</p>

    <form action="../api/upload.php" method="post" enctype="multipart/form-data">
        <label for="directory">Destination directory :</label>
        <select name="directory" id="directory">
            <?php foreach ($allowedDirectories as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <label for="fileToUpload">Select file to upload :</label>
        <input type="file" name="fileToUpload" id="fileToUpload" required>
        <br><br>

        <input type="hidden" name="api" value="<?php echo htmlspecialchars(API_KEY); ?>">
        <input type="hidden" name="source" value="web">

        <button type="submit">Upload File</button>
    </form>
</body>
</html>
