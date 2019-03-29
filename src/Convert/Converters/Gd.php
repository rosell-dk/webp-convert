<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class Gd extends AbstractConverter
{
    public static $extraOptions = [];

    /**
     * Find out if all functions exists.
     *
     * @return boolean
     */
    private static function functionsExist($functionNamesArr)
    {
        foreach ($functionNamesArr as $functionName) {
            if (!function_exists($functionName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Try to convert image pallette to true color.
     *
     * Try to convert image pallette to true color. If imageistruecolor() exists, that is used (available from
     * PHP >= 5.5.0). Otherwise using workaround found on the net.
     *
     * @param  \GImage  &$image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    public static function makeTrueColor(&$image)
    {
        if (function_exists('imagepalettetotruecolor')) {
            return imagepalettetotruecolor($image);
        } else {
            // Got the workaround here: https://secure.php.net/manual/en/function.imagepalettetotruecolor.php
            if ((function_exists('imageistruecolor') && !imageistruecolor($image))
                || !function_exists('imageistruecolor')
            ) {
                if (self::functionsExist(['imagecreatetruecolor', 'imagealphablending', 'imagecolorallocatealpha',
                        'imagefilledrectangle', 'imagecopy', 'imagedestroy', 'imagesx', 'imagesy'])) {
                    $dst = imagecreatetruecolor(imagesx($image), imagesy($image));

                    //prevent blending with default black
                    imagealphablending($dst, false);

                     //change the RGB values if you need, but leave alpha at 127
                    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);

                     //simpler than flood fill
                    imagefilledrectangle($dst, 0, 0, imagesx($image), imagesy($image), $transparent);
                    imagealphablending($dst, true);     //restore default blending

                    imagecopy($dst, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                    imagedestroy($image);

                    $image = $dst;
                    return true;
                }
            } else {
                return false;
            }
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {
        if (!extension_loaded('gd')) {
            throw new SystemRequirementsNotMetException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new SystemRequirementsNotMetException(
                'Gd has been compiled without webp support.'
            );
        }

        $this->logLn('GD Version: ' . gd_info()["GD Version"]);

        // Btw: Check out processWebp here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Gd/Encoder.php

        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                $image = imagecreatefrompng($this->source);
                if (!$image) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefrompng() failed)'
                    );
                }
                break;

            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without Jpeg support and can therefore not convert this jpeg image.'
                    );
                }
                $image = imagecreatefromjpeg($this->source);
                if (!$image) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefromjpeg() failed)'
                    );
                }
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false

        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($image)) {
                $this->logLn('image is true color');
            } else {
                $this->logLn('image is not true color');
                $mustMakeTrueColor = true;
            }
        } else {
            $this->logLn('It can not be determined if image is true color');
            $mustMakeTrueColor = true;
        }

        if ($mustMakeTrueColor) {
            $this->logLn('converting color palette to true color');
            $success = $this->makeTrueColor($image);
            if (!$success) {
                $this->logLn(
                    'Warning: FAILED converting color palette to true color. ' .
                    'Continuing, but this does not look good.'
                );
            }
        }

        if ($mimeType == 'png') {
            if (function_exists('imagealphablending')) {
                if (!imagealphablending($image, true)) {
                    $this->logLn('Warning: imagealphablending() failed');
                }
            } else {
                $this->logLn(
                    'Warning: imagealphablending() is not available on your system.' .
                    ' Converting PNGs with transparency might fail on some systems'
                );
            }

            if (function_exists('imagesavealpha')) {
                if (!imagesavealpha($image, true)) {
                    $this->logLn('Warning: imagesavealpha() failed');
                }
            } else {
                $this->logLn(
                    'Warning: imagesavealpha() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
        }

        $success = imagewebp($image, $this->destination, $this->options['_calculated_quality']);

        if (!$success) {
            throw new ConversionFailedException(
                'Gd failed when trying to save the image as webp (call to imagewebp() failed). ' .
                'It probably failed writing file. Check file permissions!'
            );
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */
        if (filesize($this->destination) % 2 == 1) {
            file_put_contents($this->destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}
