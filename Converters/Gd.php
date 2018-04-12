<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

class Gd
{
    // TODO: Move to WebPConvert or helper classes file (redundant, see Imagick.php)
    private static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public static function convert($source, $destination, $quality, $stripMetadata, $options = array())
    {
        $defaultOptions = array(
            'convert_pngs' => false
        );

        // For backwards compatibility
        if (defined("WEBPCONVERT_GD_PNG")) {
            if (!isset($options['convert_pngs'])) {
                $options['convert_pngs'] = WEBPCONVERT_GD_PNG;
            }
        }

        $options = array_merge($defaultOptions, $options);

        if (!extension_loaded('gd')) {
            throw new ConverterNotOperationalException('Required GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new ConverterNotOperationalException('Required imagewebp() function is not available.');
        }

        switch (self::getExtension($source)) {
            case 'png':
                if ($options['convert_pngs']) {
                    if (!function_exists('imagecreatefrompng')) {
                        throw new ConverterNotOperationalException('Required imagecreatefrompng() function is not available.');
                    }
                    $image = imagecreatefrompng($source);
                    if (!$image) {
                        throw new \Exception('imagecreatefrompng("' . $source . '") failed');
                    }
                } else {
                    throw new ConversionDeclinedException('PNG file skipped. GD is configured not to convert PNGs');
                }
                break;
            default:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new ConverterNotOperationalException('Required imagecreatefromjpeg() function is not available.');
                }
                $image = imagecreatefromjpeg($source);
                if (!$image) {
                    throw new \Exception('imagecreatefromjpeg("' . $source . '") failed');
                }
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false

        $success = imagewebp($image, $destination, $quality);

        if (!$success) {
            throw new ConverterFailedException('Call to imagewebp() failed. Probably failed writing file');
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
