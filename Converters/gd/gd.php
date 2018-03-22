<?php

function webpconvert_gd($source, $destination, $quality, $strip_metadata)
{
    if (!extension_loaded('gd')) {
        return 'This implementation requires the GD extension, which you do not have';
    }

    if (!function_exists('imagewebp')) {
        return 'imagewebp() is not available';
    }

    $parts = explode('.', $source);
    $ext = array_pop($parts);
    $image = '';

    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'png':
            if (defined("WEBPCONVERT_GD_PNG") && WEBPCONVERT_GD_PNG) {
                $image = imagecreatefrompng($source);
            } else {
                return 'This converter has poor handling of PNG images and therefore refuses to convert the image. You can however force it to convert PNGs as well like this: define("WEBPCONVERT_GD_PNG", true);';
            }
            break;
        default:
            return 'Unsupported file extension';
    }

    if (!$image) {
        // Either imagecreatefromjpeg or imagecreatefrompng returned false
        return 'Either imagecreatefromjpeg or imagecreatefrompng failed';
    }

    $success = imagewebp($image, $destination, $quality);


    /*
     * This hack solves an `imagewebp` bug
     * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
     *
     */

    if (filesize($destination) % 2 == 1) {
        file_put_contents($destination, "\0", FILE_APPEND);
    }

    // Hm... sometimes I get completely transparent images, even with the hack above. Help, anybody?

    imagedestroy($image);
    if ($success) {
        return true;
    } else {
        return 'imagewebp() call failed';
    }
}
