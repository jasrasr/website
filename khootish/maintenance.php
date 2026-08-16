<?php
declare(strict_types=1);
require __DIR__ . '/game_registry.php';
$expired = khootish_expire_stale_games();
json_response(['ok'=>true,'expired'=>$expired]);
