<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 3 || $argc > 4 || ($argc === 4 && $argv[3] !== '--write')) {
    fwrite(STDERR, "Usage: php install.php /source/root https://stats-host/path/assets/js/tracker.js [--write]\n"); exit(1);
}
$root = realpath($argv[1]);
$url = $argv[2];
if (!$root || !is_dir($root) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') exit(1);
$write = ($argv[3] ?? '') === '--write';
$skip = ['.git','node_modules','vendor','webstats','1-Framework','data','storage','tests'];
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator($directory, static fn(SplFileInfo $file): bool => !$file->isLink() && !in_array($file->getFilename(), $skip, true));
foreach (new RecursiveIteratorIterator($filter) as $file) {
    if (!in_array(strtolower($file->getExtension()), ['php','html','htm'], true)) continue;
    $path = $file->getPathname(); $source = file_get_contents($path);
    if ($source === false || str_contains($source, $url)) continue;
    $tokens = token_get_all($source); $result = ''; $changed = false;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        if (!$changed && is_array($token) && $token[0] === T_INLINE_HTML) {
            $count = 0;
            $newline = str_contains($text, "\r\n") ? "\r\n" : "\n";
            $text = preg_replace_callback('~^([ \t]*)</body>[ \t]*\r?$~mi', static fn(array $match): string =>
                $match[1] . '<script defer src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>' . $newline . $match[0], $text, 1, $count);
            $changed = $count > 0;
        }
        $result .= $text;
    }
    echo ($changed ? ($write ? 'WRITE ' : 'WOULD UPDATE ') : 'SKIP (manual integration) ') . $path . "\n";
    if ($changed && $write && file_put_contents($path, $result) !== strlen($result)) { fwrite(STDERR, "Write failed.\n"); exit(1); }
}
