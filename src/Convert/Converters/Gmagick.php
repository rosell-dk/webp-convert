<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Gmagick extends AbstractConverter
{
    public static $extraOptions = [];

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {
        if (!extension_loaded('Gmagick')) {
            throw new SystemRequirementsNotMetException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new SystemRequirementsNotMetException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        $options = $this->options;

        // This might throw an exception.
        // We let it...
        $im = new \Gmagick($this->source);


        // Throws an exception if Gmagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryformats())) {
            throw new SystemRequirementsNotMetException('Gmagick was compiled without WebP support.');
        }

        /*
        Seems there are currently no way to set webp options
        As noted in the following link, it should probably be done with a $im->addDefinition() method
        - but that isn't exposed (yet)
        (TODO: see if anyone has answered...)
        https://stackoverflow.com/questions/47294962/how-to-write-lossless-webp-files-with-perlmagick
        */
        // The following two does not have any effect... How to set WebP options?
        //$im->setimageoption('webp', 'webp:lossless', $options['lossless'] ? 'true' : 'false');
        //$im->setimageoption('WEBP', 'method', strval($options['method']));

        // It seems there is no COMPRESSION_WEBP...
        // http://php.net/manual/en/imagick.setimagecompression.php
        //$im->setImageCompression(Imagick::COMPRESSION_JPEG);
        //$im->setImageCompression(Imagick::COMPRESSION_UNDEFINED);



        $im->setimageformat('WEBP');

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        // Ps: Imagick automatically uses same quality as source, when no quality is set
        // This feature is however not present in Gmagick
        $im->setcompressionquality($this->getCalculatedQuality());

        try {
            $imageBlob = $im->getImageBlob();
        } catch (\ImagickException $e) {
            throw new ConversionFailedException('Gmagick failed converting - getImageBlob() threw an exception)', 0, $e);
        }


        //$success = $im->writeimagefile(fopen($destination, 'wb'));
        $success = @file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new ConversionFailedException('Failed writing file');
        } else {
            //$logger->logLn('sooms we made it!');
        }
    }
}
