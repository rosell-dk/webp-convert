<?php

// TODO: Quality option

namespace WebPConvert\Converters;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

use WebPConvert\Convert\BaseConverter;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Stack extends BaseConverter
{
    public static $extraOptions = [
        [
            'name' => 'converters',
            'type' => 'array',
            'sensitive' => true,
            'default' => ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'],
            'required' => false
        ],
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];

    public static $availableConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary', 'wpc', 'ewww'];
    public static $localConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'];


    public static function getClassNameOfConverter($converterId)
    {
        return 'WebPConvert\\Converters\\' . ucfirst($converterId);
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in BaseConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {
        $options = $this->options;

        // If we have set converter options for a converter, which is not in the converter array,
        // then we add it to the array
        if (isset($options['converter-options'])) {
            foreach ($options['converter-options'] as $converterName => $converterOptions) {
                if (!in_array($converterName, $options['converters'])) {
                    $options['converters'][] = $converterName;
                }
            }
        }

        //$this->logLn('converters: ' . print_r($options['converters'], true));

        $defaultConverterOptions = $options;

        unset($defaultConverterOptions['converters']);
        unset($defaultConverterOptions['converter-options']);
        $defaultConverterOptions['_skip_basic_validations'] = true;
        $defaultConverterOptions['_suppress_success_message'] = true;

        $anyRuntimeErrors = false;
        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = $converter['options'];
            } else {
                $converterId = $converter;
                $converterOptions = [];
                if (isset($options['converter-options'][$converterId])) {
                    // Note: right now, converter-options are not meant to be used,
                    //       when you have several converters of the same type
                    $converterOptions = $options['converter-options'][$converterId];
                }
            }

            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);

            // If quality is different, we must recalculate
            if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                unset($converterOptions['_calculated_quality']);
            }

            $beginTime = microtime(true);

            try {
                $this->ln();
                $this->logLn('Trying:' . $converterId, 'italic');

                $className = self::getClassNameOfConverter($converterId);
                if (!is_callable([$className, 'convert'])) {
                    throw new ConverterNotFoundException();
                }

                call_user_func(
                    [$className, 'convert'],
                    $this->source,
                    $this->destination,
                    $converterOptions,
                    $this->logger
                );

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLnLn('success');
                return true;
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
                $this->logLn($e->getMessage());

            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                $this->logLn($e->getMessage());
                $anyRuntimeErrors = true;

            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                $this->logLn($e->getMessage());
            }

            $this->logLn('Failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
        }

        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConverterFailedException('None of the converters in the stack could convert the image. At least one failed, even though its requirements seemed to be met.');

        } else {
            // All converters threw a ConverterNotOperationalException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }

    }
}
