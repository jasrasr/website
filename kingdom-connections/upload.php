<?php
declare(strict_types=1);

// Re-encode to strip metadata and store only a small display image.
function listingImages(array $files): array
{
    $images = [];
    foreach (['picture','logo'] as $field) {
        $file = $files[$field.'File'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) continue;
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 3000000) throw new InvalidArgumentException('Choose an image under 3 MB.');
        if (!extension_loaded('gd')) throw new InvalidArgumentException('Image uploads require PHP GD on this host.');
        $info = getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP], true) || $info[0]*$info[1] > 12000000) throw new InvalidArgumentException('Choose a JPEG, PNG or WebP up to 12 megapixels.');
        $source = imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$source) throw new InvalidArgumentException('Unreadable image.');
        $scale = min(1, 480/max($info[0],$info[1]));
        $out = imagecreatetruecolor(max(1,(int)($info[0]*$scale)),max(1,(int)($info[1]*$scale)));
        imagefill($out,0,0,imagecolorallocate($out,255,255,255));
        imagecopyresampled($out,$source,0,0,0,0,imagesx($out),imagesy($out),imagesx($source),imagesy($source));
        ob_start();
        imagejpeg($out,null,82);
        $images[$field] = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    }
    return $images;
}
