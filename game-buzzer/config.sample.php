<?php
// Copy to config.local.php on the host only; never commit the real password.
return [
    'create_password' => 'REPLACE-WITH-A-LONG-PRIVATE-PASSWORD',
    // Prefer an absolute directory outside public_html. Must be writable by PHP.
    'data_dir' => __DIR__ . '/data',
];
