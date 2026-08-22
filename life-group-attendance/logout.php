<?php
require_once __DIR__ . '/lib.php';
if (user()) audit('logout','user',user()['id']);
$_SESSION=[]; session_destroy(); header('Location: login.php');

