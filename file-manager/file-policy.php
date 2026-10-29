<?php
declare(strict_types=1);

function file_manager_allowed_filename(string $filename): bool
{
    if ($filename === '' || $filename[0] === '.' || preg_match('/[\x00-\x1F\x7F\\\\\/]/', $filename)) {
        return false;
    }

    // Apache can treat an earlier extension as a handler (for example, file.php.jpg).
    if (preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|py|rb|sh|asp|aspx|jsp|shtml)(?:\.|$)/i', $filename)) {
        return false;
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, [
        // Documents
        'pdf', 'txt', 'md', 'csv', 'rtf', 'doc', 'docx', 'xls', 'xlsx',
        'ppt', 'pptx', 'odt', 'ods', 'odp',
        // Pictures (SVG is excluded because it can contain active script)
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'heif',
        // Video
        'mp4', 'm4v', 'mov', 'webm', 'avi', 'mkv', 'mpg', 'mpeg',
        // Music and audio
        'mp3', 'm4a', 'aac', 'wav', 'flac', 'ogg', 'wma',
    ], true);
}
