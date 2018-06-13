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
    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        $defaultConverterOptions = $options;
        $defaultConverterOptions['converters'] = null;

        $success = false;

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
                ConverterHelper::callConvert($converterId, $source, $destination, $converterOptions, false);

                if (file_exists($destination)) {
                    $success = true;
                    break;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
                // The converter is not operational.
                // Well, well, we will just have to try the next, then
            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                // Converter failed in an anticipated fashion.
                // If no converter is able to do a conversion, we will rethrow the exception.
                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            }


            // As success will break the loop, being here means that no converters could
            // do the conversion.
            // If no converters are operational, simply return false
            // Otherwise rethrow the exception that was thrown first (the most prioritized converter)
            if ($firstFailException) {
                throw $e;
            }

            $success = false;
        }

        return $success;
    }
}
