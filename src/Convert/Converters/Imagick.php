<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp using Imagick extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Imagick extends AbstractConverter
{
    protected $supportsLossless = false;

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    /**
     * Check operationality of Imagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('imagick')) {
            throw new SystemRequirementsNotMetException('Required iMagick extension is not available.');
        }

        if (!class_exists('\\Imagick')) {
            throw new SystemRequirementsNotMetException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        $im = new \Imagick();

        if (!in_array('WEBP', $im->queryFormats())) {
            throw new SystemRequirementsNotMetException('iMagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Imagick does not support image type
     */
    public function checkConvertability()
    {
        $im = new \Imagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    /**
     *
     * It may also throw an ImagickException if imagick throws an exception
     * @throws CreateDestinationFileException if imageblob could not be saved to file
     */
    protected function doActualConvert()
    {
        $options = $this->options;

        // This might throw - we let it!
        $im = new \Imagick($this->source);

        //$im = new \Imagick();
        //$im->readImage($this->source);

        $im->setImageFormat('WEBP');

        /*
         * More about iMagick's WebP options:
         * http://www.imagemagick.org/script/webp.php
         * https://developers.google.com/speed/webp/docs/cwebp
         * https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        // TODO: We could easily support all webp options with a loop

        /*
        After using getImageBlob() to write image, the following setOption() calls
        makes settings makes imagick fail. So can't use those. But its a small price
        to get a converter that actually makes great quality conversions.

        $im->setOption('webp:method', strval($options['method']));
        $im->setOption('webp:low-memory', strval($options['low-memory']));
        $im->setOption('webp:lossless', strval($options['lossless']));
        */

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        if ($this->isQualityDetectionRequiredButFailing()) {
            // Luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-quality functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $this->logLn('Converting without setting quality in order to achieve auto quality');
        } else {
            $im->setImageCompressionQuality($this->getCalculatedQuality());
        }

        // https://stackoverflow.com/questions/29171248/php-imagick-jpeg-optimization
        // setImageFormat

        // TODO: Read up on
        // https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
        // https://github.com/nwtn/php-respimg

        // TODO:
        // Should we set alpha channel for PNG's like suggested here:
        // https://gauntface.com/blog/2014/09/02/webp-support-with-imagemagick-and-php ??
        // It seems that alpha channel works without... (at least I see completely transparerent pixels)

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)

        // We used to use writeImageFile() method. But we now use getImageBlob(). See issue #43
        //$success = $im->writeImageFile(fopen($destination, 'wb'));


        // This might throw - we let it!
        $imageBlob = $im->getImageBlob();

        $success = file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new CreateDestinationFileException('Failed writing file');
        }

        // Btw: check out processWebp() method here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Imagick/Encoder.php
    }
}
