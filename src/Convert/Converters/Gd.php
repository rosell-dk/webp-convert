<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class Gd extends AbstractConverter
{
    private $errorMessageWhileCreating = '';
    private $errorNumberWhileCreating;

    // TODO: Can we make this private, but still test?
    private $image = false;

    public static $extraOptions = [];

    /**
     * Check (general) operationality of Gd converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
    {
        if (!extension_loaded('gd')) {
            throw new SystemRequirementsNotMetException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new SystemRequirementsNotMetException(
                'Gd has been compiled without webp support.'
            );
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Gd has been compiled without support for image type
     */
    protected function checkConvertability()
    {
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;

            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without Jpeg support and can therefore not convert this jpeg image.'
                    );
                }
        }
    }

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
     * @param  resource  &$image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    private static function makeTrueColor(&$image)
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

    /**
     * Create Gd image resource from source
     *
     * Sets $this->image to new image, or false if unsuccesful
     *
     * @throws  InvalidInputException  if mime type is unsupported or could not be detected
     * @return  void
     */
    protected function createImageResource()
    {
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                $this->image = imagecreatefrompng($this->source);
                if ($this->image === false) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefrompng() failed)'
                    );
                }
                break;

            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($this->source);
                if ($this->image === false) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefromjpeg() failed)'
                    );
                }
                break;

            case false:
                $this->image = false;
                throw new InvalidInputException(
                    'Mime type could not be determined'
                );
                break;

            default:
                $this->image = false;
                throw new InvalidInputException(
                    'Unsupported mime type:' . $mimeType
                );
        }
    }

    /**
     * Try to make image resource true color if it is not already
     *
     */
    protected function tryToMakeTrueColorIfNot()
    {
        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($this->image)) {
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
            $success = $this->makeTrueColor($this->image);
            if (!$success) {
                $this->logLn(
                    'Warning: FAILED converting color palette to true color. ' .
                    'Continuing, but this does not look good.'
                );
            }
        }
    }

    protected function trySettingAlphaBlending()
    {
        if (function_exists('imagealphablending')) {
            if (!imagealphablending($this->image, true)) {
                $this->logLn('Warning: imagealphablending() failed');
            }
        } else {
            $this->logLn(
                'Warning: imagealphablending() is not available on your system.' .
                ' Converting PNGs with transparency might fail on some systems'
            );
        }

        if (function_exists('imagesavealpha')) {
            if (!imagesavealpha($this->image, true)) {
                $this->logLn('Warning: imagesavealpha() failed');
            }
        } else {
            $this->logLn(
                'Warning: imagesavealpha() is not available on your system. ' .
                'Converting PNGs with transparency might fail on some systems'
            );
        }

    }

    protected function errorHandlerWhileCreatingWebP($errno, $errstr, $errfile, $errline)
    {
        $this->errorNumberWhileCreating = $errno;
        $this->errorMessageWhileCreating = $errstr . ' in ' . $errfile . ', line ' . $errline .
            ', PHP ' . PHP_VERSION . ' (' . PHP_OS . ')';
    }

    protected function destroyAndRemove()
    {
        imagedestroy($this->image);
        if (file_exists($this->destination)) {
            unlink($this->destination);
        }
    }

    protected function tryConverting()
    {
        // Danger zone!
        //    Using output buffering to generate image.
        //    In this zone, Do NOT do anything that might produce unwanted output
        //    Do NOT call $this->logLn
        // --------------------------------- (start of danger zone)

        $addedZeroPadding = false;
        set_error_handler(array($this, "errorHandlerWhileCreatingWebP"));

        ob_start();
        $success = imagewebp($this->image);
        if (!$success) {
            $this->destroyAndRemove();
            ob_end_clean();
            restore_error_handler();
            throw new ConversionFailedException(
                'Failed creating image. Call to imagewebp() failed.',
                $this->errorMessageWhileCreating
            );
        }


        // The following hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (ob_get_length() % 2 == 1) {
            echo "\0";
            $addedZeroPadding = true;
        }
        $output = ob_get_clean();
        restore_error_handler();

        // --------------------------------- (end of danger zone).


        if ($this->errorMessageWhileCreating != '') {

            switch ($this->errorNumberWhileCreating) {
                case E_WARNING:
                    $this->logLn('An warning was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                case E_NOTICE:
                    $this->logLn('An notice was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                default:
                    $this->destroyAndRemove();
                    throw new ConversionFailedException(
                        'An error was produced during conversion',
                        $this->errorMessageWhileCreating
                    );
                    break;
            }
        }

        if ($addedZeroPadding) {
            $this->logLn(
                'Fixing corrupt webp by adding a zero byte ' .
                '(older versions of Gd had a bug, but this hack fixes it)'
            );
        }

        $success = file_put_contents($this->destination, $output);

        if (!$success) {
            $this->destroyAndRemove();
            throw new ConversionFailedException(
                'Gd failed when trying to save the image. Check file permissions!'
            );
        }

        /*
        Previous code was much simpler, but on a system, the hack was not activated (a file with uneven number of bytes
        was created). This is puzzeling. And the old code did not provide any insights.
        Also, perhaps having two subsequent writes to the same file could perhaps cause a problem.
        In the new code, there is only one write.
        However, a bad thing about the new code is that the entire webp file is read into memory. This might cause
        memory overflow with big files.
        Perhaps we should check the filesize of the original and only use the new code when it is smaller than
        memory limit set in PHP by a certain factor.
        Or perhaps only use the new code on older versions of Gd
        https://wordpress.org/support/topic/images-not-seen-on-chrome/#post-11390284

        Here is the old code:

        $success = imagewebp($this->image, $this->destination, $this->getCalculatedQuality());

        if (!$success) {
            throw new ConversionFailedException(
                'Gd failed when trying to save the image as webp (call to imagewebp() failed). ' .
                'It probably failed writing file. Check file permissions!'
            );
        }


        // This hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (filesize($this->destination) % 2 == 1) {
            file_put_contents($this->destination, "\0", FILE_APPEND);
        }
        */
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {

        $this->logLn('GD Version: ' . gd_info()["GD Version"]);

        // Btw: Check out processWebp here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Gd/Encoder.php

        // Create image resource (this sets $this->image)
        $this->createImageResource();

        // Try to convert color palette if it is not true color (works on $this->image)
        $this->tryToMakeTrueColorIfNot();


        if ($this->getMimeTypeOfSource() == 'png') {

            // Try to set alpha blending (works on $this->image)
            $this->trySettingAlphaBlending();
        }

        // Try to convert it to webp
        $this->tryConverting();

        // End of story
        imagedestroy($this->image);
    }
}
