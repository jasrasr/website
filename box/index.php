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

require __DIR__ . '/box.php';


$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');

// Break into segments
$segments = explode('/', $path);

// Expect exactly: box/BOXxxxxxx
if (count($segments) === 2 && $segments[0] === 'box') {

    $boxCode = $segments[1];

    // Validate box code format
    if (preg_match('/^BOX[A-Z0-9]{6}$/', $boxCode)) {
        $_GET['c'] = $boxCode;
        require __DIR__ . '/box.php';
        exit;
    }

    // Invalid-looking code
    http_response_code(404);
    echo 'Invalid or unknown box code.';
    exit;
}

// Otherwise: do nothing (let Apache handle it)
http_response_code(404);
echo 'Page not found.';
