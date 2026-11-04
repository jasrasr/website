<?php

declare(strict_types=1);

function project_last_modified_date(string $projectRoot): string
{
    $excludedDirectories = ['.git', 'node_modules', 'storage', 'vendor'];
    $directory = new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        static function (SplFileInfo $item) use ($excludedDirectories): bool {
            return !$item->isDir() || !in_array($item->getFilename(), $excludedDirectories, true);
        }
    );
    $latestModified = 0;
    foreach (new RecursiveIteratorIterator($filter) as $item) {
        if ($item->isFile()) {
            $latestModified = max($latestModified, $item->getMTime());
        }
    }
    $modified = new DateTimeImmutable('@' . ($latestModified > 0 ? $latestModified : time()));
    return $modified->setTimezone(new DateTimeZone('America/New_York'))->format('n/j/y');
}
