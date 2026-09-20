<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Password arrives via stdin, never command-line arguments or generated output.
if ($argc !== 3) { fwrite(STDERR, "Usage: php setup.php /private/storage username < password-input\n"); exit(1); }
$destination = __DIR__ . '/config.local.php';
if (file_exists($destination)) { fwrite(STDERR, "Config already exists; edit it privately to make changes.\n"); exit(1); }
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 72 || trim($argv[2]) === '') { fwrite(STDERR, "Use a username and a 12–72 byte password.\n"); exit(1); }
umask(0077);
if ($argv[1][0] !== '/') { fwrite(STDERR, "Use an absolute private storage path.\n"); exit(1); }
if (!is_dir($argv[1]) && !mkdir($argv[1], 0700, true)) exit(1);
$config = require __DIR__ . '/config.example.php';
$config['storage'] = realpath($argv[1]);
$config['username'] = $argv[2];
$config['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
$config['secret'] = bin2hex(random_bytes(32));
$handle = fopen($destination, 'x');
if (!$handle) exit(1);
fwrite($handle, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n");
fclose($handle);
echo "Configuration created. Review origins and excluded paths, then open the HTTPS dashboard.\n";
