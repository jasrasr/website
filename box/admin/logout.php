<?php
require_once __DIR__ . '/../lib/auth.php';
box_require_user();
box_require_post();
box_auth()->logout();
header('Location: ' . box_auth()->portal(), true, 303);
exit;
