<?php
// MPG central administrative authorization. Revision 1.0.0.
// Must run before device_init.php and before page output.
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
$mpgAuth = \Jasr\Framework\SharedIdentity::connect();
$mpgAdmin = $mpgAuth->requireProject('mpg', 'admin');
