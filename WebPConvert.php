<?php

namespace WebPConvert;

class WebPConvert
{
    //private static $preferredConverters = [];
    //private static $excludeConverters = false;
    //private static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    //private static $converterOptions = array();

/*
    public static function setConverterOption($converter, $optionName, $optionValue)
    {
        if (!isset($converterOptions['converter'])) {
            $converterOptions['converter'] = array();
        }
        $converterOptions[$converter][$optionName] = $optionValue;
    }*/

    /* As there are many options available for imagick, it will be convenient to be able to set them in one go.
       So we will probably create a new public method setConverterOption($converter, $options)
       Example:

       setConverterOptions('imagick', array(
           'webp:low-memory' => 'true',
           'webp:method' => '6',
           'webp:lossless' => 'true',
       ));
       */


    // Defines the array of preferred converters
    /*
    public static function setConverterOrder($array, $exclude = false)
    {
        self::$preferredConverters = $array;

        if ($exclude) {
            self::$excludeConverters = true;
        }
    }*/

    /**
     * $converters: Ie: array('imagick', 'cwebp' => array('use-nice' => true))
     * $exclude
     */
    private static function getConverters($converters, $exclude = false)
    {
        // Prepare building up an array of converters
        $converters = [];

        // Saves all available converters inside the `Converters` directory to an array
        $availableConverters = array_map(function ($filePath) {
            $fileName = basename($filePath, '.php');
            return strtolower($fileName);
        }, glob(__DIR__ . '/Converters/*.php'));

        // Order the available converters so imagick comes first, then cwebp, then gd
        $availableConverters = array_unique(
            array_merge(
                array('imagick', 'cwebp', 'gd'),
                $availableConverters
            )
        );

        // Checks if preferred converters match available converters and adds all matches to $converters array
        foreach (self::$preferredConverters as $preferredConverter) {
            if (in_array($preferredConverter, $availableConverters)) {
                $converters[] = $preferredConverter;
            }
        }

        if ($exclude) {
            return $converters;
        }

        // Fills $converters array with the remaining available converters, keeping the updated order of execution
        foreach ($availableConverters as $availableConverter) {
            if (in_array($availableConverter, $converters)) {
                continue;
            }
            $converters[] = $availableConverter;
        }

        return $converters;
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'meta'
    */
    public static function convert($source, $destination, $options = array())
    {
        $defaultOptions = array(
            'quality' => 85,
            'metadata' => 'none',
            'method' => 6,
            'low-memory' => false,
            'converters' =>  array('cwebp', 'imagick', 'gd')
        );
        $options = array_merge($defaultOptions, $options);

        $defaultConverterOptions = $options;
        $defaultConverterOptions['converters'] = null;

        $success = false;

        $firstFailExecption = null;

        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = $converter['options'];
            } else {
                $converterId = $converter;
                $converterOptions = array();
            }

            $className = 'WebPConvert\\Converters\\' . ucfirst($converterId);

            if (!is_callable([$className, 'convert'])) {
                continue;
            }

            try {
                $converterOptions = array_merge($defaultConverterOptions, $converterOptions);
                $conversion = call_user_func(
                    [$className, 'convert'],
                    $source,
                    $destination,
                    $converterOptions
                );

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
                if (!$firstFailExecption) {
                    $firstFailExecption = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                if (!$firstFailExecption) {
                    $firstFailExecption = $e;
                }
            }


            // As success will break the loop, being here means that no converters could
            // do the conversion.
            // If no converters are operational, simply return false
            // Otherwise rethrow the exception that was thrown first (the most prioritized converter)
            if ($firstFailExecption) {
                throw $e;
            }

            $success = false;
        }

        return $success;
    }
}
