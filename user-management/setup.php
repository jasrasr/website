<?php
declare(strict_types=1);
// CLI only. Password is read from stdin, never a command-line argument.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/bootstrap.php';
try {
    if ($argc !== 3) throw new InvalidArgumentException('Usage: php user-management/setup.php USERNAME "Display Name" < private-password-file');
    $password = rtrim((string)fgets(STDIN), "\r\n");
    $directory = new \Jasr\Users\Directory(jasr_users_config()['data_path']);
    $directory->setup($argv[1], $argv[2], $password);
    fwrite(STDOUT, "Administrator created. Sign in to user-management.\n");
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Setup failed. Check PHP configuration and private storage permissions.\n"); exit(1);
}
