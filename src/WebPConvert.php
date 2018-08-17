<?php

namespace WebPConvert;

use WebPConvert\Converters\ConverterHelper;

class WebPConvert
{

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        ConverterHelper::processQualityOption($source, $options, $logger);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        $defaultConverterOptions = $options;
        $defaultConverterOptions['converters'] = null;

        $firstFailException = null;

        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = $converter['options'];
            } else {
                $converterId = $converter;
                $converterOptions = [];
            }

            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);

            try {
                $logger->logLn('Trying:' . $converterId, 'italic');

                // If quality is different, we must recalculate
                if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                    unset($converterOptions['_calculated_quality']);
                    ConverterHelper::processQualityOption($source, $converterOptions, $logger);
                }

                ConverterHelper::runConverter($converterId, $source, $destination, $converterOptions, false, $logger);

                // Still here? - well, we did it! - job is done.
                $logger->logLn('ok', 'bold');
                return true;
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
//                $logger->logLnLn($e->description . ' : ' . $e->getMessage());
                $logger->logLnLn($e->getMessage());

                // The converter is not operational.
                // Well, well, we will just have to try the next, then
            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                $logger->logLnLn($e->getMessage());

                // Converter failed in an anticipated, yet somewhat surprising fashion.
                // The converter seemed operational - requirements was in order - but it failed anyway.
                // This is moderately bad.
                // If some other converter can handle the conversion, we will let this one go.
                // But if not, we shall throw the exception

                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                $logger->logLnLn($e->getMessage());

                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                // We also possibly rethrow this, because it may have come as a surprise to the user
                // who perhaps only tested jpg
                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            }
        }

        if ($firstFailException) {
            // At least one converter failed or declined.
            $logger->logLn('Conversion failed. None of the tried converters could convert the image', 'bold');
        } else {
            // All converters threw a ConverterNotOperationalException
            $logger->logLn('Conversion failed. None of the tried converters are operational', 'bold');
        }

        // No converters could do the job.
        // If one of them failed moderately bad, rethrow that exception.
        if ($firstFailException) {
            throw $firstFailException;
        }

        return false;
    }
}
