<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidOptionTypeException;

/**
 * Trait for handling options
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait OptionsTrait
{

    /** @var array  Provided conversion options */
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    protected $options;

    abstract protected function getMimeTypeOfSource();
    abstract protected static function getConverterId();

    /** @var array  Definitions of general options (the options that are available on all converters) */
    protected static $optionDefinitionsBasic = [
        ['alpha-quality', 'integer', 85],
        ['autofilter', 'boolean', false],
        ['default-quality', 'number', 75],       // PS: Default is altered to 85 for PNG in ::getDefaultOptions()
        ['encoding', 'string', "auto"],          // PS: Default is altered to "lossy" for JPG in ::getDefaultOptions()
        //['lossless', 'boolean|string', false],   // PS: Default is altered to "auto" for PNG in ::getDefaultOptions()
        ['low-memory', 'boolean', false],
        ['max-quality', 'number', 85],
        ['metadata', 'string', 'none'],
        ['method', 'number', 6],
        ['near-lossless', 'integer', 60],
        ['preset', 'string', null],              // ('default' | 'photo' | 'picture' | 'drawing' | 'icon' | 'text')
        ['quality', 'number|string', 'auto'],    // PS: Default is altered to 85 for PNG in ::getDefaultOptions()
        ['size-in-percentage', 'number', null],
        ['skip', 'boolean', false],
        ['use-nice', 'boolean', false],
    ];

    /**
     * Set "provided options" (options provided by the user when calling convert().
     *
     * This also calculates the protected options array, by merging in the default options, merging
     * jpeg and png options and merging prefixed options (such as 'vips-quality').
     * The resulting options array are set in the protected property $this->options and can be
     * retrieved using the public ::getOptions() function.
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

        // merge down converter-prefixed options
        $converterId = self::getConverterId();
        $strLen = strlen($converterId);
        //$this->logLn('id:' . $converterId);
        foreach ($this->providedOptions as $optionKey => $optionValue) {
            //$this->logLn($optionKey . ':' . $optionValue);
            //$this->logLn(substr($optionKey, 0, strlen($converterId)));
            if (substr($optionKey, 0, $strLen + 1) == ($converterId . '-')) {
                //$this->logLn($optionKey . ':' . $optionValue);
                //$this->logLn(substr($optionKey, $strLen + 1));
                $this->providedOptions[substr($optionKey, $strLen + 1)] = $optionValue;
            }
        }

        // -  Merge $defaultOptions into provided options
        $this->options = array_merge($this->getDefaultOptions(), $this->providedOptions);
    }

    /**
     * Get the resulting options after merging provided options with default options.
     *
     * @return array  An associative array of options: ['metadata' => 'none', ...]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Change an option specifically.
     *
     * This method is probably rarely neeeded. We are using it to change the "encoding" option temporarily
     * in the EncodingAutoTrait.
     *
     * @param  string  $optionName   Name id of option (ie "metadata")
     * @param  mixed   $optionValue  The new value.
     * @return void
     */
    protected function setOption($optionName, $optionValue)
    {
        $this->options[$optionName] = $optionValue;
    }


    /**
     * Get default options for the converter.
     *
     * Note that the defaults depends on the mime type of the source. For example, the default value for quality
     * is "auto" for jpegs, and 85 for pngs.
     *
     * @return array  An associative array of option defaults: ['metadata' => 'none', ...]
     */
    public function getDefaultOptions()
    {
        $defaults = [];
        foreach ($this->getOptionDefinitions() as list($name, $type, $default)) {
            $defaults[$name] = $default;
        }
        if ($this->getMimeTypeOfSource() == 'image/png') {
            $defaults['quality'] = 85;
            $defaults['default-quality'] = 85;
        }
        if ($this->getMimeTypeOfSource() == 'image/jpeg') {
            $defaults['encoding'] = 'lossy';
        }
        return $defaults;
    }


    /**
     * Get definitions of general options (those available for all converters)
     *
     * To get only the extra definitions for a specific converter, call
     * ::getOptionDefinitionsExtra(). To get both general and extra, merged together, call ::getOptionDefinitions().
     *
     * @return array  A numeric array of definitions of general options for the converter.
     *                Each definition is a numeric array with three items: [option id, type, default value]
     */
    public function getGeneralOptionDefinitions()
    {
        return self::$optionDefinitionsBasic;
    }

    /**
     * Get definitions of extra options unique for the actual converter.
     *
     * @return array  A numeric array of definitions of extra options for the converter.
     *                Each definition is a numeric array with three items: [option id, type, default value]
     */
    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    /**
     * Get option definitions for the converter (includes both general options and the extra options for the converter)
     *
     * To get only the general options definitions (those available for all converters), call
     * ::getGeneralOptionDefinitions(). To get only the extra definitions for a specific converter, call
     * ::getOptionDefinitionsExtra().
     *
     * @return array  A numeric array of definitions of all options for the converter.
     *                Each definition is a numeric array with three items: [option id, type, default value]
     */
    public function getOptionDefinitions()
    {
        return array_merge(self::$optionDefinitionsBasic, $this->getOptionDefinitionsExtra());
    }

    /**
     *  Check option types generally (against their definitions).
     *
     *  @throws InvalidOptionTypeException  if type is invalid
     *  @return void
     */
    private function checkOptionTypesGenerally()
    {
        foreach ($this->getOptionDefinitions() as $def) {
            list($optionName, $optionType) = $def;
            if (isset($this->providedOptions[$optionName])) {
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
            }
        }
    }

    /**
     *  Check quality option
     *
     *  @throws InvalidOptionTypeException  if value is out of range
     *  @return void
     */
    private function checkQualityOption()
    {
        if (!isset($this->providedOptions['quality'])) {
            return;
        }
        $optionValue = $this->providedOptions['quality'];
        if (gettype($optionValue) == 'string') {
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

    /**
     *  Check "encoding" option.
     *
     *  @throws InvalidOptionTypeException  if value is out of range
     *  @return void
     */
    private function checkEncodingOption()
    {
        if (!isset($this->providedOptions['encoding'])) {
            return;
        }
        $optionValue = $this->providedOptions['encoding'];
        if (!in_array($optionValue, ['lossy', 'lossless', 'auto'])) {
            throw new InvalidOptionTypeException(
                '"encoding" option must be "lossy", "lossless" or "auto". It was set to: "' . $optionValue . '"'
            );
        }
    }

    /**
     *  Check option types.
     *
     *  @throws InvalidOptionTypeException  if an option value have wrong type or is out of range
     *  @return void
     */
    private function checkOptionTypes()
    {
        $this->checkOptionTypesGenerally();
        $this->checkQualityOption();
        $this->checkEncodingOption();
    }

    /**
     *  Check options.
     *
     *  @throws InvalidOptionTypeException  if an option value have wrong type or is out of range
     *  @throws ConversionSkippedException  if 'skip' option is set to true
     *  @return void
     */
    protected function checkOptions()
    {
        $this->checkOptionTypes();

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
}
