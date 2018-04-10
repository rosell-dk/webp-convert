<?php

namespace WebPConvert\Converters;

class Gd
{
    // TODO: Move to WebPConvert or helper classes file (redundant, see Imagick.php)
    private static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('Required GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new \Exception('Required imagewebp() function is not available.');
        }

        switch (self::getExtension($source)) {
            case 'png':
                if (defined('WEBPCONVERT_GD_PNG') && WEBPCONVERT_GD_PNG) {
                    $image = imagecreatefrompng($source);
                } else {
                    throw new \Exception('PNG file skipped. GD is configured not to convert PNGs');
                }
                break;
            default:
                $image = imagecreatefromjpeg($source);
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false
        if (!$image) {
            throw new \Exception('Either imagecreatefromjpeg or imagecreatefrompng failed');
        }

        $success = imagewebp($image, $destination, $quality);

        if (!$success) {
            throw new \Exception('Failed writing file');
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */

        if (filesize($destination) % 2 == 1) {
            file_put_contents($destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}
