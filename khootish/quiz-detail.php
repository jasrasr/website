<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

require_admin_api();
$id = clean_id((string)($_GET['id'] ?? ''));
if ($id === '') json_response(['ok'=>false,'error'=>'Quiz ID is required.'],422);
$quiz = read_quiz($id);
if (!$quiz) json_response(['ok'=>false,'error'=>'Quiz not found.'],404);
json_response(['ok'=>true,'quiz'=>$quiz]);
