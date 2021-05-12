<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Helpers\JpegQualityDetector;

/**
 * Trait for handling the "quality:auto" option.
 *
 * This trait is only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning auto quality.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait AutoQualityTrait
{

    abstract public function logLn($msg, $style = '');
    abstract public function getMimeTypeOfSource();

    /** @var boolean  Whether the quality option has been processed or not */
    private $processed = false;

    /** @var boolean  Whether the quality of the source could be detected or not (set upon processing) */
    private $qualityCouldNotBeDetected = false;

    /** @var integer  The calculated quality (set upon processing - on successful detection) */
    private $calculatedQuality;


    /**
     *  Determine if quality detection is required but failing.
     *
     *  It is considered "required" when:
     *  - Mime type is "image/jpeg"
     *  - Quality is set to "auto"
     *
     *  If quality option hasn't been proccessed yet, it is triggered.
     *
     *  @return  boolean
     */
    public function isQualityDetectionRequiredButFailing()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->qualityCouldNotBeDetected;
    }

    /**
     * Get calculated quality.
     *
     * If the "quality" option is a number, that number is returned.
     * If mime type of source is something else than "image/jpeg", the "default-quality" option is returned
     * If quality is "auto" and source is a jpeg image, it will be attempted to detect jpeg quality.
     * In case of failure, the value of the "default-quality" option is returned.
     * In case of success, the detected quality is returned, or the value of the "max-quality" if that is lower.
     *
     *  @return  int
     */
    public function getCalculatedQuality()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->calculatedQuality;
    }

    /**
     * Process the quality option if it is not already processed.
     *
     * @return void
     */
    private function processQualityOptionIfNotAlready()
    {
        if (!$this->processed) {
            $this->processed = true;
            $this->processQualityOption();
        }
    }

    /**
     * Process the quality option.
     *
     * Sets the private property "calculatedQuality" according to the description for the getCalculatedQuality
     * function.
     * In case quality detection was attempted and failed, the private property "qualityCouldNotBeDetected" is set
     * to true. This is used by the "isQualityDetectionRequiredButFailing" (and documented there too).
     *
     * @return void
     */
    private function processQualityOption()
    {
        $options = $this->options;
        $source = $this->source;

        /*
        Mapping from old options to new options:
        quality: "auto", max-quality: 85, default-quality: 75
        becomes: quality: 85, auto-limit: true

        quality: 80
        becomes: quality: 80, auto-limit: false
        */
        $q = $options['quality'];
        if ($q == 'auto') {
            $q = $options['quality'] = $options['max-quality'];
            $this->logLn(
                '*Setting "quality" to "auto" is deprecated. ' .
                'Instead, set "quality" to a number (0-100) and "auto-limit" to true. '
            );
            $this->logLn(
                '*"quality" has been set to: ' . $options['max-quality'] . ' (took the value of "max-quality").*'
            );
            if (!$this->options2->getOptionById('auto-limit')->isValueExplicitlySet()) {
                $options['auto-limit'] = true;
                $this->logLn(
                    '*"auto-limit" has been set to: true."*'
                );
            } else {
                $this->logLn(
                    '*PS: "auto-limit" is set to false, as it was set explicitly to false in the options."*'
                );
            }
        }

        if ($options['auto-limit']) {
            if (($this->/** @scrutinizer ignore-call */getMimeTypeOfSource() == 'image/jpeg')) {
                $this->logLn('Running auto-limit');
                $this->logLn(
                    'Quality setting: ' . $q . '. '
                );
                $q = JpegQualityDetector::detectQualityOfJpg($source);
                if (is_null($q)) {
                    $q = $options['quality'];
                    $this->/** @scrutinizer ignore-call */logLn(
                        'Quality of source image could not be established (Imagick or GraphicsMagick is required). ' .
                        'Sorry, no auto-limit functionality for you. Using supplied quality (' . $q . ').'
                    );

                    $this->qualityCouldNotBeDetected = true;
                } else {
                    $this->logLn(
                        'Quality of jpeg: ' . $q . '. '
                    );
                    if ($q < $options['quality']) {
                        $this->logLn(
                            'Auto-limit result: ' . $q . ' ' .
                            '(limiting applied).'
                        );
                    } else {
                        $q = $options['quality'];
                        $this->logLn(
                            'Auto-limit result: ' . $q . ' ' .
                            '(no limiting needed this time).'
                        );
                    }
                }
                $q = min($q, $options['max-quality']);
            } else {
                $this->logLn('Bypassing auto-limit (it is only active for jpegs)');
                $this->logLn('Quality: ' . $q . '. ');
            }
        } else {
            $this->logLn(
                'Quality: ' . $q . '. '
            );
            if (($this->getMimeTypeOfSource() == 'image/jpeg')) {
                $this->logLn(
                    'Consider enabling "auto-limit" option. This will prevent unnecessary high quality'
                );
            }
        }
        $this->calculatedQuality = $q;
    }
}
