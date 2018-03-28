<?php

namespace WebPConvert\Converters;

class Imagick
{
    // Throws an exception if iMagick does not support WebP conversion
    protected static function hasWebpSupport($object)
    {
        if (!in_array('WEBP', $object->queryFormats())) {
            throw new \Exception('iMagick was compiled without WebP support.');
        }
        $object->setImageFormat('WEBP');
    }

    // Throws an exception if the provided file's extension is unsupported
    protected static function isValidExtension($filePath, $object)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileExtension = strtolower($fileExtension);

        switch ($fileExtension) {
            case 'jpg':
            case 'jpeg':
                break;
            case 'png':
                $object->setOption('webp:lossless', 'true');
                break;
            default:
                throw new \Exception('Unsupported file extension: ' . $fileExtension);
        }
        return true;
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            if (!extension_loaded('imagick')) {
                throw new \Exception('Required iMagick extension is not available.');
            }

            if (!class_exists('Imagick')) {
                throw new \Exception('iMagick is installed but cannot handle source file.');
            }

            $im = new \Imagick($source);

            self::hasWebpSupport($im);
            self::isValidExtension($source, $im);
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
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

        if (defined('WEBPCONVERT_IMAGICK_LOW_MEMORY')) {
            $im->setOption('webp:low-memory', (
                WEBPCONVERT_IMAGICK_LOW_MEMORY
                ? 'true'
                : 'false'
            ));
        } else {
            $im->setOption('webp:low-memory', 'true');
        }

        $im->setImageCompressionQuality($quality);

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)
        $success = $im->writeImageFile(fopen($destination, 'wb'));

        if (!$success) {
            return false;
        }

        return true;
    }
}
