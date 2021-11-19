<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;

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
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'size-in-percentage',
        ];
    }

    /**
     * Check operationality of Imagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     * @return void
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
        if (!in_array('WEBP', $im->queryFormats('WEBP'))) {
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
                if (!in_array('PNG', $im->queryFormats('PNG'))) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats('JPEG'))) {
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
        /*
         * More about iMagick's WebP options:
         * - Inspect source code: https://github.com/ImageMagick/ImageMagick/blob/master/coders/webp.c#L559
         *      (search for "webp:")
         * - http://www.imagemagick.org/script/webp.php
         * - https://developers.google.com/speed/webp/docs/cwebp
         * - https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        $options = $this->options;

        // This might throw - we let it!
        $im = new \Imagick($this->source);
        //$im = new \Imagick();
        //$im->pingImage($this->source);
        //$im->readImage($this->source);

        $version = \Imagick::getVersion();
        $this->logLn('ImageMagic API version (full): ' . $version['versionString']);

        preg_match('#\d+\.\d+\.\d+[\d\.\-]+#', $version['versionString'], $matches);
        $versionNumber = (isset($matches[0]) ? $matches[0] : 'unknown');
        $this->logLn('ImageMagic API version (just the number): ' . $versionNumber);

        // Note: good enough for info, but not entirely reliable - see #304
        $extVersion = (defined('\Imagick::IMAGICK_EXTVER') ? \Imagick::IMAGICK_EXTVER : phpversion('imagick'));
        $this->logLn('Imagic extension version: ' . $extVersion);

        $im->setImageFormat('WEBP');

        if (!is_null($options['preset'])) {
            if ($options['preset'] != 'none') {
                $imageHint = $options['preset'];
                switch ($imageHint) {
                    case 'drawing':
                    case 'icon':
                    case 'text':
                        $imageHint = 'graph';
                        $this->logLn(
                            'The "preset" value was mapped to "graph" because imagick does not support "drawing",' .
                            ' "icon" and "text", but grouped these into one option: "graph".'
                        );
                }
                $im->setOption('webp:image-hint', $imageHint);
            }
        }

        $im->setOption('webp:method', $options['method']);
        $im->setOption('webp:lossless', $options['encoding'] == 'lossless' ? 'true' : 'false');
        $im->setOption('webp:low-memory', $options['low-memory'] ? 'true' : 'false');
        $im->setOption('webp:alpha-quality', $options['alpha-quality']);

        if ($options['near-lossless'] != 100) {
            if (version_compare($versionNumber, '7.0.10-54', '>=')) {
                $im->setOption('webp:near-lossless', $options['near-lossless']);
            } else {
                $this->logLn(
                    'Note: near-lossless is not supported in your version of ImageMagick. ' .
                        'ImageMagic >= 7.0.10-54 is required',
                    'italic'
                );
            }
        }

        if ($options['auto-filter'] === true) {
            $im->setOption('webp:auto-filter', 'true');
        }

        if ($options['sharp-yuv'] === true) {
            if (version_compare($versionNumber, '7.0.8-26', '>=')) {
                $im->setOption('webp:use-sharp-yuv', 'true');
            } else {
                $this->logLn(
                    'Note: "sharp-yuv" option is not supported in your version of ImageMagick. ' .
                      'ImageMagic >= 7.0.8-26 is required',
                    'italic'
                );
            }
        }

        if ($options['metadata'] == 'none') {
            // To strip metadata, we need to use the stripImage() method. However, that method does not only remove
            // metadata, but color profiles as well. We want to keep the color profiles, so we grab it now to be able
            // to restore it. (Thanks, Max Eremin: https://www.php.net/manual/en/imagick.stripimage.php#120380)

            // Grab color profile (to be able to restore them)
            $profiles = $im->getImageProfiles("icc", true);

            // Strip metadata (and color profiles)
            $im->stripImage();

            // Restore color profiles
            if (!empty($profiles)) {
                $im->profileImage("icc", $profiles['icc']);
            }
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

        // We used to use writeImageFile() method. But we now use getImageBlob(). See issue #43

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
