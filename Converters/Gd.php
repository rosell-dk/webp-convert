<?php

namespace WebPConvert\Converters;

class Gd
{
    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            if (!extension_loaded('gd')) {
                throw new \Exception('Required GD extension is not available.');
            }

            if (!function_exists('imagewebp')) {
                throw new \Exception('Required imagewebp() function is not available.');
            }
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
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
}
