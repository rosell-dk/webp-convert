<?php

namespace WebPConvert\Convert\BaseConverters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidOptionTypeException;

trait OptionsTrait
{

    /** @var array  Provided conversion options */
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    public $options;

    // The concrete converters must supply this method...
    abstract protected function getOptionDefinitionsExtra();

    abstract protected function getMimeTypeOfSource();

    public static $optionDefinitionsBasic = [
        ['quality', 'number|string', 'auto'],
        ['max-quality', 'number', 85],
        ['default-quality', 'number', 75],
        ['metadata', 'string', 'none'],
        ['lossless', 'boolean|string', false],
        ['skip', 'boolean', false],
    ];

    /**
     * Set "provided options"
     * This also sets the internal options array, by merging in the default options
     *
     * @param   array $providedOptions (optional)
     * @return  void
     */
    public function setProvidedOptions($providedOptions = [])
    {
        $this->providedOptions = $providedOptions;

        if (isset($this->providedOptions['png'])) {
            if ($this->getMimeTypeOfSource() == 'image/png') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['png']);
//                $this->logLn(print_r($this->providedOptions, true));
            }
        }

        if (isset($this->providedOptions['jpeg'])) {
            if ($this->getMimeTypeOfSource() == 'image/jpeg') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['jpeg']);
            }
        }
        // -  Merge $defaultOptions into provided options
        $this->options = array_merge($this->getDefaultOptions(), $this->providedOptions);
    }


    public function getAllOptionDefinitions()
    {
        return array_merge(self::$optionDefinitionsBasic, $this->getOptionDefinitionsExtra());
    }

    public function getDefaultOptions()
    {
        $defaults = [];
        foreach ($this->getAllOptionDefinitions() as list($name, $type, $default)) {
            $defaults[$name] = $default;
        }
        if ($this->getMimeTypeOfSource() == 'image/png') {
            $defaults['lossless'] = 'auto';
        }
        return $defaults;
    }

    protected function checkOptions()
    {
        foreach ($this->getAllOptionDefinitions() as $def) {
            list($optionName, $optionType) = $def;

            if (isset($this->providedOptions[$optionName])) {
                //$this->logLn($optionName);

                $actualType = gettype($this->providedOptions[$optionName]);
                if ($actualType != $optionType) {
                    $optionType = str_replace('number', 'integer|double', $optionType);
                    if (!in_array($actualType, explode('|', $optionType))) {
                        throw new InvalidOptionTypeException(
                            'The provided ' . $optionName . ' option is not a ' . $optionType .
                                ' (it is a ' . $actualType . ')'
                        );
                    }
                }

                $optionValue = $this->providedOptions[$optionName];

                if ($optionName == 'quality') {
                    if ($actualType == 'string') {
                        if ($optionValue != 'auto') {
                            throw new InvalidOptionTypeException(
                                'Quality option must be either "auto" or a number between 0-100. ' .
                                'A string, "' . $optionValue . '" was given'
                            );
                        }
                    } else {
                        if (($optionValue < 0) || ($optionValue > 100)) {
                            throw new InvalidOptionTypeException(
                                'Quality option must be either "auto" or a number between 0-100. ' .
                                    'The number you provided (' . strval($optionValue) . ') is out of range.'
                            );
                        }
                    }
                }

                if (($optionName == 'lossless') && ($actualType == 'string')  && ($optionValue != 'auto')) {
                    throw new InvalidOptionTypeException(
                        'Lossless option must be true, false or "auto". It was set to: "' . $optionValue . '"'
                    );
                }
            }
        }

        if ($this->options['skip']) {
            if (($this->getMimeTypeOfSource() == 'image/png') && isset($this->options['png']['skip'])) {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so for PNG)'
                );
            } else {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so)'
                );
            }
        }
    }

    /**
     * Prepare options.
     */
     /*
    private function prepareOptions()
    {
        //$defaultOptions = self::$defaultOptions;

        // -  Merge defaults of the converters extra options into the standard default options.
        //$defaultOptions = array_merge($defaultOptions, array_column(static::$extraOptions, 'default', 'name'));
        //print_r($this->getOptionDefinitionsExtra());
        //$extra = [];
        //$this->getDefaultOptionsExtra();
        //echo '<br>';
        //print_r(static::$extraOptions);
        //print_r(array_column(static::$extraOptions, 'default', 'name'));
        //$defaultOptions = array_merge($defaultOptions, $this->getDefaultOptionsExtra());


        //throw new \Exception('extra!' . print_r($this->getConverterDisplayName(), true));

        // -  Merge $defaultOptions into provided options
        //$this->options = array_merge($defaultOptions, $this->options);
        //$this->options = array_merge($this->getDefaultOptions(), $providedOptions);

        if ($this->getMimeTypeOfSource() == 'png') {
            // skip png's ?
            if ($this->options['skip-pngs']) {
                throw new ConversionSkippedException(
                    'PNG file skipped (configured to do so)'
                );
            }
        }


        // TODO: Here we could test if quality is 0-100 or auto.
        //       and if not, throw something extending InvalidArgumentException (which is a LogicException)
    }*/
}
