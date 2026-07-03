<?php
/**
 * File: app-icon.php
 * Project: TV Binge Board
 * Description: Dynamic PNG app icon generator matching the JasonLamb.me JL favicon/logo style for PWA, Apple touch icon, and browser favicon use.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

$allowedSizes = [16, 32, 48, 64, 180, 192, 512];
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
if (!in_array($size, $allowedSizes, true)) {
    $size = 192;
}

$maxAge = 604800;
header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
header('X-Content-Type-Options: nosniff');

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/svg+xml; charset=utf-8');
    $svgSize = (string)$size;
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svgSize . '" height="' . $svgSize . '" viewBox="0 0 512 512">'
        . '<rect width="512" height="512" rx="72" fill="#fff"/>'
        . '<path d="M163 68 L357 107 M262 92 L180 337 Q155 425 55 373" fill="none" stroke="#0715ff" stroke-width="64" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<path d="M184 151 L284 448 L456 362" fill="none" stroke="#ff140d" stroke-width="64" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
    exit;
}

$image = imagecreatetruecolor($size, $size);
imagealphablending($image, true);
imagesavealpha($image, true);
if (function_exists('imageantialias')) {
    imageantialias($image, true);
}

$white = imagecolorallocate($image, 255, 255, 255);
$blue = imagecolorallocate($image, 7, 21, 255);
$red = imagecolorallocate($image, 255, 20, 13);
$border = imagecolorallocatealpha($image, 0, 0, 0, 110);

imagefilledrectangle($image, 0, 0, $size, $size, $white);

function tvbb_icon_line($image, int $color, float $x1, float $y1, float $x2, float $y2, float $thickness, int $size): void
{
    $x1 *= $size;
    $y1 *= $size;
    $x2 *= $size;
    $y2 *= $size;
    $thickness = max(1, $thickness * $size);
    imagesetthickness($image, (int)round($thickness));
    imageline($image, (int)round($x1), (int)round($y1), (int)round($x2), (int)round($y2), $color);
    $radius = $thickness / 2;
    imagefilledellipse($image, (int)round($x1), (int)round($y1), (int)round($radius * 2), (int)round($radius * 2), $color);
    imagefilledellipse($image, (int)round($x2), (int)round($y2), (int)round($radius * 2), (int)round($radius * 2), $color);
}

$stroke = 0.132;
// Blue J-like stroke from the JasonLamb.me mark.
tvbb_icon_line($image, $blue, 0.33, 0.085, 0.70, 0.165, $stroke, $size);
tvbb_icon_line($image, $blue, 0.50, 0.145, 0.39, 0.54, $stroke, $size);
tvbb_icon_line($image, $blue, 0.39, 0.54, 0.30, 0.73, $stroke, $size);
tvbb_icon_line($image, $blue, 0.30, 0.73, 0.095, 0.62, $stroke, $size);

// Red L stroke from the JasonLamb.me mark.
tvbb_icon_line($image, $red, 0.36, 0.275, 0.56, 0.875, $stroke, $size);
tvbb_icon_line($image, $red, 0.56, 0.875, 0.885, 0.71, $stroke, $size);

if ($size >= 64) {
    imagerectangle($image, 0, 0, $size - 1, $size - 1, $border);
}

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
