<?php

declare(strict_types=1);

const GALLERY_THUMBNAIL_MAX_DIMENSION = 720;
const GALLERY_THUMBNAIL_WEBP_QUALITY = 82;

function gallery_thumbnail_supported(): bool
{
    return function_exists('imagecreatetruecolor')
        && function_exists('imagewebp')
        && function_exists('imagecreatefromjpeg')
        && function_exists('imagecreatefrompng');
}

function gallery_thumbnail_source(string $sourcePath, string $mimeType)
{
    if ($mimeType === 'image/jpeg') {
        return @imagecreatefromjpeg($sourcePath);
    }
    if ($mimeType === 'image/png') {
        return @imagecreatefrompng($sourcePath);
    }
    if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($sourcePath);
    }
    return false;
}

function orient_gallery_jpeg_thumbnail($image, string $sourcePath)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $metadata = @exif_read_data($sourcePath);
    $orientation = is_array($metadata) ? (int) ($metadata['Orientation'] ?? 1) : 1;
    if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
        imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
    }
    $angle = match ($orientation) {
        3, 4 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => 0,
    };
    if ($angle !== 0) {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }
    }
    return $image;
}

function create_gallery_thumbnail(string $sourcePath, string $destinationPath, string $mimeType): bool
{
    if (!gallery_thumbnail_supported() || !is_file($sourcePath)) {
        return false;
    }

    $source = gallery_thumbnail_source($sourcePath, $mimeType);
    if ($source === false) {
        return false;
    }
    if ($mimeType === 'image/jpeg') {
        $source = orient_gallery_jpeg_thumbnail($source, $sourcePath);
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1) {
        imagedestroy($source);
        return false;
    }

    $scale = min(1, GALLERY_THUMBNAIL_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));
    $thumbnail = imagecreatetruecolor($width, $height);
    if ($thumbnail === false) {
        imagedestroy($source);
        return false;
    }

    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
    imagefill($thumbnail, 0, 0, $transparent);
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

    $directory = dirname($destinationPath);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        imagedestroy($thumbnail);
        imagedestroy($source);
        return false;
    }

    $temporaryPath = $destinationPath . '.tmp-' . bin2hex(random_bytes(4));
    $written = imagewebp($thumbnail, $temporaryPath, GALLERY_THUMBNAIL_WEBP_QUALITY);
    imagedestroy($thumbnail);
    imagedestroy($source);
    if (!$written) {
        @unlink($temporaryPath);
        return false;
    }
    @chmod($temporaryPath, 0640);
    if (!rename($temporaryPath, $destinationPath)) {
        @unlink($temporaryPath);
        return false;
    }
    return true;
}
