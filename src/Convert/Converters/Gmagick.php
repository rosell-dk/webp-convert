<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp using Gmagick extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Gmagick extends AbstractConverter
{
    protected $supportsLossless = false;

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    /**
     * Check (general) operationality of Gmagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('Gmagick')) {
            throw new SystemRequirementsNotMetException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new SystemRequirementsNotMetException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        $im = new \Gmagick($this->source);

        if (!in_array('WEBP', $im->queryformats())) {
            throw new SystemRequirementsNotMetException('Gmagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Gmagick does not support image type
     */
    public function checkConvertability()
    {
        $im = new \Gmagick();
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

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doActualConvert()
    {

        $options = $this->options;

        try {
            $im = new \Gmagick($this->source);
        } catch (\Exception $e) {
            throw new ConversionFailedException(
                'Failed creating Gmagick object of file',
                'Failed creating Gmagick object of file: "' . $this->source . '" - Gmagick threw an exception.',
                $e
            );
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
        // TODO: However, it might be possible after all - see #91
        $im->setcompressionquality($this->getCalculatedQuality());

        try {
            // We call getImageBlob().
            // That method is undocumented, but it is there!
            // - just like it is in imagick, as pointed out here:
            //   https://www.php.net/manual/ru/gmagick.readimageblob.php

            /** @scrutinizer ignore-call */
            $imageBlob = $im->getImageBlob();
        } catch (\ImagickException $e) {
            throw new ConversionFailedException(
                'Gmagick failed converting - getImageBlob() threw an exception)',
                0,
                $e
            );
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
