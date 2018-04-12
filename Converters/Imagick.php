<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Imagick
{
    // TODO: Move to WebPConvert or helper classes file (redundant, see Gd.php)
    private static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        if (!extension_loaded('imagick')) {
            throw new ConverterNotOperationalException('Required iMagick extension is not available.');
        }

        if (!class_exists('Imagick')) {
            throw new ConverterNotOperationalException('iMagick is installed, but not correctly. The class Imagick is not available');
        }

        $im = new \Imagick($source);

        // Throws an exception if iMagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryFormats())) {
            throw new ConverterNotOperationalException('iMagick was compiled without WebP support.');
        }

        $im->setImageFormat('WEBP');

        // Apply losless compression for PNG images
        switch (self::getExtension($source)) {
            case 'png':
                $im->setOption('webp:lossless', 'true');
                break;
            default:
                break;
        }

        /*
         * More about iMagick's WebP options:
         * http://www.imagemagick.org/script/webp.php
         * https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        if (defined('WEBPCONVERT_IMAGICK_METHOD')) {
            $im->setOption('webp:method', WEBPCONVERT_IMAGICK_METHOD);
        } else {
            $im->setOption('webp:method', '6');
        }

        if (!defined('WEBPCONVERT_IMAGICK_LOW_MEMORY')) {
            $im->setOption('webp:low-memory', 'true');
        } else {
            $im->setOption('webp:low-memory', (
                WEBPCONVERT_IMAGICK_LOW_MEMORY
                ? 'true'
                : 'false'
            ));
        }

        $im->setImageCompressionQuality($quality);

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)
        $success = $im->writeImageFile(fopen($destination, 'wb'));

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        }
    }
}
