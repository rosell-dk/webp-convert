<?php

namespace WebPConvert\Converters;

class Gd
{
    protected static function isValidExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileExtension = strtolower($fileExtension);

        switch ($fileExtension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($filePath);
            case 'png':
                if (defined('WEBPCONVERT_GD_PNG') && WEBPCONVERT_GD_PNG) {
                    return imagecreatefrompng($filePath);
                } else {
                    throw new \Exception('PNG file conversion failed. Try forcing it with: define("WEBPCONVERT_GD_PNG", true);');
                }
                break;
            default:
                throw new \Exception('Unsupported file extension: ' . $fileExtension);
        }
        return true;
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            if (!extension_loaded('gd')) {
                throw new \Exception('Required GD extension is not available.');
            }

            if (!function_exists('imagewebp')) {
                throw new \Exception('Required imagewebp() function is not available.');
            }
            
            $image = self::isValidExtension($source);

            // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false
            if (!$image) {
                throw new \Exception('Either imagecreatefromjpeg or imagecreatefrompng failed');
            }
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
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
