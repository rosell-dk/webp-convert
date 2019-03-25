<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Imagick extends AbstractConverter
{
    public static $extraOptions = [];


    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {

        if (!extension_loaded('imagick')) {
            throw new SystemRequirementsNotMetException('Required iMagick extension is not available.');
        }

        if (!class_exists('\\Imagick')) {
            throw new SystemRequirementsNotMetException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        $options = $this->options;

        // This might throw an exception.
        // Ie "ImagickException: no decode delegate for this image format `JPEG'"
        // We let it...
        $im = new \Imagick($this->source);
        //$im = new \Imagick();
        //$im->readImage($this->source);

        // Throws an exception if iMagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryFormats())) {
            throw new SystemRequirementsNotMetException('iMagick was compiled without WebP support.');
        }

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

        if ($this->isQualitySetToAutoAndDidQualityDetectionFail()) {
            // Luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-quality functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $logger->logLn('Converting without setting quality, to achieve auto quality');
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

        try {
            $imageBlob = $im->getImageBlob();
        } catch (\ImagickException $e) {
            throw new ConversionFailedException(
                'Imagick failed converting - getImageBlob() threw an exception)',
                0,
                $e
            );
        }

        $success = file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new CreateDestinationFileException('Failed writing file');
        }



        // Btw: check out processWebp() method here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Imagick/Encoder.php
    }
}
