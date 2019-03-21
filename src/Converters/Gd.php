<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

use WebPConvert\Converters\ConverterHelper;

class Gd
{
    public static $extraOptions = [
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('gd', $source, $destination, $options, true);
    }

    /**
     *
     *  @return Returns TRUE if the convertion was complete, or if the source image already is a true color image,
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
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('gd')) {
            throw new ConverterNotOperationalException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new ConverterNotOperationalException(
                'Required imagewebp() function is not available. It seems Gd has been compiled without webp support.'
            );
        }

        switch (ConverterHelper::getExtension($source)) {
            case 'png':
                if (!$options['skip-pngs']) {
                    if (!function_exists('imagecreatefrompng')) {
                        throw new ConverterNotOperationalException(
                            'Required imagecreatefrompng() function is not available.'
                        );
                    }
                    $image = @imagecreatefrompng($source);
                    if (!$image) {
                        throw new ConverterFailedException(
                            'imagecreatefrompng("' . $source . '") failed'
                        );
                    }
                } else {
                    throw new ConversionDeclinedException(
                        'PNG file skipped. GD is configured not to convert PNGs'
                    );
                }
                break;
            default:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new ConverterNotOperationalException(
                        'Required imagecreatefromjpeg() function is not available.'
                    );
                }
                $image = @imagecreatefromjpeg($source);
                if (!$image) {
                    throw new ConverterFailedException('imagecreatefromjpeg("' . $source . '") failed');
                }
        }

        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($image)) {
                $logger->logLn('image is true color');
            } else {
                $logger->logLn('image is not true color');
                $mustMakeTrueColor = true;
            }
        } else {
            $logger->logLn('It can not be determined if image is true color');
            $mustMakeTrueColor = true;
        }
        if ($mustMakeTrueColor) {
            $logger->logLn('converting color palette to true color');
            $success = self::makeTrueColor($image);
            if (!$success) {
                $logger->logLn(
                    'Warning: FAILED converting color palette to true color. Continuing, but this does not look good.'
                );
            }
        }
        if (ConverterHelper::getExtension($source) == 'png') {
            if (function_exists('imagealphablending')) {
                if (!imagealphablending($image, true)) {
                    $logger->logLn('Warning: imagealphablending() failed');
                }
            } else {
                $logger->logLn(
                    'Warning: imagealphablending() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
            if (function_exists('imagesavealpha')) {
                if (!imagesavealpha($image, true)) {
                    $logger->logLn('Warning: imagesavealpha() failed');
                }
            } else {
                $logger->logLn(
                    'Warning: imagesavealpha() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
        }

        $success = @imagewebp($image, $destination, $options['_calculated_quality']);

        if (!$success) {
            throw new ConverterFailedException(
                'Call to imagewebp() failed. Probably failed writing file. Check file permissions!'
            );
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */
        if (@filesize($destination) % 2 == 1) {
            @file_put_contents($destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}
