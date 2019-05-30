<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\IntegerOrNullOption;
use WebPConvert\Options\MetadataOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Options\QualityOption;

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

    /** @var Options  */
    protected $options2;

    abstract protected function getMimeTypeOfSource();
    abstract protected static function getConverterId();

    /**
     *  Create options.
     *
     *  The options created here will be available to all converters.
     *  Individual converters may add options by overriding this method.
     *
     *  @return void
     */
    protected function createOptions()
    {
        $isPng = ($this->getMimeTypeOfSource() == 'image/png');

        $this->options2 = new Options();
        $this->options2->addOptions(
            new IntegerOption('alpha-quality', 85, 0, 100),
            new BooleanOption('auto-filter', false),
            new IntegerOption('default-quality', ($isPng ? 85 : 75), 0, 100),
            new StringOption('encoding', 'auto', ['lossy', 'lossless', 'auto']),
            new BooleanOption('low-memory', false),
            new BooleanOption('log-call-arguments', false),
            new IntegerOption('max-quality', 85, 0, 100),
            new MetadataOption('metadata', 'none'),
            new IntegerOption('method', 6, 0, 6),
            new IntegerOption('near-lossless', 60, 0, 100),
            new StringOption('preset', 'none', ['none', 'default', 'photo', 'picture', 'drawing', 'icon', 'text']),
            new QualityOption('quality', ($isPng ? 85 : 'auto')),
            new IntegerOrNullOption('size-in-percentage', null, 0, 100),
            new BooleanOption('skip', false),
            new BooleanOption('use-nice', false)
        );
    }

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
        $this->createOptions();

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
        foreach ($this->providedOptions as $optionKey => $optionValue) {
            if (substr($optionKey, 0, $strLen + 1) == ($converterId . '-')) {
                $this->providedOptions[substr($optionKey, $strLen + 1)] = $optionValue;
            }
        }

        // Create options (Option objects)
        foreach ($this->providedOptions as $optionId => $optionValue) {
            $this->options2->setOrCreateOption($optionId, $optionValue);
        }
        //$this->logLn(print_r($this->options2->getOptions(), true));
//$this->logLn($this->options2->getOption('hello'));

        // Create flat associative array of options
        $this->options = $this->options2->getOptions();

        // -  Merge $defaultOptions into provided options
        //$this->options = array_merge($this->getDefaultOptions(), $this->providedOptions);

        //$this->logOptions();
    }

    /**
     * Get the resulting options after merging provided options with default options.
     *
     * Note that the defaults depends on the mime type of the source. For example, the default value for quality
     * is "auto" for jpegs, and 85 for pngs.
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
     * @param  string  $id      Id of option (ie "metadata")
     * @param  mixed   $value   The new value.
     * @return void
     */
    protected function setOption($id, $value)
    {
        $this->options[$id] = $value;
        $this->options2->setOrCreateOption($id, $value);
    }

    /**
     *  Check options.
     *
     *  @throws InvalidOptionValueException  if an option value have wrong type or is out of range
     *  @throws ConversionSkippedException  if 'skip' option is set to true
     *  @return void
     */
    protected function checkOptions()
    {
        $this->options2->check();

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

/*
    private function logOption($def) {
        list($optionName, $optionType) = $def;
        $sensitive = (isset($def[3]) && $def[3] === true);
        if ($sensitive) {
            $printValue = '*****';
        } else {
            $printValue = $this->options[$optionName];
            //switch ($optionType) {
            switch (gettype($printValue)) {
                case 'boolean':
                    $printValue = ($printValue === true ? 'true' : 'false');
                    break;
                case 'string':
                    $printValue = '"' . $printValue . '"';
                    break;
                case 'NULL':
                    $printValue = 'NULL';
                    break;
                case 'array':
                    //$printValue = print_r($printValue, true);
                    if (count($printValue) == 0) {
                        $printValue = '(empty array)';
                    } else {
                        $printValue = '(array of ' . count($printValue) . ' items)';
                    }
                    break;
            }
        }

        $this->log($optionName . ': ', 'italic');
        $this->logLn($printValue);
        //$this->logLn($optionName . ': ' . $printValue, 'italic');
            //(isset($this->providedOptions[$optionName]) ? '' : ' (using default)')

        //$this->logLn(gettype($printValue));
    }*/

    public function logOptions()
    {
    }
}
