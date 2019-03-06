<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Convert\BaseConverter;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Gmagick extends BaseConverter
{
    public static $extraOptions = [];

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in BaseConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {
        if (!extension_loaded('Gmagick')) {
            throw new ConverterNotOperationalException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new ConverterNotOperationalException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        $options = $this->options;

        // This might throw an exception.
        // We let it...
        $im = new \Gmagick($this->source);


        // Throws an exception if Gmagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryformats())) {
            throw new ConverterNotOperationalException('Gmagick was compiled without WebP support.');
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

        //$success = $im->writeimagefile(fopen($destination, 'wb'));
        $success = @file_put_contents($this->destination, $im->getImageBlob());

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        } else {
            //$logger->logLn('sooms we made it!');
        }
    }
}
