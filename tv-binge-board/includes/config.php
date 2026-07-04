<?php
/**
 * File: includes/config.php
 * Project: TV Binge Board
 * Description: Application configuration, version constants, timezone, session/remember-me lifetimes, TMDB constants, local cache paths, and optional local overrides.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.11
 */
declare(strict_types=1);


const APP_NAME = 'TV Binge Board';
const APP_VERSION = '1.5.11';
const APP_TIMEZONE = 'America/New_York';
const APP_SESSION_NAME = 'tv_binge_board_session';
const APP_DEFAULT_POSTER = 'assets/img/poster-placeholder.svg';
const APP_PUBLIC_SITE_NOTE = 'This site tracks watch history. It does not stream TV shows or movies.';
const APP_MAX_LOGIN_FAILURES = 5;
const APP_LOGIN_LOCKOUT_SECONDS = 900;
const APP_MAX_UPLOAD_BYTES = 5242880;
const APP_SESSION_COOKIE_SECONDS = 31536000;
const APP_REMEMBER_ME_SECONDS = 31536000;
const APP_REMEMBER_ME_MAX_TOKENS_PER_USER = 10;

date_default_timezone_set(APP_TIMEZONE);

define('APP_ROOT', dirname(__DIR__));
define('APP_DATA_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'data');
define('APP_CACHE_DIR', APP_DATA_DIR . DIRECTORY_SEPARATOR . 'cache');
define('APP_UPLOADS_DIR', APP_DATA_DIR . DIRECTORY_SEPARATOR . 'uploads');
define('APP_BACKUP_DIR', APP_DATA_DIR . DIRECTORY_SEPARATOR . 'backups');
define('APP_PUBLIC_CACHE_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'public-cache');
define('APP_PUBLIC_CACHE_URL', 'public-cache');

// Put TMDB credentials in includes/config.local.php rather than source control.
const TMDB_API_KEY = '';
const TMDB_API_READ_ACCESS_TOKEN = '';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/w342';
const TMDB_IMAGE_BASE_SMALL = 'https://image.tmdb.org/t/p/w185';
const TMDB_IMAGE_BASE_MEDIUM = 'https://image.tmdb.org/t/p/w500';
const TMDB_IMAGE_BASE_STILL = 'https://image.tmdb.org/t/p/w300';
const TMDB_IMAGE_BASE_ORIGINAL = 'https://image.tmdb.org/t/p/original';

$localConfig = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}
