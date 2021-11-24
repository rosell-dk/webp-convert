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

    /** @var array  Type constraints for the value (JSON schema syntax) */
    protected $schemaType = [];

    /** @var array|null  Array of allowed values (JSON schema syntax) */
    protected $enum = null; //https://json-schema.org/understanding-json-schema/reference/generic.html#enumerated-values

    /** @var boolean  Whether the option has been deprecated */
    protected $deprecated = false;

    /** @var string  Help text */
    protected $helpText = '';

    /** @var array  UI Def */
    protected $ui;

    /** @var array  Extra Schema Def (ie holding 'title', 'description' or other)*/
    protected $extraSchemaDefs;


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
     * Get Id.
     *
     * @param string $id  The id of the option
     */
    public function setId($id)
    {
        $this->id = $id;
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

    /**
     * Set help text for the option
     *
     * @param  string  $helpText  The help text
     * @return  void
     */
    public function setHelpText($helpText)
    {
        $this->helpText = $helpText;
    }

    /**
     * Get help text for the option
     *
     * @return  string  $helpText  The help text
     */
    public function getHelpText()
    {
        return $this->helpText;
    }

    /**
     * Set ui definition for the option
     *
     * @param  array  $ui  The UI def
     * @return  void
     */
    public function setUI($ui)
    {
        $this->ui = $ui;
    }

    public function setExtraSchemaDefs($def)
    {
        $this->extraSchemaDefs = $def;
    }


    /**
     * Get ui definition for the option
     *
     * @return  array  $ui  The UI def
     */
    public function getUI()
    {
        return $this->ui;
    }

    public function getSchema()
    {
        if (isset($this->extraSchemaDefs)) {
            $schema = $this->extraSchemaDefs;
        } else {
            $schema = [];
        }
        $schema['type'] = $this->schemaType;
        $schema['default'] = $this->defaultValue;
        if (!is_null($this->enum)) {
            $schema['enum'] = $this->enum;
        }
        return $schema;
    }


    public function getDefinition()
    {
        $obj = [
            'id' => $this->id,
            'schema' => $this->getSchema(),
            'ui' => $this->ui,
        ];
        if ($this->deprecated) {
            $obj['deprecated'] = true;
        }
        return $obj;
    }
}
