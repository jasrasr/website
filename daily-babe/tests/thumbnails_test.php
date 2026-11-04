<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/thumbnails.php';

function assert_thumbnail_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assert_thumbnail_test(GALLERY_THUMBNAIL_MAX_DIMENSION === 720, 'Gallery thumbnails must fit within 720 pixels.');
assert_thumbnail_test(GALLERY_THUMBNAIL_WEBP_QUALITY >= 1 && GALLERY_THUMBNAIL_WEBP_QUALITY <= 100, 'WebP quality must be valid.');
assert_thumbnail_test(create_gallery_thumbnail(__DIR__ . '/missing-image.jpg', sys_get_temp_dir() . '/missing-thumbnail.webp', 'image/jpeg') === false, 'Missing images must fall back safely.');

echo gallery_thumbnail_supported()
    ? "Thumbnail helper checks passed with GD/WebP enabled.\n"
    : "Thumbnail helper checks passed; image generation skipped because local GD/WebP is unavailable.\n";
