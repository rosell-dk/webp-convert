<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Exceptions\InvalidOptionTypeException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * (base) option class.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Option
{
    /** @var string  The id of the option */
    protected $id;

    /** @var mixed  The default value of the option */
    protected $defaultValue;

    /** @var mixed  The value of the option */
    protected $value;

    /** @var boolean  Whether the value has been set (if not, getValue() will return the default value) */
    protected $isExplicitlySet = false;

    /** @var string  An option must supply a type id */
    protected $typeId;

    /** @var array  Type constraints for the value */
    protected $allowedValueTypes = [];

    /** @var boolean  Whether the option has been deprecated */
    protected $deprecated = false;

    /** @var string  Help text */
    protected $helpText = '';


    /**
     * Constructor.
     *
     * @param   string  $id              id of the option
     * @param   mixed   $defaultValue    default value for the option
     * @throws  InvalidOptionValueException  if the default value cannot pass the check
     * @throws  InvalidOptionTypeException   if the default value is wrong type
     * @return  void
     */
    public function __construct($id, $defaultValue)
    {
        $this->id = $id;
        $this->defaultValue = $defaultValue;

        // Check that default value is ok
        $this->check();
    }

    /**
     * Get Id.
     *
     * @return  string  The id of the option
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get default value.
     *
     * @return  mixed  The default value for the option
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }


    /**
     * Get value, or default value if value has not been explicitly set.
     *
     * @return  mixed  The value/default value
     */
    public function getValue()
    {
        if (!$this->isExplicitlySet) {
            return $this->defaultValue;
        } else {
            return $this->value;
        }
    }

    /**
     * Get to know if value has been set.
     *
     * @return  boolean  Whether or not the value has been set explicitly
     */
    public function isValueExplicitlySet()
    {
        return $this->isExplicitlySet;
    }

    /**
     * Set value
     *
     * @param  mixed  $value  The value
     * @return  void
     */
    public function setValue($value)
    {
        $this->isExplicitlySet = true;
        $this->value = $value;
    }

    /**
     * Check if the value is valid.
     *
     * This base class does no checking, but this method is overridden by most other options.
     * @return  void
     */
    public function check()
    {
    }

    /**
     * Helpful function for checking type - used by subclasses.
     *
     * @param  string  $expectedType  The expected type, ie 'string'
     * @throws  InvalidOptionTypeException  If the type is invalid
     * @return  void
     */
    protected function checkType($expectedType)
    {
        if (gettype($this->getValue()) != $expectedType) {
            throw new InvalidOptionTypeException(
                'The "' . $this->id . '" option must be a ' . $expectedType .
                ' (you provided a ' . gettype($this->getValue()) . ')'
            );
        }
    }

    public function markDeprecated()
    {
        $this->deprecated = true;
    }

    public function isDeprecated()
    {
        return $this->deprecated;
    }

    public function getValueForPrint()
    {
        return print_r($this->getValue(), true);
    }


    /*  POST-PONED till 2.7.0

    public function getDefinition()
    {
        $obj = [
          'id' => $this->id,
          'type' => $this->typeId,
          'allowed-value-types' => $this->allowedValueTypes,
          'default' => $this->defaultValue,
          'help-text' => $this->helpText,
        ];
        if ($this->deprecated) {
            $obj['deprecated'] = true;
        }
        return $obj;
    }*/
}
