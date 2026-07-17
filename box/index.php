<?php
/*
===========================================================
 File: box/index.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-14
 Modified: 2026-01-19
 Revision: 1.1

 Description:
   Router for pretty URLs.
   Routes /box/BOXxxxxxx to box.php.
===========================================================
*/

// Accept either a query string (?c=BOXxxxxxx) or a pretty URL whose LAST
// path segment is the box code. Prefix-agnostic so it works whether deployed
// at /box/ or /github/box/.
$boxCode = $_GET['c'] ?? '';

if ($boxCode === '') {
    $path     = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    $last     = end($segments);
    if ($last !== false && $last !== 'box' && $last !== 'index.php') {
        $boxCode = $last;
    }
}

// A valid box code -> render the box view
if (preg_match('/^BOX[A-Z0-9]{6}$/', $boxCode)) {
    $_GET['c'] = $boxCode;
    require __DIR__ . '/box.php';
    exit;
}

// Bare /box/ with no code, or a malformed code
http_response_code(404);
echo 'Invalid or unknown box code.';
