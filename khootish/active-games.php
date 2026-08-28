<?php
declare(strict_types=1);
require __DIR__ . '/game_registry.php';
require_admin_api();
json_response(['ok'=>true,'games'=>khootish_active_games()]);
