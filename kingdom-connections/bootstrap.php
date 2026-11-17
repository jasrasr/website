<?php
/** Filename: bootstrap.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Kingdom Connections initialization. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/Directory.php';
$config = ['storage_path' => __DIR__ . '/storage'];
if (is_file(__DIR__ . '/config.local.php')) $config = array_replace($config, require __DIR__ . '/config.local.php');
$directory = new KingdomDirectory($config['storage_path'] . '/directory.json');
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ratingLabel(array $business): string {
    $ratings = $business['ratings'] ?? [];
    return $ratings ? number_format(array_sum(array_column($ratings, 'stars')) / count($ratings), 1) . ' / 5 · ' . count($ratings) . ' rating(s)' : 'Not yet rated';
}
