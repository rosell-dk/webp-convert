<?php

namespace WebPConvert\Options;

use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\IntegerOrNullOption;
use WebPConvert\Options\MetadataOption;
use WebPConvert\Options\StringOption;
use WebPConvert\Options\SensitiveStringOption;
use WebPConvert\Options\QualityOption;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.7.0
 */
class OptionFactory
{

    public static function createOption($optionName, $optionType, $def)
    {
        $option = null;
        switch ($optionType) {
            case 'int':
                $minValue = (isset($def['minimum']) ? $def['minimum'] : null);
                $maxValue = (isset($def['maximum']) ? $def['maximum'] : null);
                unset($def['minimum']);
                unset($def['maximum']);
                if (isset($def['allow-null']) && $def['allow-null']) {
                    $option = new IntegerOrNullOption($optionName, $def['default'], $minValue, $maxValue);
                } else {
                    if ($optionName == 'quality') {
                        $option = new QualityOption($optionName, $def['default']);
                    } else {
                        $option = new IntegerOption($optionName, $def['default'], $minValue, $maxValue);
                    }
                }
                break;

            case 'string':
                if ($optionName == 'metadata') {
                    $option = new MetadataOption($optionName, $def['default']);
                } else {
                    $enum = (isset($def['enum']) ? $def['enum'] : null);
                    if (isset($def['sensitive']) && ($def['sensitive'] == true)) {
                        unset($def['sensitive']);
                        $option = new SensitiveStringOption($optionName, $def['default'], $enum);
                    } else {
                        $option = new StringOption($optionName, $def['default'], $enum);
                    }
                }
                break;

            case 'boolean':
                $option = new BooleanOption($optionName, $def['default']);
                break;

            case 'array':
                if (isset($def['sensitive']) && ($def['sensitive'] == true)) {
                    $option = new SensitiveArrayOption($optionName, $def['default']);
                } else {
                    $option = new ArrayOption($optionName, $def['default']);
                }
                break;
        }
        unset($def['default']);

        if (!is_null($option)) {
            if (isset($def['deprecated'])) {
                $option->markDeprecated();
            }
            if (isset($def['ui'])) {
                $option->setUI($def['ui']);
                unset($def['ui']);
            }
        }
        $option->setExtraSchemaDefs($def);
        return $option;
    }

    public static function createOptions($def)
    {
        $result = [];
        foreach ($def as $i => list($optionName, $optionType, $optionDef)) {
            $option = self::createOption($optionName, $optionType, $optionDef);
            if (!is_null($option)) {
                $result[] = $option;
            }
        }
        return $result;
    }
}
