<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class StringOption extends Option
{

    protected $typeId = 'string';
    protected $enum;
    protected $schemaType = ['string'];

    public function __construct($id, $defaultValue, $enum = null)
    {
        $this->enum = $enum;
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        $this->checkType('string');

        if (!is_null($this->enum) && (!in_array($this->getValue(), $this->enum))) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be on of these values: ' .
                '[' . implode(', ', $this->enum) . ']. ' .
                'It was however set to: "' . $this->getValue() . '"'
            );
        }
    }

    public function getValueForPrint()
    {
        return '"' . $this->getValue() . '"';
    }

    public function getDefinition()
    {
        $obj = parent::getDefinition();
        $obj['sensitive'] = false;
        if (!is_null($this->enum)) {
            $obj['options'] = $this->enum;
        }
        return $obj;
    }
}
