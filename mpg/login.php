<?php
// MPG login entry point, revision 2.0.0.
// Authentication and password recovery are owned by User Management.
require_once __DIR__ . '/admin_auth.php';
header('Location: admin.php', true, 302);
exit;
