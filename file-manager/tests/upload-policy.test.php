<?php
require_once __DIR__ . '/../file-policy.php';

foreach (['report.pdf', 'photo.JPG', 'clip.mp4', 'song.flac', 'notes.md'] as $name) {
    if (!file_manager_allowed_filename($name)) {
        throw new RuntimeException("Expected allowed file: $name");
    }
}

foreach (['shell.php', 'shell.php.jpg', 'shell.phtml.png', 'page.html', 'active.svg',
          '.env', 'script.js', 'backup.zip', '../photo.jpg', "bad\nname.pdf"] as $name) {
    if (file_manager_allowed_filename($name)) {
        throw new RuntimeException("Expected blocked file: $name");
    }
}

echo "Upload policy checks passed.\n";
