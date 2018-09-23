<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Gmagick
{
    public static $extraOptions = [];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('gmagick', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('Gmagick')) {
            throw new ConverterNotOperationalException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new ConverterNotOperationalException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        // This might throw an exception.
        // We let it...
        $im = new \Gmagick($source);


        // Throws an exception if Gmagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryformats())) {
            throw new ConverterNotOperationalException('Gmagick was compiled without WebP support.');
        }

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
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

        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // but luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-height functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
        } else {
            $im->setImageCompressionQuality($options['_calculated_quality']);
        }


        //$success = $im->writeimagefile(fopen($destination, 'wb'));
        $success = @file_put_contents($destination, $im->getImageBlob());

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        } else {
            //$logger->logLn('sooms we made it!');

        }
    }
}
