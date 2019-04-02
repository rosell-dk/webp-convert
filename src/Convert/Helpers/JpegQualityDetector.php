<?php

namespace WebPConvert\Convert\Helpers;

abstract class JpegQualityDetector
{

    /**
     * Try to detect quality of jpeg.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    public static function detectQualityOfJpg($filename)
    {
        /*
        if (!file_exists($filename)) {
            return null;
        }
        */
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            $img = new \Imagick($filename);

            // The required function is available as from PECL imagick v2.2.2
            if (method_exists($img, 'getImageCompressionQuality')) {
                return $img->getImageCompressionQuality();
            }
        }

        // Gmagick extension doesn't seem to support this (yet):
        // https://bugs.php.net/bug.php?id=63939

        if (function_exists('shell_exec')) {
            // Try Imagick
            // Note that shell_exect may throw or warn
            $quality = shell_exec("identify -format '%Q' " . escapeshellarg($filename));
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . escapeshellarg($filename));
            if ($quality) {
                return intval($quality);
            }
        }

        return null;
    }
}
