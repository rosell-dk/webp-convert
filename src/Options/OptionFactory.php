<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\GhostOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\IntegerOrNullOption;
use WebPConvert\Options\MetadataOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
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
                $minValue = (isset($def['min']) ? $def['min'] : null);
                $maxValue = (isset($def['max']) ? $def['max'] : null);
                if (isset($def['allow-null']) && $def['allow-null']) {
                    $option = new IntegerOrNullOption($optionName, $def['default'], $minValue, $maxValue);
                } else {
                    $option = new IntegerOption($optionName, $def['default'], $minValue, $maxValue);
                }
                break;

            case 'string':
                $allowedValues = (isset($def['allowedValues']) ? $def['allowedValues'] : null);
                $option = new StringOption($optionName, $def['default'], $allowedValues);
                break;

            case 'boolean':
                $option = new BooleanOption($optionName, $def['default']);
                break;
        }

        if (!is_null($option)) {
            if (isset($def['deprecated'])) {
                $option->markDeprecated();
            }
        }
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
