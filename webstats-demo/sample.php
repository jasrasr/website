<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
// The same interactive fixture rendered through PHP. Its page URL remains sample.php.
readfile(__DIR__ . '/index.html');
