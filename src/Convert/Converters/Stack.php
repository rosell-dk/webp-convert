<?php

// TODO: Quality option

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by trying a stack of converters until success.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Stack extends AbstractConverter
{
    protected $processLosslessAuto = false;
    protected $supportsLossless = true;

    protected function getOptionDefinitionsExtra()
    {
        return [
            ['converters', 'array', ['cwebp', 'vips', 'gd', 'imagick', 'gmagick', 'imagickbinary'], true],
        ];
    }

    public static $availableConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary', 'wpc', 'ewww'];
    public static $localConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'];

    public static function converterIdToClassname($converterId)
    {
        switch ($converterId) {
            case 'imagickbinary':
                $classNameShort = 'ImagickBinary';
                break;
            default:
                $classNameShort = ucfirst($converterId);
        }
        $className = 'WebPConvert\\Convert\\Converters\\' . $classNameShort;
        if (is_callable([$className, 'convert'])) {
            return $className;
        } else {
            throw new ConverterNotFoundException('There is no converter with id:' . $converterId);
        }
    }

    public static function getClassNameOfConverter($converterId)
    {
        if (strtolower($converterId) == $converterId) {
            return self::converterIdToClassname($converterId);
        }
        $className = $converterId;
        if (!is_callable([$className, 'convert'])) {
            throw new ConverterNotFoundException('There is no converter with class name:' . $className);
        }

        return $className;
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (count($this->options) == 0) {
            throw new ConverterNotOperationalException(
                'Converter stack is empty! - no converters to try, no conversion can be made!'
            );
        }

        // TODO: We should test if all converters are found in order to detect problems early

        $this->logLn('Stack converter ignited');
    }

    protected function doActualConvert()
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
        $defaultConverterOptions['_skip_input_check'] = true;
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

            // TODO:
            // Reuse QualityProcessor of previous, unless quality option is overridden
            // ON the other hand: With the recent change, the quality is not detected until a
            // converter needs it (after operation checks). So such feature will rarely be needed now

            // If quality is different, we must recalculate
            /*
            if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                unset($converterOptions['_calculated_quality']);
            }*/

            $beginTime = microtime(true);

            $className = self::getClassNameOfConverter($converterId);


            try {
                $converterDisplayName = call_user_func(
                    [$className, 'getConverterDisplayName']
                );
            } catch (\Exception $e) {
                // TODO: handle failure better than this
                $converterDisplayName = 'Untitled converter';
            }

            try {
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
            } catch (ConversionSkippedException $e) {
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
