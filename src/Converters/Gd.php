<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;
use WebPConvert\Convert\BaseConverter;

class Gd extends BaseConverter
{
    public static $extraOptions = [];

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in BaseConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {
        if (!extension_loaded('gd')) {
            throw new ConverterNotOperationalException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new ConverterNotOperationalException(
                'Required imagewebp() function is not available. It seems Gd has been compiled without webp support.'
            );
        }

        switch ($this->getSourceExtension()) {
            case 'png':
                if (!function_exists('imagecreatefrompng')) {
                    throw new ConverterNotOperationalException(
                        'Required imagecreatefrompng() function is not available.'
                    );
                }
                $image = @imagecreatefrompng($this->source);
                if (!$image) {
                    throw new ConverterFailedException(
                        'imagecreatefrompng() failed'
                    );
                }
                break;
            default:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new ConverterNotOperationalException(
                        'Required imagecreatefromjpeg() function is not available.'
                    );
                }
                $image = @imagecreatefromjpeg($this->source);
                if (!$image) {
                    throw new ConverterFailedException('imagecreatefromjpeg() failed');
                }
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false

        $success = @imagewebp($image, $this->destination, $this->options['_calculated_quality']);

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
        if (@filesize($this->destination) % 2 == 1) {
            @file_put_contents($this->destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}
