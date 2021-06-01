<?php

namespace WebPConvert\Options;

use WebPConvert\Options\StringOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class SensitiveStringOption extends StringOption
{

    public function __construct($id, $defaultValue, $enum = null)
    {
        parent::__construct($id, $defaultValue, $enum);
    }

    public function check()
    {
        parent::check();
    }

    public function getValueForPrint()
    {
        if (strlen($this->getValue()) == 0) {
            return '""';
        }
        return '*****';
    }

    public function getDefinition()
    {
        $obj = parent::getDefinition();
        $obj['sensitive'] = true;
        return $obj;
    }
}
