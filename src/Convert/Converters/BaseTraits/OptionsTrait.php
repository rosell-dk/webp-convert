<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;

use WebPConvert\Options\GhostOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\OptionFactory;

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

    abstract public function log($msg, $style = '');
    abstract public function logLn($msg, $style = '');
    abstract protected function getMimeTypeOfSource();

    /** @var array  Provided conversion options (array of simple objects)*/
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    protected $options;

    /** @var Options  */
    protected $options2;

    /**
     *  Get the "general" options (options that are standard in the meaning that they
     *  are generally available (unless specifically marked as unsupported by a given converter)
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return  array  Array of options
     */
    public function getGeneralOptions($imageType)
    {
        $isPng = ($imageType == 'png');

        /*
        return [
            //new IntegerOption('auto-limit-adjustment', 5, -100, 100),
            new BooleanOption('log-call-arguments', false),
            new BooleanOption('skip', false),
            new BooleanOption('use-nice', false),
            new ArrayOption('jpeg', []),
            new ArrayOption('png', [])
        ];*/

        $introMd = 'https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/' .
            'converting/introduction-for-converting.md';

        return OptionFactory::createOptions([
            ['encoding', 'string', [
                'title' => 'Encoding',
                'description' => 'Set encoding for the webp. ' .
                    'If you choose "auto", webp-convert will ' .
                    'convert to both lossy and lossless and pick the smallest result',
                'default' => 'auto',
                'enum' => ['auto', 'lossy', 'lossless'],
                'ui' => [
                    'component' => 'select',
                    'links' => [['Guide', $introMd . '#auto-selecting-between-losslesslossy-encoding']],
                ]
            ]],
            ['quality', 'int', [
                'title' => 'Quality (Lossy)',
                'description' =>
                    'Quality for lossy encoding. ' .
                    'In case you enable "auto-limit", you can consider this property a maximum quality.',
                'default' => ($isPng ? 85 : 75),
                'default-png' => 85,
                'default-jpeg' => 75,
                //'minimum' => 0,
                //'maximum' => 100,
                "oneOf" => [
                    ["type" => "number", "minimum" => 0, 'maximum' => 100],
                    ["type" => "string", "enum" => ["auto"]]
                ],
                'ui' => [
                    'component' => 'slider',
                    'display' => "option('encoding') != 'lossless'"
                ]
            ]],
            ['auto-limit', 'boolean', [
                'title' => 'Auto-limit',
                'description' =>
                    'Enable this option to prevent an unnecessarily high quality setting for low ' .
                    'quality jpegs. It works by adjusting quality setting down to the quality of the jpeg. ' .
                    'Converting ie a jpeg with quality:50 to ie quality:80 does not get you better quality ' .
                    'than converting it to quality:80, but it does get you a much bigger file - so you ' .
                    'really should enable this option.' . "\n\n" .
                    'The option is ignored for PNG and never adjusts quality up. ' . "\n\n" .
                    'The feature requires Imagick, ImageMagick or Gmagick in order to detect the quality of ' .
                    'the jpeg. ' . "\n\n" .
                    'PS: The "auto-limit" option is relative new. However, before this option, you could achieve ' .
                    'the same by setting quality to "auto" and specifying a "max-quality" and a "default-quality". ' .
                    'These are deprecated now, but still works.',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true,
                    'links' => [
                        [
                            'Guide',
                            $introMd . '#preventing-unnecessarily-high-quality-setting-for-low-quality-jpegs'
                        ]
                    ],
                    'display' => "option('encoding') != 'lossless'"
                ]
            ]],
            ['alpha-quality', 'int', [
                'title' => 'Alpha quality',
                'description' =>
                    'Quality of alpha channel. ' .
                    'Often, there is no need for high quality transparency layer and in some cases you ' .
                    'can tweak this all the way down to 10 and save a lot in file size. The option only ' .
                    'has effect with lossy encoding, and of course only on images with transparency.',
                'default' => 85,
                'minimum' => 0,
                'maximum' => 100,
                'ui' => [
                    'component' => 'slider',
                    'links' => [['Guide', $introMd . '#alpha-quality']],
                    'display' => "(option('encoding') != 'lossless') && (imageType!='jpeg')"
                ]
            ]],
            ['near-lossless', 'int', [
                'title' => '"Near lossless" quality',
                'description' =>
                    'This option allows you to get impressively better compression for lossless encoding, with ' .
                    'minimal impact on visual quality. The range is 0 (maximum preprocessing) to 100 (no ' .
                    'preprocessing). Read the guide for more info.',
                'default' => 60,
                'minimum' => 0,
                'maximum' => 100,
                'ui' => [
                    'component' => 'slider',
                    'links' => [['Guide', $introMd . '#near-lossless']],
                    'display' => "option('encoding') != 'lossy'"
                ]
            ]],
            ['metadata', 'string', [
                'title' => 'Metadata',
                'description' =>
                    'Determines which metadata that should be copied over to the webp. ' .
                    'Setting it to "all" preserves all metadata, setting it to "none" strips all metadata. ' .
                    '*cwebp* can take a comma-separated list of which kinds of metadata that should be copied ' .
                    '(ie "exif,icc"). *gd* will always remove all metadata and *ffmpeg* will always keep all ' .
                    'metadata. The rest can either strip all or keep all (they will keep all, unless the option ' .
                    'is set to *none*)',
                'default' => 'none',
                'ui' => [
                    'component' => 'multi-select',
                    'options' => ['all', 'none', 'exif', 'icc', 'xmp'],
                ]
                // TODO: set regex validation
            ]],
            ['method', 'int', [
                'title' => 'Reduction effort (0-6)',
                'description' =>
                    'Controls the trade off between encoding speed and the compressed file size and quality. ' .
                    'Possible values range from 0 to 6. 0 is fastest. 6 results in best quality and compression. ' .
                    'PS: The option corresponds to the "method" option in libwebp',
                'default' => 6,
                'minimum' => 0,
                'maximum' => 6,
                'ui' => [
                  'component' => 'slider',
                  'advanced' => true,
                ]
            ]],
            ['sharp-yuv', 'boolean', [
                'title' => 'Sharp YUV',
                'description' =>
                    'Better RGB->YUV color conversion (sharper and more accurate) at the expense of a little extra ' .
                    'conversion time.',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true,
                    'links' => [
                        ['Ctrl.blog', 'https://www.ctrl.blog/entry/webp-sharp-yuv.html'],
                    ],
                ]
            ]],
            ['auto-filter', 'boolean', [
                'title' => 'Auto-filter',
                'description' =>
                    'Turns auto-filter on. ' .
                    'This algorithm will spend additional time optimizing the filtering strength to reach a well-' .
                    'balanced quality. Unfortunately, it is extremely expensive in terms of computation. It takes ' .
                    'about 5-10 times longer to do a conversion. A 1MB picture which perhaps typically takes about ' .
                    '2 seconds to convert, will takes about 15 seconds to convert with auto-filter. ',
                'default' => false,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true,
                ]
            ]],
            ['low-memory', 'boolean', [
                'title' => 'Low memory',
                'description' =>
                    'Reduce memory usage of lossy encoding at the cost of ~30% longer encoding time and marginally ' .
                    'larger output size. Only effective when the *method* option is 3 or more. Read more in ' .
                    '[the docs](https://developers.google.com/speed/webp/docs/cwebp)',
                'default' => false,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true,
                    'display' => "(option('encoding') != 'lossless') && (option('method')>2)"
                ]
            ]],
            ['preset', 'string', [
                'title' => 'Preset',
                'description' =>
                    'Using a preset will set many of the other options to suit a particular type of ' .
                    'source material. It even overrides them. It does however not override the quality option. ' .
                    '"none" means that no preset will be set',
                'default' => 'none',
                'enum' => ['none', 'default', 'photo', 'picture', 'drawing', 'icon', 'text'],
                'ui' => [
                    'component' => 'select',
                    'advanced' => true,
                ]
            ]],
            ['size-in-percentage', 'int', ['default' => null, 'minimum' => 0, 'maximum' => 100, 'allow-null' => true]],
            ['skip', 'boolean', ['default' => false]],
            ['log-call-arguments', 'boolean', ['default' => false]],
            // TODO: use-nice should not be a "general" option
            //['use-nice', 'boolean', ['default' => false]],
            ['jpeg', 'array', ['default' => []]],
            ['png', 'array', ['default' => []]],

            // Deprecated options
            ['default-quality', 'int', [
                'default' => ($isPng ? 85 : 75),
                'minimum' => 0,
                'maximum' => 100,
                'deprecated' => true]
            ],
            ['max-quality', 'int', ['default' => 85, 'minimum' => 0, 'maximum' => 100, 'deprecated' => true]],
        ]);
    }

    /**
     *  Get the unique options for a converter
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return  array  Array of options
     */
    public function getUniqueOptions($imageType)
    {
        return [];
    }

    /**
     *  Create options.
     *
     *  The options created here will be available to all converters.
     *  Individual converters may add options by overriding this method.
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return void
     */
    protected function createOptions($imageType = 'png')
    {
        $this->options2 = new Options();
        $this->options2->addOptions(... $this->getGeneralOptions($imageType));
        $this->options2->addOptions(... $this->getUniqueOptions($imageType));
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
        $imageType = ($this->getMimeTypeOfSource() == 'image/png' ? 'png' : 'jpeg');
        $this->createOptions($imageType);

        $this->providedOptions = $providedOptions;

        if (isset($this->providedOptions['png'])) {
            if ($this->getMimeTypeOfSource() == 'image/png') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['png']);
//                $this->logLn(print_r($this->providedOptions, true));
                unset($this->providedOptions['png']);
            }
        }

        if (isset($this->providedOptions['jpeg'])) {
            if ($this->getMimeTypeOfSource() == 'image/jpeg') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['jpeg']);
                unset($this->providedOptions['jpeg']);
            }
        }

        // merge down converter-prefixed options
        $converterId = self::getConverterId();
        $strLen = strlen($converterId);
        foreach ($this->providedOptions as $optionKey => $optionValue) {
            if (substr($optionKey, 0, $strLen + 1) == ($converterId . '-')) {
                $this->providedOptions[substr($optionKey, $strLen + 1)] = $optionValue;
                unset($this->providedOptions[$optionKey]);
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
     *  @throws InvalidOptionTypeException   if an option have wrong type
     *  @throws InvalidOptionValueException  if an option value is out of range
     *  @throws ConversionSkippedException   if 'skip' option is set to true
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

    public function logOptions()
    {
        $this->logLn('');
        $this->logLn('Options:');
        $this->logLn('------------');

        $unsupported = $this->getUnsupportedDefaultOptions();
        $received = [];
        $implicitlySet = [];
        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if (in_array($id, [
                'png', 'jpeg', '_skip_input_check', '_suppress_success_message', 'skip', 'log_call_arguments'
            ])) {
                continue;
            }
            if ($option->isValueExplicitlySet()) {
                $received[] = $option;
            } else {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                    //$received[] = $option;
                } else {
                    if (!$option->isDeprecated()) {
                        $implicitlySet[] = $option;
                    }
                }
            }
        }

        if (count($received) > 0) {
            foreach ($received as $option) {
                $this->log('- ' . $option->getId() . ': ');
                if ($option instanceof GhostOption) {
                    $this->log('  (unknown to ' . $this->getConverterId() . ')', 'bold');
                    $this->logLn('');
                    continue;
                }
                $this->log($option->getValueForPrint());
                if ($option->isDeprecated()) {
                    $this->log(' (deprecated)', 'bold');
                }
                if (in_array($option->getId(), $unsupported)) {
                    if ($this instanceof Stack) {
                        //$this->log('  *(passed on)*');
                    } else {
                        $this->log(' (unsupported by ' . $this->getConverterId() . ')', 'bold');
                    }
                }
                $this->logLn('');
            }
            $this->logLn('');
            $this->logLn(
                'Note that these are the resulting options after merging down the "jpeg" and "png" options and any ' .
                'converter-prefixed options'
            );
        }

        if (count($implicitlySet) > 0) {
            $this->logLn('');
            $this->logLn('Defaults:');
            $this->logLn('------------');
            $this->logLn(
                'The following options was not set, so using the following defaults:'
            );
            foreach ($implicitlySet as $option) {
                $this->log('- ' . $option->getId() . ': ');
                $this->log($option->getValueForPrint());
                /*if ($option instanceof GhostOption) {
                    $this->log('  **(ghost)**');
                }*/
                $this->logLn('');
            }
        }
    }

    // to be overridden by converters
    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    public function getUnsupportedGeneralOptions()
    {
        return $this->getUnsupportedDefaultOptions();
    }

    /**
      * Get unique option definitions.
      *
      * Gets definitions of the converters "unique" options (that is, those options that
      * are not general). It was added in order to give GUI's a way to automatically adjust
      * their setting screens.
      *
      * @param  bool  $filterOutOptionsWithoutUI  If options without UI defined should be filtered out
      * @param  string   $imageType   (png | jpeg)   The image type - determines the defaults
      *
      * @return array  Array of options definitions - ready to be json encoded, or whatever
      */
    public function getUniqueOptionDefinitions($filterOutOptionsWithoutUI = true, $imageType = 'jpeg')
    {
        $uniqueOptions = new Options();
        //$uniqueOptions->addOptions(... $this->getUniqueOptions($imageType));
        foreach ($this->getUniqueOptions($imageType) as $uoption) {
            $uoption->setId(self::getConverterId() . '-' . $uoption->getId());
            $uniqueOptions->addOption($uoption);
        }

        $optionDefinitions = $uniqueOptions->getDefinitions();
        if ($filterOutOptionsWithoutUI) {
            $optionDefinitions = array_filter($optionDefinitions, function ($value) {
                return !is_null($value['ui']);
            });
            $optionDefinitions = array_values($optionDefinitions); // re-index
        }
        return $optionDefinitions;
    }

    /**
     * Get general option definitions.
     *
     * Gets definitions of all general options (not just the ones supported by current converter)
     * For UI's, as a way to automatically adjust their setting screens.
     *
     * @param  bool  $filterOutOptionsWithoutUI  If options without UI defined should be filtered out
     * @param  string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     * @return  array  Array of options definitions - ready to be json encoded, or whatever
     */
    public function getGeneralOptionDefinitions($filterOutOptionsWithoutUI = true, $imageType = 'jpeg')
    {
        $generalOptions = new Options();
        $generalOptions->addOptions(... $this->getGeneralOptions($imageType));
        //$generalOptions->setUI($this->getUIForGeneralOptions($imageType));
        $optionDefinitions = $generalOptions->getDefinitions();
        if ($filterOutOptionsWithoutUI) {
            $optionDefinitions = array_filter($optionDefinitions, function ($value) {
                return !is_null($value['ui']);
            });
            $optionDefinitions = array_values($optionDefinitions); // re-index
        }
        return $optionDefinitions;
    }

    public function getSupportedGeneralOptions($imageType = 'png')
    {
        $unsupportedGeneral = $this->getUnsupportedDefaultOptions();
        $generalOptionsArr = $this->getGeneralOptions($imageType);
        $supportedIds = [];
        foreach ($generalOptionsArr as $i => $option) {
            if (in_array($option->getId(), $unsupportedGeneral)) {
                unset($generalOptionsArr[$i]);
            }
        }
        return $generalOptionsArr;
    }

       /**
        *  Get general option definitions.
        *
        *  Gets definitions of the converters "general" options. (that is, those options that
        *  It was added in order to give GUI's a way to automatically adjust their setting screens.
        *
        *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
        *
        *  @return  array  Array of options definitions - ready to be json encoded, or whatever
        */
    public function getSupportedGeneralOptionDefinitions($imageType = 'png')
    {
        $generalOptions = new Options();
        $generalOptions->addOptions(... $this->getSupportedGeneralOptions($imageType));
        return $generalOptions->getDefinitions();
    }

    public function getSupportedGeneralOptionIds()
    {
        $supportedGeneralOptions = $this->getSupportedGeneralOptions();
        $supportedGeneralIds = [];
        foreach ($supportedGeneralOptions as $option) {
            $supportedGeneralIds[] = $option->getId();
        }
        return $supportedGeneralIds;
    }
}
