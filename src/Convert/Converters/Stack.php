<?php

// TODO: Quality option

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Stack extends AbstractConverter
{
    public static $extraOptions = [
        [
            'name' => 'converters',
            'type' => 'array',
            'sensitive' => true,
            'default' => ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'],
            'required' => false
        ],
        /*
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => false,
            'required' => false
        ],*/
        /*[
            'name' => 'quality',
            'type' => 'quality',
            'sensitive' => false,
            'default' => 'auto',
            'required' => false
        ],*/
    ];

    public static $availableConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary', 'wpc', 'ewww'];
    public static $localConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'];


    public static function getClassNameOfConverter($converterId)
    {
        if (strtolower($converterId) == $converterId) {
            $className = 'WebPConvert\\Convert\\Converters\\' . ucfirst($converterId);
            if (is_callable([$className, 'convert'])) {
                return $className;
            } else {
                throw new ConverterNotFoundException('There is no converter with id:' . $converterId);
            }
        }
        $className = $converterId;
        if (!is_callable([$className, 'convert'])) {
            throw new ConverterNotFoundException('There is no converter with class name:' . $className);
        }

        return $className;
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {
        $options = $this->options;

        $beginTimeStack = microtime(true);

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

            // We could have decided to carry on, if a converter could not be found,
            // However, such an error should be corrected, so we decided to fail in that case (and skip rest of queue)
            $className = self::getClassNameOfConverter($converterId);
            if (!is_callable([$className, 'convert'])) {
                throw new ConverterNotFoundException(
                    'There is no converter with id:' . $converterId .
                    ' (and it is not a class either)'
                );
            }


            try {
                $converterDisplayName = call_user_func(
                    [$className, 'getConverterDisplayName']
                );

                $this->ln();
                $this->logLn('Trying: ' . $converterId, 'italic');

                call_user_func(
                    [$className, 'convert'],
                    $this->source,
                    $this->destination,
                    $converterOptions,
                    $this->logger
                );

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLn($converterDisplayName . ' succeeded :)');
                return;
            } catch (ConverterNotOperationalException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionFailedException $e) {
                $this->logLn($e->getMessage(), 'italic');
                $prev = $e->getPrevious();
                if (!is_null($prev)) {
                    $this->logLn($prev->getMessage(), 'italic');
                    $this->logLn(' in ' . $prev->getFile() . ', line ' . $prev->getLine(), 'italic');
                    $this->ln();
                }
                //$this->logLn($e->getTraceAsString());
                $anyRuntimeErrors = true;
            } catch (ConversionDeclinedException $e) {
                $this->logLn($e->getMessage());
            }

            $this->logLn($converterDisplayName . ' failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
        }

        $this->ln();
        $this->logLn('Stack failed in ' . round((microtime(true) - $beginTimeStack) * 1000) . ' ms');

        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConversionFailedException(
                'None of the converters in the stack could convert the image. ' .
                'At least one failed, even though its requirements seemed to be met.'
            );
        } else {
            // All converters threw a SystemRequirementsNotMetException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }
    }
}
