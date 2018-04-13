<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

use WebPConvert\Exceptions\TargetNotFoundException;

class Imagick
{
    public static function convert($source, $destination, $options = array())
    {
        ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $defaultOptions = array(
            'quality' => 80,
            'method' => 6,
            'low-memory' => true
        );

        // For backwards compatibility
        if (defined("WEBPCONVERT_IMAGICK_METHOD")) {
            if (!isset($options['method'])) {
                $options['method'] = WEBPCONVERT_IMAGICK_METHOD;
            }
        }
        if (defined("WEBPCONVERT_IMAGICK_LOW_MEMORY")) {
            if (!isset($options['low-memory'])) {
                $options['low-memory'] = WEBPCONVERT_IMAGICK_LOW_MEMORY;
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
        switch (ConverterHelper::getExtension($source)) {
            case 'png':
                $im->setOption('webp:lossless', 'true');
                break;
            default:
                break;
        }

        /*
         * More about iMagick's WebP options:
         * http://www.imagemagick.org/script/webp.php
         * https://developers.google.com/speed/webp/docs/cwebp
         * https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        // TODO: We could easily support all webp options with a loop
        $im->setOption('webp:method', strval($options['method']));
        $im->setOption('webp:low-memory', strval($options['low-memory']));


        $im->setImageCompressionQuality($options['quality']);

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)
        $success = $im->writeImageFile(fopen($destination, 'wb'));

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        }
    }
}
