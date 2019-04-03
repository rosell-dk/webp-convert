<?php

namespace WebPConvert\Convert;

use WebPConvert\Helpers\JpegQualityDetector;

class QualityProcessor
{

    private $processed = false;
    private $qualityCouldNotBeDetected = false;
    private $calculatedQuality;

    public function __construct($converter)
    {
        $this->converter = $converter;
    }

    private function processIfNotAlready()
    {
        if (!$this->processed) {
            $this->processed = true;
            $this->proccess();
        }

    }

    public function isQualitySetToAutoAndDidQualityDetectionFail()
    {
        $this->processIfNotAlready();
        return $this->qualityCouldNotBeDetected;
    }

    public function getCalculatedQuality()
    {
        $this->processIfNotAlready();
        return $this->calculatedQuality;
    }


    private function proccess()
    {
        $options = $this->converter->options;
        $logger = $this->converter->logger;
        $source = $this->converter->source;

        $q = $options['quality'];
        if ($q == 'auto') {
            if (($this->converter->getMimeTypeOfSource() == 'image/jpeg')) {
                $q = JpegQualityDetector::detectQualityOfJpg($source);
                if (is_null($q)) {
                    $q = $options['default-quality'];
                    $logger->logLn(
                        'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                        ' - Using default instead (' . $options['default-quality'] . ').'
                    );

                    $this->qualityCouldNotBeDetected = true;
                } else {
                    if ($q > $options['max-quality']) {
                        $logger->logLn(
                            'Quality of source is ' . $q . '. ' .
                            'This is higher than max-quality, so using max-quality instead (' . $options['max-quality'] . ')'
                        );
                    } else {
                        $logger->logLn('Quality set to same as source: ' . $q);
                    }
                }
                $q = min($q, $options['max-quality']);
            } else {
                $logger->logLn('Quality: ' . $q . '. ');
            }
        } else {
            $logger->logLn(
                'Quality: ' . $q . '. ' .
                'Consider setting quality to "auto" instead. It is generally a better idea'
            );
        }
        $this->calculatedQuality = $q;
    }
}
