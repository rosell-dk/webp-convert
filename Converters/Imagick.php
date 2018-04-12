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

    public static function convert($source, $destination, $quality, $stripMetadata, $options = array())
    {
        $defaultOptions = array(
            'webp:method' => 6,
            'webp:low-memory' => true
        );

        // For backwards compatibility
        if (defined("WEBPCONVERT_IMAGICK_METHOD")) {
            if (!isset($options['webp:method'])) {
                $options['webp:method'] = WEBPCONVERT_IMAGICK_METHOD;
            }
        }
        if (defined("WEBPCONVERT_IMAGICK_LOW_MEMORY")) {
            if (!isset($options['webp:low-memory'])) {
                $options['webp:low-memory'] = WEBPCONVERT_IMAGICK_LOW_MEMORY;
            }
        }

        $options = array_merge($defaultOptions, $options);

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

        // TODO: We could easily support all webp options with a loop
        $im->setOption('webp:method', strval($options['webp:method']));
        $im->setOption('webp:low-memory', strval($options['webp:low-memory']));


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
