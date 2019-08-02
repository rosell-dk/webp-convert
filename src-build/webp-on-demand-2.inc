<?php 
?><?php

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

    /** @var boolean  Whether the value has been explicitly set */
    protected $isExplicitlySet = false;

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
     * Get to know if value has been explicitly set.
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

    public function getValueForPrint()
    {
        return print_r($this->getValue(), true);
    }
}

?><?php

// TODO:
// Read this: https://sourcemaking.com/design_patterns/strategy

namespace WebPConvert\Convert\Converters;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Helpers\MimeType;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Converters\BaseTraits\AutoQualityTrait;
use WebPConvert\Convert\Converters\BaseTraits\DestinationPreparationTrait;
use WebPConvert\Convert\Converters\BaseTraits\LoggerTrait;
use WebPConvert\Convert\Converters\BaseTraits\OptionsTrait;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
use WebPConvert\Exceptions\WebPConvertException;
use WebPConvert\Loggers\BaseLogger;

/**
 * Base for all converter classes.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class AbstractConverter
{
    use AutoQualityTrait;
    use OptionsTrait;
    use WarningLoggerTrait;
    use DestinationPreparationTrait;
    use LoggerTrait;

    /**
     * The actual conversion is be done by a concrete converter extending this class.
     *
     * At the stage this method is called, the abstract converter has taken preparational steps.
     * - It has created the destination folder (if neccesary)
     * - It has checked the input (valid mime type)
     * - It has set up an error handler, mostly in order to catch and log warnings during the doConvert fase
     *
     * Note: This method is not meant to be called from the outside. Use the static *convert* method for converting
     *       or, if you wish, create an instance with ::createInstance() and then call ::doConvert()
     *
     * @throws ConversionFailedException in case conversion failed in an antipiciated way (or subclass)
     * @throws \Exception in case conversion failed in an unantipiciated way
     */
    abstract protected function doActualConvert();

    /**
     * Whether or not the converter supports lossless encoding (even for jpegs)
     *
     * PS: Converters that supports lossless encoding all use the EncodingAutoTrait, which
     * overrides this function.
     *
     * @return  boolean  Whether the converter supports lossless encoding (even for jpegs).
     */
    public function supportsLossless()
    {
        return false;
    }

    /** @var string  The filename of the image to convert (complete path) */
    protected $source;

    /** @var string  Where to save the webp (complete path) */
    protected $destination;

    /**
     * Check basis operationality
     *
     * Converters may override this method for the purpose of performing basic operationaly checks. It is for
     * running general operation checks for a conversion method.
     * If some requirement is not met, it should throw a ConverterNotOperationalException (or subtype)
     *
     * The method is called internally right before calling doActualConvert() method.
     * - It SHOULD take options into account when relevant. For example, a missing api key for a
     *   cloud converter should be detected here
     * - It should NOT take the actual filename into consideration, as the purpose is *general*
     *   For that pupose, converters should override checkConvertability
     *   Also note that doConvert method is allowed to throw ConverterNotOperationalException too.
     *
     * @return  void
     */
    public function checkOperationality()
    {
    }

    /**
     * Converters may override this for the purpose of performing checks on the concrete file.
     *
     * This can for example be used for rejecting big uploads in cloud converters or rejecting unsupported
     * image types.
     *
     * @return  void
     */
    public function checkConvertability()
    {
    }

    /**
     * Constructor.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for conversion
     * @param   BaseLogger $logger (optional)
     */
    public function __construct($source, $destination, $options = [], $logger = null)
    {
        InputValidator::checkSourceAndDestination($source, $destination);

        $this->source = $source;
        $this->destination = $destination;

        $this->setLogger($logger);
        $this->setProvidedOptions($options);

        if (!isset($this->options['_skip_input_check'])) {
            $this->log('WebP Convert 2.1.4', 'italic');
            $this->logLn(' ignited.');
            $this->logLn('- PHP version: ' . phpversion());
            if (isset($_SERVER['SERVER_SOFTWARE'])) {
                $this->logLn('- Server software: ' . $_SERVER['SERVER_SOFTWARE']);
            }
            $this->logLn('');
            $this->logLn(self::getConverterDisplayName() . ' converter ignited');
        }
    }

    /**
     * Get source.
     *
     * @return string  The source.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get destination.
     *
     * @return string  The destination.
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Set destination.
     *
     * @param   string  $destination         path to destination
     * @return string  The destination.
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;
    }


    /**
     *  Get converter name for display (defaults to the class name (short)).
     *
     *  Converters can override this.
     *
     * @return string  A display name, ie "Gd"
     */
    protected static function getConverterDisplayName()
    {
        // https://stackoverflow.com/questions/19901850/how-do-i-get-an-objects-unqualified-short-class-name/25308464
        return substr(strrchr('\\' . static::class, '\\'), 1);
    }


    /**
     *  Get converter id (defaults to the class name lowercased)
     *
     *  Converters can override this.
     *
     * @return string  A display name, ie "Gd"
     */
    protected static function getConverterId()
    {
        return strtolower(self::getConverterDisplayName());
    }


    /**
     * Create an instance of this class
     *
     * @param  string  $source       The path to the file to convert
     * @param  string  $destination  The path to save the converted file to
     * @param  array   $options      (optional)
     * @param  \WebPConvert\Loggers\BaseLogger   $logger       (optional)
     *
     * @return static
     */
    public static function createInstance($source, $destination, $options = [], $logger = null)
    {

        return new static($source, $destination, $options, $logger);
    }

    protected function logReduction($source, $destination)
    {
        $sourceSize = filesize($source);
        $destSize = filesize($destination);
        $this->log(round(($sourceSize - $destSize)/$sourceSize * 100) . '% ');
        if ($sourceSize < 10000) {
            $this->logLn('(went from ' . strval($sourceSize) . ' bytes to '. strval($destSize) . ' bytes)');
        } else {
            $this->logLn('(went from ' . round($sourceSize/1024) . ' kb to ' . round($destSize/1024) . ' kb)');
        }
    }

    /**
     * Run conversion.
     *
     * @return void
     */
    private function doConvertImplementation()
    {
        $beginTime = microtime(true);

        $this->activateWarningLogger();

        $this->checkOptions();

        // Prepare destination folder
        $this->createWritableDestinationFolder();
        $this->removeExistingDestinationIfExists();

        if (!isset($this->options['_skip_input_check'])) {
            // Check that a file can be written to destination
            $this->checkDestinationWritable();
        }

        $this->checkOperationality();
        $this->checkConvertability();

        if ($this->options['log-call-arguments']) {
            $this->logOptions();
            $this->logLn('');
        }

        $this->runActualConvert();

        $source = $this->source;
        $destination = $this->destination;

        if (!@file_exists($destination)) {
            throw new ConversionFailedException('Destination file is not there: ' . $destination);
        } elseif (@filesize($destination) === 0) {
            unlink($destination);
            throw new ConversionFailedException('Destination file was completely empty');
        } else {
            if (!isset($this->options['_suppress_success_message'])) {
                $this->ln();
                $this->log('Converted image in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');

                $sourceSize = @filesize($source);
                if ($sourceSize !== false) {
                    $this->log(', reducing file size with ');
                    $this->logReduction($source, $destination);
                }
            }
        }

        $this->deactivateWarningLogger();
    }

    //private function logEx
    /**
     * Start conversion.
     *
     * Usually you would rather call the static convert method, but alternatively you can call
     * call ::createInstance to get an instance and then ::doConvert().
     *
     * @return void
     */
    public function doConvert()
    {
        try {
            //trigger_error('hello', E_USER_ERROR);
            $this->doConvertImplementation();
        } catch (WebPConvertException $e) {
            $this->logLn('');
            /*
            if (isset($e->description) && ($e->description != '')) {
                $this->log('Error: ' . $e->description . '. ', 'bold');
            } else {
                $this->log('Error: ', 'bold');
            }
            */
            $this->log('Error: ', 'bold');
            $this->logLn($e->getMessage(), 'bold');
            throw $e;
        } catch (\Exception $e) {
            $className = get_class($e);

            $classNameParts = explode("\\", $className);
            $shortClassName = array_pop($classNameParts);

            $this->logLn('');
            $this->logLn($shortClassName . ' thrown in ' . $e->getFile() . ':' . $e->getLine(), 'bold');
            $this->logLn('Message: "' . $e->getMessage() . '"', 'bold');
            //$this->logLn('Exception class: ' . $className);

            $this->logLn('Trace:');
            foreach ($e->getTrace() as $trace) {
                //$this->logLn(print_r($trace, true));
                if (isset($trace['file']) && isset($trace['line'])) {
                    $this->logLn(
                        $trace['file'] . ':' . $trace['line']
                    );
                }
            }
            throw $e;
        } /*catch (\Error $e) {
            $this->logLn('ERROR');
        }*/
    }

    /**
     * Runs the actual conversion (after setup and checks)
     * Simply calls the doActualConvert() of the actual converter.
     * However, in the EncodingAutoTrait, this method is overridden to make two conversions
     * and select the smallest.
     *
     * @return void
     */
    protected function runActualConvert()
    {
        $this->doActualConvert();
    }

    /**
     * Convert an image to webp.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for conversion
     * @param   BaseLogger $logger (optional)
     *
     * @throws  ConversionFailedException   in case conversion fails in an antipiciated way
     * @throws  \Exception   in case conversion fails in an unantipiciated way
     * @return  void
     */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        $c = self::createInstance($source, $destination, $options, $logger);
        $c->doConvert();
        //echo $instance->id;
    }

    /**
     * Get mime type for image (best guess).
     *
     * It falls back to using file extension. If that fails too, false is returned
     *
     * PS: Is it a security risk to fall back on file extension?
     * - By setting file extension to "jpg", one can lure our library into trying to convert a file, which isn't a jpg.
     * hmm, seems very unlikely, though not unthinkable that one of the converters could be exploited
     *
     * @return  string|false|null mimetype (if it is an image, and type could be determined / guessed),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    public function getMimeTypeOfSource()
    {
        return MimeType::getMimeTypeDetectionResult($this->source);
    }
}

?><?php

namespace WebPConvert\Convert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

/**
 *  ConversionFailedException is the base exception in the hierarchy for conversion errors.
 *
 *  Exception hierarchy from here:
 *
 *  WebpConvertException
 *      ConversionFailedException
 *          ConversionSkippedException
 *          ConverterNotOperationalException
 *              InvalidApiKeyException
 *              SystemRequirementsNotMetException
 *          FileSystemProblemsException
 *              CreateDestinationFileException
 *              CreateDestinationFolderException
 *          InvalidInputException
 *              ConverterNotFoundException
 *              InvalidImageTypeException
 *              InvalidOptionValueException
 *              TargetNotFoundException
 */
class ConversionFailedException extends WebPConvertException
{
    //public $description = 'Conversion failed';
    public $description = '';
}

?><?php

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
class ArrayOption extends Option
{

    public function check()
    {
        $this->checkType('array');
    }

    public function getValueForPrint()
    {
        if (count($this->getValue()) == 0) {
            return '(empty array)';
        } else {
            return parent::getValueForPrint();
        }
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Boolean option
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class BooleanOption extends Option
{

    public function check()
    {
        $this->checkType('boolean');
    }

    public function getValueForPrint()
    {
        return ($this->getValue() === true ? 'true' : 'false');
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Ghost option
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class GhostOption extends Option
{

    public function getValueForPrint()
    {
        return '(not defined for this converter)';
    }
}

?><?php

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
class IntegerOption extends Option
{

    protected $minValue;
    protected $maxValue;

    /**
     * Constructor.
     *
     * @param   string   $id              id of the option
     * @param   integer  $defaultValue    default value for the option
     * @throws  InvalidOptionValueException  if the default value cannot pass the check
     * @return  void
     */
    public function __construct($id, $defaultValue, $minValue = null, $maxValue = null)
    {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        parent::__construct($id, $defaultValue);
    }

    protected function checkMin()
    {
        if (!is_null($this->minValue) && $this->getValue() < $this->minValue) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be set to minimum ' . $this->minValue . '. ' .
                'It was however set to: ' . $this->getValue()
            );
        }
    }

    protected function checkMax()
    {
        if (!is_null($this->maxValue) && $this->getValue() > $this->maxValue) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be set to max ' . $this->maxValue . '. ' .
                'It was however set to: ' . $this->getValue()
            );
        }
    }

    protected function checkMinMax()
    {
        $this->checkMin();
        $this->checkMax();
    }

    public function check()
    {
        $this->checkType('integer');
        $this->checkMinMax();
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class IntegerOrNullOption extends IntegerOption
{

    public function __construct($id, $defaultValue, $minValue = null, $maxValue = null)
    {
        parent::__construct($id, $defaultValue, $minValue, $maxValue);
    }

    public function check()
    {
        $this->checkMinMax();

        $valueType = gettype($this->getValue());
        if (!in_array($valueType, ['integer', 'NULL'])) {
            throw new InvalidOptionValueException(
                'The "' . $this->id . '" option must be either integer or NULL. ' .
                    'You however provided a value of type: ' . $valueType
            );
        }
    }

    public function getValueForPrint()
    {
        if (gettype($this->getValue() == 'NULL')) {
            return 'null (not set)';
        }
        return parent::getValueForPrint();
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\StringOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Metadata option. A Comma-separated list ('all', 'none', 'exif', 'icc', 'xmp')
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class MetadataOption extends StringOption
{

    public function __construct($id, $defaultValue)
    {
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        parent::check();

        $value = $this->getValue();

        if (($value == 'all') || ($value == 'none')) {
            return;
        }

        foreach (explode(',', $value) as $item) {
            if (!in_array($value, ['exif', 'icc', 'xmp'])) {
                throw new InvalidOptionValueException(
                    '"metadata" option must be "all", "none" or a comma-separated list of "exif", "icc" or "xmp". ' .
                    'It was however set to: "' . $value . '"'
                );
            }
        }

        //$this->checkType('string');
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\OptionNotFoundException;

/**
 * Handles a collection of options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Options
{

    /** @var  array  A map of options, keyed by their id */
    private $options = [];

    /**
     * Add option.
     *
     * @param  Option  $option  The option object to add to collection.
     * @return void
     */
    public function addOption($option)
    {
        $this->options[$option->getId()] = $option;
    }

    /**
     * Add options.
     *
     * Conveniently add several options in one call.
     *
     * @param  Option[]  ...$options  Array of options objects to add
     * @return void
     */
    public function addOptions(...$options)
    {
        foreach ($options as $option) {
            $this->addOption($option);
        }
    }

    /**
     * Set the value of an option.
     *
     * @param  string  $id      Id of the option
     * @param  mixed   $value   Value of the option
     * @return void
     */
    public function setOption($id, $value)
    {
        if (!isset($this->options[$id])) {
            throw new OptionNotFoundException(
                'Could not set option. There is no option called "' . $id . '" in the collection.'
            );
        }
        $option = $this->options[$id];
        $option->setValue($value);
    }

    /**
     * Set option, or create a new, if no such option exists.
     *
     * @param  string  $id  Id of option to set/create
     * @param  mixed  $value  Value of option
     * @return void
     */
    public function setOrCreateOption($id, $value)
    {
        if (!isset($this->options[$id])) {
            $newOption = new GhostOption($id, null);
            $newOption->setValue($value);
            //$newOption = new Option($id, $value);
            $this->addOption($newOption);
        } else {
            $this->setOption($id, $value);
        }
    }

    /**
     * Get the value of an option in the collection - by id.
     *
     * @param  string  $id      Id of the option to get
     * @throws  OptionNotFoundException  if the option is not in the collection
     * @return mixed  The value of the option
     */
    public function getOption($id)
    {
        if (!isset($this->options[$id])) {
            throw new OptionNotFoundException(
                'There is no option called "' . $id . '" in the collection.'
            );
        }
        $option = $this->options[$id];
        return $option->getValue();
    }

    /**
     * Return map of option objects.
     *
     * @return array  map of option objects
     */
    public function getOptionsMap()
    {
        return $this->options;
    }

    /**
     * Return flat associative array of options.
     *
     * @return array  associative array of options
     */
    public function getOptions()
    {
        $values = [];
        foreach ($this->options as $id => $option) {
            $values[$id] = $option->getValue();
        }
        return $values;
    }

    /**
     * Check all options in the collection.
     */
    public function check()
    {
        foreach ($this->options as $id => $option) {
            $option->check();
        }
    }
}

?><?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Quality option.
 *
 * Quality can be a number between 0-100 or "auto"
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class QualityOption extends Option
{

    public function __construct($id, $defaultValue)
    {
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        $value = $this->getValue();
        if (gettype($value) == 'string') {
            if ($value != 'auto') {
                throw new InvalidOptionValueException(
                    'The "quality" option must be either "auto" or a number between 0-100. ' .
                    'A string, different from "auto" was given'
                );
            }
        } elseif (gettype($value) == 'integer') {
            if (($value < 0) || ($value > 100)) {
                throw new InvalidOptionValueException(
                    'The "quality" option must be either "auto" or a number between 0-100. ' .
                        'The number you provided (' . strval($value) . ') is out of range.'
                );
            }
        } else {
            throw new InvalidOptionValueException(
                'The "quality" option must be either "auto" or an integer. ' .
                    'You however provided a value of type: ' . gettype($value)
            );
        }
    }

    public function getValueForPrint()
    {
        if (gettype($this->getValue()) == 'string') {
            return '"' . $this->getValue() . '"';
        }
        return $this->getValue();
    }
}

?><?php

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
class SensitiveArrayOption extends ArrayOption
{

    public function check()
    {
        parent::check();
    }

    public function getValueForPrint()
    {
        if (count($this->getValue()) == 0) {
            return '(empty array)';
        } else {
            return '(array of ' . count($this->getValue()) . ' items)';
        }
        //return '*****';
    }
}

?><?php

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

    public function __construct($id, $defaultValue, $allowedValues = null)
    {
        parent::__construct($id, $defaultValue, $allowedValues);
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
}

?><?php

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

    public $allowedValues;

    public function __construct($id, $defaultValue, $allowedValues = null)
    {
        $this->allowedValues = $allowedValues;
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        $this->checkType('string');

        if (!is_null($this->allowedValues) && (!in_array($this->getValue(), $this->allowedValues))) {
            throw new InvalidOptionValueException(
                '"' . $this->id . '" option must be on of these values: ' .
                '[' . implode(', ', $this->allowedValues) . ']. ' .
                'It was however set to: "' . $this->getValue() . '"'
            );
        }
    }

    public function getValueForPrint()
    {
        return '"' . $this->getValue() . '"';
    }
}

?><?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Helpers\JpegQualityDetector;

/**
 * Trait for handling the "quality:auto" option.
 *
 * This trait is only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning auto quality.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait AutoQualityTrait
{

    abstract public function logLn($msg, $style = '');
    abstract public function getMimeTypeOfSource();

    /** @var boolean  Whether the quality option has been processed or not */
    private $processed = false;

    /** @var boolean  Whether the quality of the source could be detected or not (set upon processing) */
    private $qualityCouldNotBeDetected = false;

    /** @var integer  The calculated quality (set upon processing - on successful detection) */
    private $calculatedQuality;


    /**
     *  Determine if quality detection is required but failing.
     *
     *  It is considered "required" when:
     *  - Mime type is "image/jpeg"
     *  - Quality is set to "auto"
     *
     *  If quality option hasn't been proccessed yet, it is triggered.
     *
     *  @return  boolean
     */
    public function isQualityDetectionRequiredButFailing()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->qualityCouldNotBeDetected;
    }

    /**
     * Get calculated quality.
     *
     * If the "quality" option is a number, that number is returned.
     * If mime type of source is something else than "image/jpeg", the "default-quality" option is returned
     * If quality is "auto" and source is a jpeg image, it will be attempted to detect jpeg quality.
     * In case of failure, the value of the "default-quality" option is returned.
     * In case of success, the detected quality is returned, or the value of the "max-quality" if that is lower.
     *
     *  @return  int
     */
    public function getCalculatedQuality()
    {
        $this->processQualityOptionIfNotAlready();
        return $this->calculatedQuality;
    }

    /**
     * Process the quality option if it is not already processed.
     *
     * @return void
     */
    private function processQualityOptionIfNotAlready()
    {
        if (!$this->processed) {
            $this->processed = true;
            $this->processQualityOption();
        }
    }

    /**
     * Process the quality option.
     *
     * Sets the private property "calculatedQuality" according to the description for the getCalculatedQuality
     * function.
     * In case quality detection was attempted and failed, the private property "qualityCouldNotBeDetected" is set
     * to true. This is used by the "isQualityDetectionRequiredButFailing" (and documented there too).
     *
     * @return void
     */
    private function processQualityOption()
    {
        $options = $this->options;
        $source = $this->source;

        $q = $options['quality'];
        if ($q == 'auto') {
            if (($this->/** @scrutinizer ignore-call */getMimeTypeOfSource() == 'image/jpeg')) {
                $q = JpegQualityDetector::detectQualityOfJpg($source);
                if (is_null($q)) {
                    $q = $options['default-quality'];
                    $this->/** @scrutinizer ignore-call */logLn(
                        'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                        ' - Using default instead (' . $options['default-quality'] . ').'
                    );

                    $this->qualityCouldNotBeDetected = true;
                } else {
                    if ($q > $options['max-quality']) {
                        $this->logLn(
                            'Quality of source is ' . $q . '. ' .
                            'This is higher than max-quality, so using max-quality instead (' .
                                $options['max-quality'] . ')'
                        );
                    } else {
                        $this->logLn('Quality set to same as source: ' . $q);
                    }
                }
                $q = min($q, $options['max-quality']);
            } else {
                //$q = $options['default-quality'];
                $q = min($options['default-quality'], $options['max-quality']);
                $this->logLn('Quality: ' . $q . '. ');
            }
        } else {
            $this->logLn(
                'Quality: ' . $q . '. '
            );
            if (($this->getMimeTypeOfSource() == 'image/jpeg')) {
                $this->logLn(
                    'Consider setting quality to "auto" instead. It is generally a better idea'
                );
            }
        }
        $this->calculatedQuality = $q;
    }
}

?><?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;

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
trait DestinationPreparationTrait
{

    abstract public function getDestination();
    abstract public function logLn($msg, $style = '');

    /**
     * Create writable folder in provided path (if it does not exist already)
     *
     * @throws CreateDestinationFolderException  if folder cannot be removed
     * @return void
     */
    private function createWritableDestinationFolder()
    {
        $destination = $this->getDestination();

        $folder = dirname($destination);
        if (!file_exists($folder)) {
            $this->logLn('Destination folder does not exist. Creating folder: ' . $folder);
            // TODO: what if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir

            // Trying to create the given folder (recursively)
            if (!mkdir($folder, 0777, true)) {
                throw new CreateDestinationFolderException(
                    'Failed creating folder. Check the permissions!',
                    'Failed creating folder: ' . $folder . '. Check permissions!'
                );
            }
        }
    }

    /**
     * Check that we can write file at destination.
     *
     * It is assumed that the folder already exists (that ::createWritableDestinationFolder() was called first)
     *
     * @throws CreateDestinationFileException  if file cannot be created at destination
     * @return void
     */
    private function checkDestinationWritable()
    {
        $destination = $this->getDestination();
        $dirName = dirname($destination);

        if (@is_writable($dirName) && @is_executable($dirName)) {
            // all is well
            return;
        }

        // The above might fail on Windows, even though dir is writable
        // So, to be absolute sure that we cannot write, we make an actual write test (writing a dummy file)
        // No harm in doing that for non-Windows systems either.
        if (file_put_contents($destination, 'dummy') !== false) {
            // all is well, after all
            unlink($destination);
            return;
        }

        throw new CreateDestinationFileException(
            'Cannot create file: ' . basename($destination) . ' in dir:' . dirname($destination)
        );
    }

    /**
     * Remove existing destination.
     *
     * @throws CreateDestinationFileException  if file cannot be removed
     * @return void
     */
    private function removeExistingDestinationIfExists()
    {
        $destination = $this->getDestination();
        if (file_exists($destination)) {
            // A file already exists in this folder...
            // We delete it, to make way for a new webp
            if (!unlink($destination)) {
                throw new CreateDestinationFileException(
                    'Existing file cannot be removed: ' . basename($destination)
                );
            }
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters\BaseTraits;

/**
 * Trait for providing logging capabilities.
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning logging.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait LoggerTrait
{

    /** @var \WebPConvert\Loggers\BaseLogger  The logger (or null if not set) */
    protected $logger;

    /**
     * Set logger
     *
     * @param   \WebPConvert\Loggers\BaseLogger $logger (optional)  $logger
     * @return  void
     */
    public function setLogger($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Write a line to the logger.
     *
     * @param  string  $msg    The line to write.
     * @param  string  $style  (optional) Ie "italic" or "bold"
     * @return void
     */
    protected function logLn($msg, $style = '')
    {
        if (isset($this->logger)) {
            $this->logger->logLn($msg, $style);
        }
    }

    /**
     * New line
     *
     * @return  void
     */
    protected function ln()
    {
        if (isset($this->logger)) {
            $this->logger->ln();
        }
    }

    /**
     * Write to the logger, without newline
     *
     * @param  string  $msg    What to write.
     * @param  string  $style  (optional) Ie "italic" or "bold"
     * @return void
     */
    protected function log($msg, $style = '')
    {
        if (isset($this->logger)) {
            $this->logger->log($msg, $style);
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;

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

    /** @var array  Provided conversion options */
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    protected $options;

    /** @var Options  */
    protected $options2;


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
            new BooleanOption('use-nice', false),
            new ArrayOption('jpeg', []),
            new ArrayOption('png', [])
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
        $this->logLn(
            'The following options have been set explicitly. ' .
            'Note: it is the resulting options after merging down the "jpeg" and "png" options and any ' .
            'converter-prefixed options.'
        );
        $this->logLn('- source: ' . $this->source);
        $this->logLn('- destination: ' . $this->destination);

        $unsupported = $this->getUnsupportedDefaultOptions();
        //$this->logLn('Unsupported:' . print_r($this->getUnsupportedDefaultOptions(), true));
        $ignored = [];
        $implicit = [];
        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if (($id == 'png') || ($id == 'jpeg')) {
                continue;
            }
            if ($option->isValueExplicitlySet()) {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                    //$this->log(' (note: this option is ignored by this converter)');
                    if (($id != '_skip_input_check') && ($id != '_suppress_success_message')) {
                        $ignored[] = $option;
                    }
                } else {
                    $this->log('- ' . $id . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                } else {
                    $implicit[] = $option;
                }
            }
        }

        if (count($implicit) > 0) {
            $this->logLn('');
            $this->logLn(
                'The following options have not been explicitly set, so using the following defaults:'
            );
            foreach ($implicit as $option) {
                $this->log('- ' . $option->getId() . ': ');
                $this->log($option->getValueForPrint());
                $this->logLn('');
            }
        }
        if (count($ignored) > 0) {
            $this->logLn('');
            if ($this instanceof Stack) {
                $this->logLn(
                    'The following options were supplied and are passed on to the converters in the stack:'
                );
                foreach ($ignored as $option) {
                    $this->log('- ' . $option->getId() . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                $this->logLn(
                    'The following options were supplied but are ignored because they are not supported by this ' .
                        'converter:'
                );
                foreach ($ignored as $option) {
                    $this->logLn('- ' . $option->getId());
                }
            }
        }
        $this->logLn('------------');
    }

    // to be overridden by converters
    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }
}

?><?php

namespace WebPConvert\Convert\Converters\BaseTraits;

/**
 * Trait for handling warnings (by logging them)
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait WarningLoggerTrait
{
    abstract protected function logLn($msg, $style = '');

    /** @var string|array|null  Previous error handler (stored in order to be able pass warnings on) */
    private $previousErrorHandler;

    /**
     *  Handle warnings and notices during conversion by logging them and passing them on.
     *
     *  The function is a callback used with "set_error_handler".
     *  It is declared public because it needs to be accessible from the point where the warning happened.
     *
     *  @param  integer  $errno
     *  @param  string   $errstr
     *  @param  string   $errfile
     *  @param  integer  $errline
     *
     *  @return false|null
     */
    public function warningHandler($errno, $errstr, $errfile, $errline)
    {
        /*
        We do NOT do the following (even though it is generally recommended):

        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        - Because we want to log all warnings and errors (also the ones that was suppressed with @)
        https://secure.php.net/manual/en/language.operators.errorcontrol.php
        */

        $errorTypes = [
            E_WARNING =>             "Warning",
            E_NOTICE =>              "Notice",
            E_STRICT =>              "Strict Notice",
            E_DEPRECATED =>          "Deprecated",
            E_USER_DEPRECATED =>     "User Deprecated",

            /*
            The following can never be catched by a custom error handler:
            E_PARSE, E_ERROR, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING

            We do do not currently trigger the following:
            E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE

            But we may want to do that at some point, like this:
            trigger_error('Your version of Gd is very old', E_USER_WARNING);
            in that case, remember to add them to this array
            */
        ];

        if (isset($errorTypes[$errno])) {
            $errType = $errorTypes[$errno];
        } else {
            $errType = "Unknown error/warning/notice ($errno)";
        }

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION .
            ' (' . PHP_OS . ')';
        $this->logLn('');
        $this->logLn($msg, 'italic');
        $this->logLn('');

        if (!is_null($this->previousErrorHandler)) {
            // If previousErrorHandler is this very error handler, exit to avoid recursion
            // (this could happen if ::activateWarningLogger() were called twice)
            if (is_array($this->previousErrorHandler) &&
                isset($this->previousErrorHandler[0]) &&
                ($this->previousErrorHandler[0] == $this)
            ) {
                return false;
            } else {
                return call_user_func($this->previousErrorHandler, $errno, $errstr, $errfile, $errline);
            }
        } else {
            return false;
        }
    }

    /**
     *  Activate warning logger.
     *
     *  Sets the error handler and stores the previous so our error handler can bubble up warnings
     *
     *  @return  void
     */
    protected function activateWarningLogger()
    {
        $this->previousErrorHandler = set_error_handler(
            array($this, "warningHandler"),
            E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE
        );
    }

    /**
     *  Deactivate warning logger.
     *
     *  Restores the previous error handler.
     *
     *  @return  void
     */
    protected function deactivateWarningLogger()
    {
        restore_error_handler();
    }
}

?><?php

namespace WebPConvert\Convert\Converters\ConverterTraits;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Helpers\PhpIniSizes;

/**
 * Trait for converters that works by uploading to a cloud service.
 *
 * The trait adds a method for checking against upload limits.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait CloudConverterTrait
{

    /**
     * Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini.
     *
     * @param  string  $iniSettingId  Id of ini setting (ie "upload_max_filesize")
     *
     * @throws  ConversionFailedException  if filesize is larger than the ini setting
     * @return  void
     */
    private function checkFileSizeVsIniSetting($iniSettingId)
    {
        $fileSize = @filesize($this->source);
        if ($fileSize === false) {
            return;
        }
        $sizeInIni = PhpIniSizes::getIniBytes($iniSettingId);
        if ($sizeInIni === false) {
            // Not sure if we should throw an exception here, or not...
            return;
        }
        if ($sizeInIni < $fileSize) {
            throw new ConversionFailedException(
                'File is larger than your ' . $iniSettingId . ' (set in your php.ini). File size:' .
                    round($fileSize/1024) . ' kb. ' .
                    $iniSettingId . ' in php.ini: ' . ini_get($iniSettingId) .
                    ' (parsed as ' . round($sizeInIni/1024) . ' kb)'
            );
        }
    }

    /**
     * Check convertability of cloud converters (that file is not bigger than limits set in php.ini).
     *
     * Performs the same as ::Convertability(). It is here so converters that overrides the
     * ::Convertability() still has a chance to do the checks.
     *
     * @throws  ConversionFailedException  if filesize is larger than "upload_max_filesize" or "post_max_size"
     * @return  void
     */
    public function checkConvertabilityCloudConverterTrait()
    {
        $this->checkFileSizeVsIniSetting('upload_max_filesize');
        $this->checkFileSizeVsIniSetting('post_max_size');
    }

    /**
     * Check convertability of cloud converters (file upload limits).
     */
    public function checkConvertability()
    {
        $this->checkConvertabilityCloudConverterTrait();
    }
}

?><?php

namespace WebPConvert\Convert\Converters\ConverterTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\AbstractConverter;

/**
 * Trait for converters that works by uploading to a cloud service.
 *
 * The trait adds a method for checking against upload limits.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait CurlTrait
{

    /**
     * Check basis operationality for converters relying on curl.
     *
     * Performs the same as ::checkOperationality(). It is here so converters that overrides the
     * ::checkOperationality() still has a chance to do the checks.
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationalityForCurlTrait()
    {
        if (!extension_loaded('curl')) {
            throw new SystemRequirementsNotMetException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new SystemRequirementsNotMetException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new SystemRequirementsNotMetException(
                'Required curl_file_create() function is not available (requires PHP > 5.5).'
            );
        }
    }

    /**
     * Check basis operationality for converters relying on curl
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationality()
    {
        $this->checkOperationalityForCurlTrait();
    }

    /**
     * Init curl.
     *
     * @throws  SystemRequirementsNotMetException  if curl could not be initialized
     * @return  resource  curl handle
     */
    protected static function initCurl()
    {
        // Get curl handle
        $ch = curl_init();
        if ($ch === false) {
            throw new SystemRequirementsNotMetException('Could not initialise cURL.');
        }
        return $ch;
    }
}

?><?php

//namespace WebPConvert\Convert\Converters\BaseTraits;
namespace WebPConvert\Convert\Converters\ConverterTraits;

/**
 * Trait for converters that supports lossless encoding and thus the "lossless:auto" option.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait EncodingAutoTrait
{

    abstract protected function doActualConvert();
    abstract public function getSource();
    abstract public function getDestination();
    abstract public function setDestination($destination);
    abstract public function getOptions();
    abstract protected function setOption($optionName, $optionValue);
    abstract protected function logLn($msg, $style = '');
    abstract protected function log($msg, $style = '');
    abstract protected function ln();
    abstract protected function logReduction($source, $destination);

    public function supportsLossless()
    {
        return true;
    }

    /** Default is to not pass "lossless:auto" on, but implement it.
     *
     *  The Stack converter passes it on (it does not even use this trait)
     *  WPC currently implements it, but this might be configurable in the future.
     *
     */
    public function passOnEncodingAuto()
    {
        return false;
    }

    private function convertTwoAndSelectSmallest()
    {
        $destination = $this->getDestination();
        $destinationLossless =  $destination . '.lossless.webp';
        $destinationLossy =  $destination . '.lossy.webp';

        $this->logLn(
            'Encoding is set to auto - converting to both lossless and lossy and selecting the smallest file'
        );

        $this->ln();
        $this->logLn('Converting to lossy');
        $this->setDestination($destinationLossy);
        $this->setOption('encoding', 'lossy');
        $this->doActualConvert();
        $this->log('Reduction: ');
        $this->logReduction($this->getSource(), $destinationLossy);
        $this->ln();

        $this->logLn('Converting to lossless');
        $this->setDestination($destinationLossless);
        $this->setOption('encoding', 'lossless');
        $this->doActualConvert();
        $this->log('Reduction: ');
        $this->logReduction($this->getSource(), $destinationLossless);
        $this->ln();

        if (filesize($destinationLossless) > filesize($destinationLossy)) {
            $this->logLn('Picking lossy');
            unlink($destinationLossless);
            rename($destinationLossy, $destination);
        } else {
            $this->logLn('Picking lossless');
            unlink($destinationLossy);
            rename($destinationLossless, $destination);
        }
        $this->setDestination($destination);
        $this->setOption('encoding', 'auto');
    }

    protected function runActualConvert()
    {
        if (!$this->passOnEncodingAuto() && ($this->getOptions()['encoding'] == 'auto') && $this->supportsLossless()) {
            $this->convertTwoAndSelectSmallest();
        } else {
            $this->doActualConvert();
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters\ConverterTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

/**
 * Trait for converters that uses exec()
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait ExecTrait
{

    abstract protected function logLn($msg, $style = '');

    /**
     * Helper function for examining if "nice" command is available
     *
     * @return  boolean  true if nice is available
     */
    protected static function hasNiceSupport()
    {
        exec("nice 2>&1", $niceOutput);

        if (is_array($niceOutput) && isset($niceOutput[0])) {
            if (preg_match('/usage/', $niceOutput[0]) || (preg_match('/^\d+$/', $niceOutput[0]))) {
                /*
                 * Nice is available - default niceness (+10)
                 * https://www.lifewire.com/uses-of-commands-nice-renice-2201087
                 * https://www.computerhope.com/unix/unice.htm
                 */

                return true;
            }
            return false;
        }
    }

    /**
     * Logs output from the exec call.
     *
     * @param  array  $output
     *
     * @return  void
     */
    protected function logExecOutput($output)
    {
        if (is_array($output) && count($output) > 0) {
            $this->logLn('');
            $this->logLn('Output:', 'italic');
            foreach ($output as $line) {
                $this->logLn(print_r($line, true));
            }
            $this->logLn('');
        }
    }

    /**
     * Check basic operationality of exec converters (that the "exec" function is available)
     *
     * @throws  WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationalityExecTrait()
    {
        if (!function_exists('exec')) {
            throw new SystemRequirementsNotMetException('exec() is not enabled.');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\SensitiveStringOption;
use WebPConvert\Options\StringOption;

/**
 * Convert images to webp by calling cwebp binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Cwebp extends AbstractConverter
{

    use EncodingAutoTrait;
    use ExecTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new StringOption('command-line-options', ''),
            new SensitiveStringOption('rel-path-to-precompiled-binaries', './Binaries'),
            new BooleanOption('try-common-system-paths', true),
            new BooleanOption('try-supplied-binary-for-os', true)
        );
    }

    // System paths to look for cwebp binary
    private static $cwebpDefaultPaths = [
        'cwebp',
        '/usr/bin/cwebp',
        '/usr/local/bin/cwebp',
        '/usr/gnu/bin/cwebp',
        '/usr/syno/bin/cwebp'
    ];

    // OS-specific binaries included in this library, along with hashes
    // If other binaries are going to be added, notice that the first argument is what PHP_OS returns.
    // (possible values, see here: https://stackoverflow.com/questions/738823/possible-values-for-php-os)
    // Got the precompiled binaries here: https://developers.google.com/speed/webp/docs/precompiled
    private static $suppliedBinariesInfo = [
        'WINNT' => [['cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873']],
        'Darwin' => [['cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379']],
        'SunOS' => [['cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f']],
        'FreeBSD' => [['cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573']],
        'Linux' => [
            // Dynamically linked executable.
            // It seems it is slightly faster than the statically linked
            ['cwebp-linux-1.0.2-shared', 'd6142e9da2f1cab541de10a31527c597225fff5644e66e31d62bb391c41bfbf4'],

            // Statically linked executable
            // It may be that it on some systems works, where the dynamically linked does not (see #196)
            ['cwebp-linux-1.0.2-static', 'a67092563d9de0fbced7dde61b521d60d10c0ad613327a42a81845aefa612b29'],

            // Old executable for systems where both of the above fails
            ['cwebp-linux-0.6.1', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568'],
        ]
    ];

    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        $options = $this->options;
        if (!$options['try-supplied-binary-for-os'] && !$options['try-common-system-paths']) {
            throw new ConverterNotOperationalException(
                'Configured to neither look for cweb binaries in common system locations, ' .
                'nor to use one of the supplied precompiled binaries. But these are the only ways ' .
                'this converter can convert images. No conversion can be made!'
            );
        }
    }

    private function executeBinary($binary, $commandOptions, $useNice)
    {
        //$version = $this->detectVersion($binary);

        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions;

        //$logger->logLn('command options:' . $commandOptions);
        $this->logLn('Trying to convert by executing the following command:');
        $this->logLn($command);
        exec($command, $output, $returnCode);
        $this->logExecOutput($output);
        /*
        if ($returnCode == 255) {
            if (isset($output[0])) {
                // Could be an error like 'Error! Cannot open output file' or 'Error! ...preset... '
                $this->logLn(print_r($output[0], true));
            }
        }*/
        //$logger->logLn(self::msgForExitCode($returnCode));
        return intval($returnCode);
    }

    /**
     *  Use "escapeshellarg()" on all arguments in a commandline string of options
     *
     *  For example, passing '-sharpness 5 -crop 10 10 40 40 -low_memory' will result in:
     *  [
     *    "-sharpness '5'"
     *    "-crop '10' '10' '40' '40'"
     *    "-low_memory"
     *  ]
     * @param  string $commandLineOptions  string which can contain multiple commandline options
     * @return array  Array of command options
     */
    private static function escapeShellArgOnCommandLineOptions($commandLineOptions)
    {
        if (!ctype_print($commandLineOptions)) {
            throw new ConversionFailedException(
                'Non-printable characters are not allowed in the extra command line options'
            );
        }

        if (preg_match('#[^a-zA-Z0-9_\s\-]#', $commandLineOptions)) {
            throw new ConversionFailedException('The extra command line options contains inacceptable characters');
        }

        $cmdOptions = [];
        $arr = explode(' -', ' ' . $commandLineOptions);
        foreach ($arr as $cmdOption) {
            $pos = strpos($cmdOption, ' ');
            $cName = '';
            if (!$pos) {
                $cName = $cmdOption;
                if ($cName == '') {
                    continue;
                }
                $cmdOptions[] = '-' . $cName;
            } else {
                $cName = substr($cmdOption, 0, $pos);
                $cValues = substr($cmdOption, $pos + 1);
                $cValuesArr = explode(' ', $cValues);
                foreach ($cValuesArr as &$cArg) {
                    $cArg = escapeshellarg($cArg);
                }
                $cValues = implode(' ', $cValuesArr);
                $cmdOptions[] = '-' . $cName . ' ' . $cValues;
            }
        }
        return $cmdOptions;
    }

    /**
     * Build command line options for a given version of cwebp.
     *
     * The "-near_lossless" param is not supported on older versions of cwebp, so skip on those.
     *
     * @param  string $version  Version of cwebp.
     * @return string
     */
    private function createCommandLineOptions($version)
    {

        $this->logLn('Creating command line options for version: ' . $version);

        // we only need two decimal places for version.
        // convert to number to make it easier to compare
        $version = preg_match('#^\d+\.\d+#', $version, $matches);
        $versionNum = 0;
        if (isset($matches[0])) {
            $versionNum = floatval($matches[0]);
        } else {
            $this->logLn(
                'Could not extract version number from the following version string: ' . $version,
                'bold'
            );
        }

        //$this->logLn('version:' . strval($versionNum));

        $options = $this->options;

        $cmdOptions = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        if ($versionNum >= 0.3) {
            $cmdOptions[] = '-metadata ' . $options['metadata'];
        }

        // preset. Appears first in the list as recommended in the docs
        if (!is_null($options['preset'])) {
            if ($options['preset'] != 'none') {
                $cmdOptions[] = '-preset ' . $options['preset'];
            }
        }

        // Size
        $addedSizeOption = false;
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource = filesize($this->source);
            if ($sizeSource !== false) {
                $targetSize = floor($sizeSource * $options['size-in-percentage'] / 100);
                $cmdOptions[] = '-size ' . $targetSize;
                $addedSizeOption = true;
            }
        }

        // quality
        if (!$addedSizeOption) {
            $cmdOptions[] = '-q ' . $this->getCalculatedQuality();
        }

        // alpha-quality
        if ($this->options['alpha-quality'] !== 100) {
            $cmdOptions[] = '-alpha_q ' . escapeshellarg($this->options['alpha-quality']);
        }

        // Losless PNG conversion
        if ($options['encoding'] == 'lossless') {
            // No need to add -lossless when near-lossless is used (on version >= 0.5)
            if (($options['near-lossless'] === 100) || ($versionNum < 0.5)) {
                $cmdOptions[] = '-lossless';
            }
        }

        // Near-lossles
        if ($options['near-lossless'] !== 100) {
            if ($versionNum < 0.5) {
                $this->logLn(
                    'The near-lossless option is not supported on this (rather old) version of cwebp' .
                        '- skipping it.',
                    'italic'
                );
            } else {
                // We only let near_lossless have effect when encoding is set to "lossless"
                // otherwise encoding=auto would not work as expected

                if ($options['encoding'] == 'lossless') {
                    $cmdOptions[] ='-near_lossless ' . $options['near-lossless'];
                } else {
                    $this->logLn(
                        'The near-lossless option ignored for lossy'
                    );
                }
            }
        }

        if ($options['auto-filter'] === true) {
            $cmdOptions[] = '-af';
        }

        // Built-in method option
        $cmdOptions[] = '-m ' . strval($options['method']);

        // Built-in low memory option
        if ($options['low-memory']) {
            $cmdOptions[] = '-low_memory';
        }

        // command-line-options
        if ($options['command-line-options']) {
            array_push(
                $cmdOptions,
                ...self::escapeShellArgOnCommandLineOptions($options['command-line-options'])
            );
        }

        // Source file
        $cmdOptions[] = escapeshellarg($this->source);

        // Output
        $cmdOptions[] = '-o ' . escapeshellarg($this->destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $cmdOptions[] = '2>&1';

        $commandOptions = implode(' ', $cmdOptions);
        //$this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    /**
     *  Get path for supplied binary for current OS - and validate hash.
     *
     *  @return  array  Array of supplied binaries (which actually exists, and where hash validates)
     */
    private function getSuppliedBinaryPathForOS()
    {
        $this->log('Checking if we have a supplied binary for OS: ' . PHP_OS . '... ');

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $this->logLn('No we dont - not for that OS');
            return [];
        }
        $this->logLn('We do.');

        $result = [];
        $files = self::$suppliedBinariesInfo[PHP_OS];
        if (count($files) > 0) {
            $this->logLn('We in fact have ' . count($files));
        }

        foreach ($files as $i => list($file, $hash)) {
            //$file = $info[0];
            //$hash = $info[1];

            $binaryFile = __DIR__ . '/' . $options['rel-path-to-precompiled-binaries'] . '/' . $file;

            // Replace "/./" with "/" in path (we could alternatively use realpath)
            //$binaryFile = preg_replace('#\/\.\/#', '/', $binaryFile);
            // The file should exist, but may have been removed manually.
            /*
            if (!file_exists($binaryFile)) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                return false;
            }*/

            $realPathResult = realpath($binaryFile);
            if ($realPathResult === false) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                continue;
            }
            $binaryFile = $realPathResult;

            // File exists, now generate its hash
            // hash_file() is normally available, but it is not always
            // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
            // If available, validate that hash is correct.

            if (function_exists('hash_file')) {
                $binaryHash = hash_file('sha256', $binaryFile);

                if ($binaryHash != $hash) {
                    $this->logLn(
                        'Binary checksum of supplied binary is invalid! ' .
                        'Did you transfer with FTP, but not in binary mode? ' .
                        'File:' . $binaryFile . '. ' .
                        'Expected checksum: ' . $hash . '. ' .
                        'Actual checksum:' . $binaryHash . '.',
                        'bold'
                    );
                    continue;
                }
            }
            $result[] = $binaryFile;
        }

        return $result;
    }

    private function discoverBinaries()
    {
        $this->logLn('Locating cwebp binaries');

        if (defined('WEBPCONVERT_CWEBP_PATH')) {
            $this->logLn('WEBPCONVERT_CWEBP_PATH was defined, so using that path and ignoring any other');
            //$this->logLn('Value: "' . getenv('WEBPCONVERT_CWEBP_PATH') . '"');
            return [constant('WEBPCONVERT_CWEBP_PATH')];
        }
        if (!empty(getenv('WEBPCONVERT_CWEBP_PATH'))) {
            $this->logLn(
                'WEBPCONVERT_CWEBP_PATH environment variable was set, so using that path and ignoring any other'
            );
            //$this->logLn('Value: "' . getenv('WEBPCONVERT_CWEBP_PATH') . '"');
            return [getenv('WEBPCONVERT_CWEBP_PATH')];
        }

        $binaries = [];
        if ($this->options['try-common-system-paths']) {
            foreach (self::$cwebpDefaultPaths as $binary) {
                if (@file_exists($binary)) {
                    $binaries[] = $binary;
                }
            }
            if (count($binaries) == 0) {
                $this->logLn('No cwebp binaries where located in common system locations');
            } else {
                $this->logLn(strval(count($binaries)) . ' cwebp binaries found in common system locations');
            }
        }
        // TODO: exec('whereis cwebp');
        if ($this->options['try-supplied-binary-for-os']) {
            $suppliedBinaries = $this->getSuppliedBinaryPathForOS();
            foreach ($suppliedBinaries as $suppliedBinary) {
                $binaries[] = $suppliedBinary;
            }
        } else {
            $this->logLn('Configured not to try the cwebp binary that comes bundled with webp-convert');
        }

        if (count($binaries) == 0) {
            $this->logLn('No cwebp binaries to try!');
        }
        $this->logLn('A total of ' . strval(count($binaries)) . ' cwebp binaries where found');
        return $binaries;
    }

    /**
     *
     * @return  string|int  Version string (ie "1.0.2") OR return code, in case of failure
     */
    private function detectVersion($binary)
    {
        //$this->logLn('Examining binary: ' . $binary);
        $command = $binary . ' -version';
        $this->log('Executing: ' . $command);
        exec($command, $output, $returnCode);

        if ($returnCode == 0) {
            //$this->logLn('Success');
            if (isset($output[0])) {
                $this->logLn('. Result: version: ' . $output[0]);
                return $output[0];
            }
        } else {
            $this->logExecOutput($output);
            $this->logLn('');
            if ($returnCode == 127) {
                $this->logLn('Exec failed (the cwebp binary was not found at path: ' . $binary. ')');
            } else {
                $this->logLn(
                    'Exec failed (return code: ' . $returnCode . ')'
                );
                if ($returnCode == 126) {
                    $this->logLn(
                        'PS: Return code 126 means "Permission denied". The user that the command was run with does ' .
                            'not have permission to execute that binary.'
                    );
                    // TODO: further info: shell_exec('whoami')
                }
            }
            return $returnCode;
        }
    }

    /**
     *  Check versions for binaries, and return array (indexed by the binary, value being the version of the binary).
     *
     *  @return  array
     */
    private function detectVersions($binaries)
    {
        $binariesWithVersions = [];
        $binariesWithFailCodes = [];

        $this->logLn(
            'Detecting versions of the cwebp binaries found (and verifying that they can be executed in the process)'
        );
        foreach ($binaries as $binary) {
            $versionStringOrFailCode = $this->detectVersion($binary);
        //    $this->logLn($binary . ': ' . $versionString);
            if (gettype($versionStringOrFailCode) == 'string') {
                $binariesWithVersions[$binary] = $versionStringOrFailCode;
            } else {
                $binariesWithFailCodes[$binary] = $versionStringOrFailCode;
            }
        }
        return ['detected' => $binariesWithVersions, 'failed' => $binariesWithFailCodes];
    }

    /**
     * @return  boolean  success or not.
     */
    private function tryBinary($binary, $version, $useNice)
    {

        //$this->logLn('Trying binary: ' . $binary);
        $commandOptions = $this->createCommandLineOptions($version);

        $returnCode = $this->executeBinary($binary, $commandOptions, $useNice);
        if ($returnCode == 0) {
            // It has happened that even with return code 0, there was no file at destination.
            if (!file_exists($this->destination)) {
                $this->logLn('executing cweb returned success code - but no file was found at destination!');
                return false;
            } else {
                $this->logLn('Success');
                return true;
            }
        } else {
            $this->logLn(
                'Exec failed (return code: ' . $returnCode . ')'
            );
            return false;
        }
    }

    protected function doActualConvert()
    {
        $binaries = $this->discoverBinaries();

        if (count($binaries) == 0) {
            throw new SystemRequirementsNotMetException(
                'No cwebp binaries located. Check the conversion log for details.'
            );
        }

        $versions = $this->detectVersions($binaries);
        if (count($versions['detected']) == 0) {
            //$this->logLn('None of the cwebp files located can be executed.');
            if (count($binaries) == 1) {
                $errorMsg = 'The cwebp file found cannot be can be executed.';
            } else {
                $errorMsg = 'None of the cwebp files located can be executed.';
            }
            $uniqueFailCodes = array_unique(array_values($versions['failed']));
            if (count($uniqueFailCodes) == 1) {
                $errorMsg .= ' ' . (count($binaries) == 1 ? 'It' : 'All') .
                    ' failed with return code ' . $uniqueFailCodes[0];
                if ($uniqueFailCodes[0] == 126) {
                    $errorMsg .= ' (permission denied)';
                }
            } else {
                $errorMsg .= ' Failure codes : ' . implode(', ', $uniqueFailCodes);
            }

            throw new SystemRequirementsNotMetException($errorMsg);
        }

        $binaryVersions = $versions['detected'];

        if (count($binaries) > 1) {
            $this->logLn(
                'Trying executing the cwebs found until success. Starting with the ones with highest version number.'
            );
        }
        //$this->logLn('binary versions: ' . print_r($binaryVersions, true));

        // Sort binaries so those with highest numbers comes first
        arsort($binaryVersions);

        //$this->logLn('binary versions (ordered by version): ' . print_r($binaryVersions, true));

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport());

        $success = false;
        foreach ($binaryVersions as $binary => $version) {
            if ($this->tryBinary($binary, $version, $useNice)) {
                $success = true;
                break;
            }
        }

        // cwebp sets file permissions to 664 but instead ..
        // .. $destination's parent folder's permissions should be used (except executable bits)
        // (or perhaps the current umask instead? https://www.php.net/umask)

        if ($success) {
            $destinationParent = dirname($this->destination);
            $fileStatistics = stat($destinationParent);
            if ($fileStatistics !== false) {
                // Apply same permissions as parent folder but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                chmod($this->destination, $permissions);
            }
        } else {
            throw new SystemRequirementsNotMetException('Failed converting. Check the conversion log for details.');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\CloudConverterTrait;
use WebPConvert\Convert\Converters\ConverterTraits\CurlTrait;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\InvalidApiKeyException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Options\SensitiveStringOption;

/**
 * Convert images to webp using ewww cloud service.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Ewww extends AbstractConverter
{
    use CloudConverterTrait;
    use CurlTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'alpha-quality',
            'auto-filter',
            'encoding',
            'low-memory',
            'use-nice'
        ];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new SensitiveStringOption('api-key', '')
        );
    }

    /**
     * Get api key from options or environment variable
     *
     * @return string|false  api key or false if none is set
     */
    private function getKey()
    {
        if (!empty($this->options['api-key'])) {
            return $this->options['api-key'];
        }
        if (defined('WEBPCONVERT_EWWW_API_KEY')) {
            return constant('WEBPCONVERT_EWWW_API_KEY');
        }
        if (!empty(getenv('WEBPCONVERT_EWWW_API_KEY'))) {
            return getenv('WEBPCONVERT_EWWW_API_KEY');
        }
        return false;
    }


    /**
     * Check operationality of Ewww converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met (curl)
     * @throws ConverterNotOperationalException   if key is missing or invalid, or quota has exceeded
     */
    public function checkOperationality()
    {

        $apiKey = $this->getKey();

        if ($apiKey === false) {
            if (isset($this->options['key'])) {
                throw new InvalidApiKeyException(
                    'The "key" option has been renamed to "api-key" in webp-convert 2.0. ' .
                    'You must change the configuration accordingly.'
                );
            }

            throw new InvalidApiKeyException('Missing API key.');
        }

        if (strlen($apiKey) < 20) {
            throw new InvalidApiKeyException(
                'Api key is invalid. Api keys are supposed to be 32 characters long - ' .
                'the provided api key is much shorter'
            );
        }

        // Check for curl requirements
        $this->checkOperationalityForCurlTrait();

        $keyStatus = self::getKeyStatus($apiKey);
        switch ($keyStatus) {
            case 'great':
                break;
            case 'exceeded':
                throw new ConverterNotOperationalException('Quota has exceeded');
                break;
            case 'invalid':
                throw new InvalidApiKeyException('Api key is invalid');
                break;
        }
    }

    /*
    public function checkConvertability()
    {
        // check upload limits
        $this->checkConvertabilityCloudConverterTrait();
    }
    */

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doActualConvert()
    {

        $options = $this->options;

        $ch = self::initCurl();

        //$this->logLn('api key:' . $this->getKey());

        $postData = [
            'api_key' => $this->getKey(),
            'webp' => '1',
            'file' => curl_file_create($this->source),
            'quality' => $this->getCalculatedQuality(),
            'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
        ];

        curl_setopt_array(
            $ch,
            [
            CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
            CURLOPT_HTTPHEADER => [
                'User-Agent: WebPConvert',
                'Accept: image/*'
            ],
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
            ]
        );

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ConversionFailedException(curl_error($ch));
        }

        // The API does not always return images.
        // For example, it may return a message such as '{"error":"invalid","t":"exceeded"}
        // Messages has a http content type of ie 'text/html; charset=UTF-8
        // Images has application/octet-stream.
        // So verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            //echo curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            /* May return this: {"error":"invalid","t":"exceeded"} */
            $responseObj = json_decode($response);
            if (isset($responseObj->error)) {
                //echo 'error:' . $responseObj->error . '<br>';
                //echo $response;
                //self::blacklistKey($key);
                //throw new SystemRequirementsNotMetException('The key is invalid. Blacklisted it!');
                throw new InvalidApiKeyException('The api key is invalid');
            }

            throw new ConversionFailedException(
                'ewww api did not return an image. It could be that the key is invalid. Response: '
                . $response
            );
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            throw new ConversionFailedException('ewww api did not return anything');
        }

        $success = file_put_contents($this->destination, $response);

        if (!$success) {
            throw new ConversionFailedException('Error saving file');
        }
    }

    /**
     *  Keep subscription alive by optimizing a jpeg
     *  (ewww closes accounts after 6 months of inactivity - and webp conversions seems not to be counted? )
     */
    public static function keepSubscriptionAlive($source, $key)
    {
        try {
            $ch = curl_init();
        } catch (\Exception $e) {
            return 'curl is not installed';
        }
        if ($ch === false) {
            return 'curl could not be initialized';
        }
        curl_setopt_array(
            $ch,
            [
            CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
            CURLOPT_HTTPHEADER => [
                'User-Agent: WebPConvert',
                'Accept: image/*'
            ],
            CURLOPT_POSTFIELDS => [
                'api_key' => $key,
                'webp' => '0',
                'file' => curl_file_create($source),
                'domain' => $_SERVER['HTTP_HOST'],
                'quality' => 60,
                'metadata' => 0
            ],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
            ]
        );

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'curl error' . curl_error($ch);
        }
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);

            /* May return this: {"error":"invalid","t":"exceeded"} */
            $responseObj = json_decode($response);
            if (isset($responseObj->error)) {
                return 'The key is invalid';
            }

            return 'ewww api did not return an image. It could be that the key is invalid. Response: ' . $response;
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            return 'ewww api did not return anything';
        }

        return true;
    }

    /*
        public static function blacklistKey($key)
        {
        }

        public static function isKeyBlacklisted($key)
        {
        }*/

    /**
     *  Return "great", "exceeded" or "invalid"
     */
    public static function getKeyStatus($key)
    {
        $ch = self::initCurl();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/verify/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );

        // The 403 forbidden is avoided with this line.
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        // echo $response;
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        // Possible responses:
        // “great” = verification successful
        // “exceeded” = indicates a valid key with no remaining image credits.
        // an empty response indicates that the key is not valid

        if ($response == '') {
            return 'invalid';
        }
        $responseObj = json_decode($response);
        if (isset($responseObj->error)) {
            if ($responseObj->error == 'invalid') {
                return 'invalid';
            } else {
                throw new \Exception('Ewww returned unexpected error: ' . $response);
            }
        }
        if (!isset($responseObj->status)) {
            throw new \Exception('Ewww returned unexpected response to verify request: ' . $response);
        }
        switch ($responseObj->status) {
            case 'great':
            case 'exceeded':
                return $responseObj->status;
        }
        throw new \Exception('Ewww returned unexpected status to verify request: "' . $responseObj->status . '"');
    }

    public static function isWorkingKey($key)
    {
        return (self::getKeyStatus($key) == 'great');
    }

    public static function isValidKey($key)
    {
        return (self::getKeyStatus($key) != 'invalid');
    }

    public static function getQuota($key)
    {
        $ch = self::initCurl();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/quota/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        return $response; // ie -830 23. Seems to return empty for invalid keys
        // or empty
        //echo $response;
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

/**
 * Convert images to webp using gd extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Gd extends AbstractConverter
{
    public function supportsLossless()
    {
        return false;
    }

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'alpha-quality',
            'auto-filter',
            'encoding',
            'low-memory',
            'metadata',
            'method',
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice'
        ];
    }

    private $errorMessageWhileCreating = '';
    private $errorNumberWhileCreating;

    /**
     * Check (general) operationality of Gd converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('gd')) {
            throw new SystemRequirementsNotMetException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new SystemRequirementsNotMetException(
                'Gd has been compiled without webp support.'
            );
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Gd has been compiled without support for image type
     */
    public function checkConvertability()
    {
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;

            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new SystemRequirementsNotMetException(
                        'Gd has been compiled without Jpeg support and can therefore not convert this jpeg image.'
                    );
                }
        }
    }

    /**
     * Find out if all functions exists.
     *
     * @return boolean
     */
    private static function functionsExist($functionNamesArr)
    {
        foreach ($functionNamesArr as $functionName) {
            if (!function_exists($functionName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Try to convert image pallette to true color on older systems that does not have imagepalettetotruecolor().
     *
     * The aim is to function as imagepalettetotruecolor, but for older systems.
     * So, if the image is already rgb, nothing will be done, and true will be returned
     * PS: Got the workaround here: https://secure.php.net/manual/en/function.imagepalettetotruecolor.php
     *
     * @param  resource  $image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    private function makeTrueColorUsingWorkaround(&$image)
    {
        //return $this->makeTrueColor($image);
        /*
        if (function_exists('imageistruecolor') && imageistruecolor($image)) {
            return true;
        }*/
        if (self::functionsExist(['imagecreatetruecolor', 'imagealphablending', 'imagecolorallocatealpha',
                'imagefilledrectangle', 'imagecopy', 'imagedestroy', 'imagesx', 'imagesy'])) {
            $dst = imagecreatetruecolor(imagesx($image), imagesy($image));

            if ($dst === false) {
                return false;
            }

            //prevent blending with default black
            if (imagealphablending($dst, false) === false) {
                return false;
            }

             //change the RGB values if you need, but leave alpha at 127
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);

            if ($transparent === false) {
                return false;
            }

             //simpler than flood fill
            if (imagefilledrectangle($dst, 0, 0, imagesx($image), imagesy($image), $transparent) === false) {
                return false;
            }

            //restore default blending
            if (imagealphablending($dst, true) === false) {
                return false;
            };

            if (imagecopy($dst, $image, 0, 0, 0, 0, imagesx($image), imagesy($image)) === false) {
                return false;
            }
            imagedestroy($image);

            $image = $dst;
            return true;
        } else {
            // The necessary methods for converting color palette are not avalaible
            return false;
        }
    }

    /**
     * Try to convert image pallette to true color.
     *
     * Try to convert image pallette to true color. If imagepalettetotruecolor() exists, that is used (available from
     * PHP >= 5.5.0). Otherwise using workaround found on the net.
     *
     * @param  resource  $image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    private function makeTrueColor(&$image)
    {
        if (function_exists('imagepalettetotruecolor')) {
            return imagepalettetotruecolor($image);
        } else {
            // imagepalettetotruecolor() is not available on this system. Using custom implementation instead
            return $this->makeTrueColorUsingWorkaround($image);
        }
    }

    /**
     * Create Gd image resource from source
     *
     * @throws  InvalidInputException  if mime type is unsupported or could not be detected
     * @throws  ConversionFailedException  if imagecreatefrompng or imagecreatefromjpeg fails
     * @return  resource  $image  The created image
     */
    private function createImageResource()
    {
        // In case of failure, image will be false

        $mimeType = $this->getMimeTypeOfSource();

        if ($mimeType == 'image/png') {
            $image = imagecreatefrompng($this->source);
            if ($image === false) {
                throw new ConversionFailedException(
                    'Gd failed when trying to load/create image (imagecreatefrompng() failed)'
                );
            }
            return $image;
        }

        if ($mimeType == 'image/jpeg') {
            $image = imagecreatefromjpeg($this->source);
            if ($image === false) {
                throw new ConversionFailedException(
                    'Gd failed when trying to load/create image (imagecreatefromjpeg() failed)'
                );
            }
            return $image;
        }

        /*
        throw new InvalidInputException(
            'Unsupported mime type:' . $mimeType
        );*/
    }

    /**
     * Try to make image resource true color if it is not already.
     *
     * @param  resource  $image  The image to work on
     * @return void
     */
    protected function tryToMakeTrueColorIfNot(&$image)
    {
        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($image)) {
                $this->logLn('image is true color');
            } else {
                $this->logLn('image is not true color');
                $mustMakeTrueColor = true;
            }
        } else {
            $this->logLn('It can not be determined if image is true color');
            $mustMakeTrueColor = true;
        }

        if ($mustMakeTrueColor) {
            $this->logLn('converting color palette to true color');
            $success = $this->makeTrueColor($image);
            if (!$success) {
                $this->logLn(
                    'Warning: FAILED converting color palette to true color. ' .
                    'Continuing, but this does NOT look good.'
                );
            }
        }
    }

    /**
     *
     * @param  resource  $image
     * @return boolean  true if alpha blending was set successfully, false otherwise
     */
    protected function trySettingAlphaBlending($image)
    {
        if (function_exists('imagealphablending')) {
            if (!imagealphablending($image, true)) {
                $this->logLn('Warning: imagealphablending() failed');
                return false;
            }
        } else {
            $this->logLn(
                'Warning: imagealphablending() is not available on your system.' .
                ' Converting PNGs with transparency might fail on some systems'
            );
            return false;
        }

        if (function_exists('imagesavealpha')) {
            if (!imagesavealpha($image, true)) {
                $this->logLn('Warning: imagesavealpha() failed');
                return false;
            }
        } else {
            $this->logLn(
                'Warning: imagesavealpha() is not available on your system. ' .
                'Converting PNGs with transparency might fail on some systems'
            );
            return false;
        }
        return true;
    }

    protected function errorHandlerWhileCreatingWebP($errno, $errstr, $errfile, $errline)
    {
        $this->errorNumberWhileCreating = $errno;
        $this->errorMessageWhileCreating = $errstr . ' in ' . $errfile . ', line ' . $errline .
            ', PHP ' . PHP_VERSION . ' (' . PHP_OS . ')';
        //return false;
    }

    /**
     *
     * @param  resource  $image
     * @return void
     */
    protected function destroyAndRemove($image)
    {
        imagedestroy($image);
        if (file_exists($this->destination)) {
            unlink($this->destination);
        }
    }

    /**
     *
     * @param  resource  $image
     * @return void
     */
    protected function tryConverting($image)
    {

        // Danger zone!
        //    Using output buffering to generate image.
        //    In this zone, Do NOT do anything that might produce unwanted output
        //    Do NOT call $this->logLn
        // --------------------------------- (start of danger zone)

        $addedZeroPadding = false;
        set_error_handler(array($this, "errorHandlerWhileCreatingWebP"));

        // This line may trigger log, so we need to do it BEFORE ob_start() !
        $q = $this->getCalculatedQuality();

        ob_start();

        //$success = imagewebp($image, $this->destination, $q);
        $success = imagewebp($image, null, $q);

        if (!$success) {
            $this->destroyAndRemove($image);
            ob_end_clean();
            restore_error_handler();
            throw new ConversionFailedException(
                'Failed creating image. Call to imagewebp() failed.',
                $this->errorMessageWhileCreating
            );
        }


        // The following hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (ob_get_length() % 2 == 1) {
            echo "\0";
            $addedZeroPadding = true;
        }
        $output = ob_get_clean();
        restore_error_handler();

        if ($output == '') {
            $this->destroyAndRemove($image);
            throw new ConversionFailedException(
                'Gd failed: imagewebp() returned empty string'
            );
        }

        // --------------------------------- (end of danger zone).


        if ($this->errorMessageWhileCreating != '') {
            switch ($this->errorNumberWhileCreating) {
                case E_WARNING:
                    $this->logLn('An warning was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                case E_NOTICE:
                    $this->logLn('An notice was produced during conversion: ' . $this->errorMessageWhileCreating);
                    break;
                default:
                    $this->destroyAndRemove($image);
                    throw new ConversionFailedException(
                        'An error was produced during conversion',
                        $this->errorMessageWhileCreating
                    );
                    break;
            }
        }

        if ($addedZeroPadding) {
            $this->logLn(
                'Fixing corrupt webp by adding a zero byte ' .
                '(older versions of Gd had a bug, but this hack fixes it)'
            );
        }

        $success = file_put_contents($this->destination, $output);

        if (!$success) {
            $this->destroyAndRemove($image);
            throw new ConversionFailedException(
                'Gd failed when trying to save the image. Check file permissions!'
            );
        }

        /*
        Previous code was much simpler, but on a system, the hack was not activated (a file with uneven number of bytes
        was created). This is puzzeling. And the old code did not provide any insights.
        Also, perhaps having two subsequent writes to the same file could perhaps cause a problem.
        In the new code, there is only one write.
        However, a bad thing about the new code is that the entire webp file is read into memory. This might cause
        memory overflow with big files.
        Perhaps we should check the filesize of the original and only use the new code when it is smaller than
        memory limit set in PHP by a certain factor.
        Or perhaps only use the new code on older versions of Gd
        https://wordpress.org/support/topic/images-not-seen-on-chrome/#post-11390284

        Here is the old code:

        $success = imagewebp($image, $this->destination, $this->getCalculatedQuality());

        if (!$success) {
            throw new ConversionFailedException(
                'Gd failed when trying to save the image as webp (call to imagewebp() failed). ' .
                'It probably failed writing file. Check file permissions!'
            );
        }


        // This hack solves an `imagewebp` bug
        // See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
        if (filesize($this->destination) % 2 == 1) {
            file_put_contents($this->destination, "\0", FILE_APPEND);
        }
        */
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doActualConvert()
    {

        $this->logLn('GD Version: ' . gd_info()["GD Version"]);

        // Btw: Check out processWebp here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Gd/Encoder.php

        // Create image resource
        $image = $this->createImageResource();

        // Try to convert color palette if it is not true color
        $this->tryToMakeTrueColorIfNot($image);


        if ($this->getMimeTypeOfSource() == 'image/png') {
            // Try to set alpha blending
            $this->trySettingAlphaBlending($image);
        }

        // Try to convert it to webp
        $this->tryConverting($image);

        // End of story
        imagedestroy($image);
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp using Gmagick extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Gmagick extends AbstractConverter
{
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice'
        ];
    }

    /**
     * Check (general) operationality of Gmagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('Gmagick')) {
            throw new SystemRequirementsNotMetException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new SystemRequirementsNotMetException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        $im = new \Gmagick($this->source);

        if (!in_array('WEBP', $im->queryformats())) {
            throw new SystemRequirementsNotMetException('Gmagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Gmagick does not support image type
     */
    public function checkConvertability()
    {
        $im = new \Gmagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Gmagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Gmagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doActualConvert()
    {

        $options = $this->options;

        try {
            $im = new \Gmagick($this->source);
        } catch (\Exception $e) {
            throw new ConversionFailedException(
                'Failed creating Gmagick object of file',
                'Failed creating Gmagick object of file: "' . $this->source . '" - Gmagick threw an exception.',
                $e
            );
        }

        $im->setimageformat('WEBP');

        // Not completely sure if setimageoption() has always been there, so lets check first. #169
        if (method_exists($im, 'setimageoption')) {
            // Finally cracked setting webp options.
            // See #167
            // - and https://stackoverflow.com/questions/47294962/how-to-write-lossless-webp-files-with-perlmagick
            $im->setimageoption('webp', 'method', $options['method']);
            $im->setimageoption('webp', 'lossless', $options['encoding'] == 'lossless' ? 'true' : 'false');
            $im->setimageoption('webp', 'alpha-quality', $options['alpha-quality']);

            if ($options['auto-filter'] === true) {
                $im->setimageoption('webp', 'auto-filter', 'true');
            }
        }

        /*
        low-memory seems not to be supported:
        $im->setimageoption('webp', 'low-memory', $options['low-memory'] ? true : false);
        */

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        // Ps: Imagick automatically uses same quality as source, when no quality is set
        // This feature is however not present in Gmagick
        // TODO: However, it might be possible after all - see #91
        $im->setcompressionquality($this->getCalculatedQuality());

        // We call getImageBlob().
        // That method is undocumented, but it is there!
        // - just like it is in imagick, as pointed out here:
        //   https://www.php.net/manual/ru/gmagick.readimageblob.php

        /** @scrutinizer ignore-call */
        $imageBlob = $im->getImageBlob();

        $success = @file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new ConversionFailedException('Failed writing file');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

/**
 * Non-functional converter, just here to tell you that it has been renamed.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class GmagickBinary extends AbstractConverter
{
    public function checkOperationality()
    {
        throw new ConversionFailedException(
            'This converter has changed ID from "gmagickbinary" to "graphicsmagick". You need to change!'
        );
    }

    protected function doActualConvert()
    {
        $this->checkOperationality();
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by calling gmagick binary (gm).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class GraphicsMagick extends AbstractConverter
{
    use ExecTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'auto-filter',
            'near-lossless',
            'preset',
            'size-in-percentage',
        ];
    }

    private function getPath()
    {
        if (defined('WEBPCONVERT_GRAPHICSMAGICK_PATH')) {
            return constant('WEBPCONVERT_GRAPHICSMAGICK_PATH');
        }
        if (!empty(getenv('WEBPCONVERT_GRAPHICSMAGICK_PATH'))) {
            return getenv('WEBPCONVERT_GRAPHICSMAGICK_PATH');
        }
        return 'gm';
    }

    public function isInstalled()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        return ($returnCode == 0);
    }

    public function getVersion()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        if (($returnCode == 0) && isset($output[0])) {
            return preg_replace('#http.*#', '', $output[0]);
        }
        return 'unknown';
    }

    // Check if webp delegate is installed
    public function isWebPDelegateInstalled()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#WebP.*yes#i', $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        if (!$this->isInstalled()) {
            throw new SystemRequirementsNotMetException('gmagick is not installed');
        }
        if (!$this->isWebPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('webp delegate missing');
        }
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        $commandArguments = [];

        // Unlike imagick binary, it seems gmagick binary uses a fixed
        // quality (75) when quality is omitted
        $commandArguments[] = '-quality ' . escapeshellarg($this->getCalculatedQuality());

        // encoding
        if ($this->options['encoding'] == 'lossless') {
            // Btw:
            // I am not sure if we should set "quality" for lossless.
            // Quality should not apply to lossless, but my tests shows that it does in some way for gmagick
            // setting it low, you get bad visual quality and small filesize. Setting it high, you get the opposite
            // Some claim it is a bad idea to set quality, but I'm not so sure.
            // https://stackoverflow.com/questions/4228027/
            // First, I do not just get bigger images when setting quality, as toc777 does.
            // Secondly, the answer is very old and that bad behaviour is probably fixed by now.
            $commandArguments[] = '-define webp:lossless=true';
        } else {
            $commandArguments[] = '-define webp:lossless=false';
        }

        if ($this->options['alpha-quality'] !== 100) {
            $commandArguments[] = '-define webp:alpha-quality=' . strval($this->options['alpha-quality']);
        }

        if ($this->options['low-memory']) {
            $commandArguments[] = '-define webp:low-memory=true';
        }

        if ($this->options['metadata'] == 'none') {
            $commandArguments[] = '-strip';
        }

        $commandArguments[] = '-define webp:method=' . $this->options['method'];

        $commandArguments[] = escapeshellarg($this->source);
        $commandArguments[] = escapeshellarg('webp:' . $this->destination);

        return implode(' ', $commandArguments);
    }

    protected function doActualConvert()
    {
        //$this->logLn('Using quality:' . $this->getCalculatedQuality());

        $this->logLn('Version: ' . $this->getVersion());

        $command = $this->getPath() . ' convert ' . $this->createCommandLineOptions();

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        $this->logLn('Executing command: ' . $command);
        exec($command, $output, $returnCode);

        $this->logExecOutput($output);
        if ($returnCode == 0) {
            $this->logLn('success');
        } else {
            $this->logLn('return code: ' . $returnCode);
        }

        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('gmagick is not installed');
        }
        if ($returnCode != 0) {
            $this->logLn('command:' . $command);
            $this->logLn('return code:' . $returnCode);
            $this->logLn('output:' . print_r(implode("\n", $output), true));
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by calling imagemagick binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ImageMagick extends AbstractConverter
{
    use ExecTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'near-lossless',
            'preset',
            'size-in-percentage',
        ];
    }

    // To futher improve this converter, I could check out:
    // https://github.com/Orbitale/ImageMagickPHP

    private function getPath()
    {
        if (defined('WEBPCONVERT_IMAGEMAGICK_PATH')) {
            return constant('WEBPCONVERT_IMAGEMAGICK_PATH');
        }
        if (!empty(getenv('WEBPCONVERT_IMAGEMAGICK_PATH'))) {
            return getenv('WEBPCONVERT_IMAGEMAGICK_PATH');
        }
        return 'convert';
    }

    private function getVersion()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        if (($returnCode == 0) && isset($output[0])) {
            return $output[0];
        } else {
            return 'unknown';
        }
    }

    public function isInstalled()
    {
        exec($this->getPath() . ' -version', $output, $returnCode);
        return ($returnCode == 0);
    }

    // Check if webp delegate is installed
    public function isWebPDelegateInstalled()
    {

        exec('convert -list delegate', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#webp\\s*=#i', $line)) {
                return true;
            }
        }

        // try other command
        exec('convert -list configure', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#DELEGATE.*webp#i', $line)) {
                return true;
            }
        }

        return false;

        // PS, convert -version does not output delegates on travis, so it is not reliable
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        if (!$this->isInstalled()) {
            throw new SystemRequirementsNotMetException(
                'imagemagick is not installed (cannot execute: "' . $this->getPath() . '")'
            );
        }
        if (!$this->isWebPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('webp delegate missing');
        }
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        // PS: Available webp options for imagemagick are documented here:
        // https://imagemagick.org/script/webp.php

        $commandArguments = [];
        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality:auto was specified, but could not be determined.
            // we cannot apply the max-quality logic, but we can provide auto quality
            // simply by not specifying the quality option.
        } else {
            $commandArguments[] = '-quality ' . escapeshellarg($this->getCalculatedQuality());
        }
        if ($this->options['encoding'] == 'lossless') {
            $commandArguments[] = '-define webp:lossless=true';
        }
        if ($this->options['low-memory']) {
            $commandArguments[] = '-define webp:low-memory=true';
        }
        if ($this->options['auto-filter'] === true) {
            $commandArguments[] = '-define webp:auto-filter=true';
        }
        if ($this->options['metadata'] == 'none') {
            $commandArguments[] = '-strip';
        }
        if ($this->options['alpha-quality'] !== 100) {
            $commandArguments[] = '-define webp:alpha-quality=' . strval($this->options['alpha-quality']);
        }

        // Unfortunately, near-lossless does not seem to be supported.
        // it does have a "preprocessing" option, which may be doing something similar

        $commandArguments[] = '-define webp:method=' . $this->options['method'];

        $commandArguments[] = escapeshellarg($this->source);
        $commandArguments[] = escapeshellarg('webp:' . $this->destination);

        return implode(' ', $commandArguments);
    }

    protected function doActualConvert()
    {
        $this->logLn($this->getVersion());

        $command = $this->getPath() . ' ' . $this->createCommandLineOptions();

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        $this->logLn('Executing command: ' . $command);
        exec($command, $output, $returnCode);

        $this->logExecOutput($output);
        if ($returnCode == 0) {
            $this->logLn('success');
        } else {
            $this->logLn('return code: ' . $returnCode);
        }

        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('imagemagick is not installed');
        }
        if ($returnCode != 0) {
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp using Imagick extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Imagick extends AbstractConverter
{
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice'
        ];
    }

    /**
     * Check operationality of Imagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     * @return void
     */
    public function checkOperationality()
    {
        if (!extension_loaded('imagick')) {
            throw new SystemRequirementsNotMetException('Required iMagick extension is not available.');
        }

        if (!class_exists('\\Imagick')) {
            throw new SystemRequirementsNotMetException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        $im = new \Imagick();
        if (!in_array('WEBP', $im->queryFormats('WEBP'))) {
            throw new SystemRequirementsNotMetException('iMagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Imagick does not support image type
     */
    public function checkConvertability()
    {
        $im = new \Imagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats('PNG'))) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats('JPEG'))) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    /**
     *
     * It may also throw an ImagickException if imagick throws an exception
     * @throws CreateDestinationFileException if imageblob could not be saved to file
     */
    protected function doActualConvert()
    {
        /*
         * More about iMagick's WebP options:
         * - Inspect source code: https://github.com/ImageMagick/ImageMagick/blob/master/coders/webp.c#L559
         *      (search for "webp:")
         * - http://www.imagemagick.org/script/webp.php
         * - https://developers.google.com/speed/webp/docs/cwebp
         * - https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        $options = $this->options;

        // This might throw - we let it!
        $im = new \Imagick($this->source);

        //$im = new \Imagick();
        //$im->pingImage($this->source);
        //$im->readImage($this->source);

        $im->setImageFormat('WEBP');

        $im->setOption('webp:method', $options['method']);
        $im->setOption('webp:lossless', $options['encoding'] == 'lossless' ? 'true' : 'false');
        $im->setOption('webp:low-memory', $options['low-memory'] ? 'true' : 'false');
        $im->setOption('webp:alpha-quality', $options['alpha-quality']);

        if ($options['auto-filter'] === true) {
            $im->setOption('webp:auto-filter', 'true');
        }

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        if ($this->isQualityDetectionRequiredButFailing()) {
            // Luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-quality functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $this->logLn('Converting without setting quality in order to achieve auto quality');
        } else {
            $im->setImageCompressionQuality($this->getCalculatedQuality());
        }

        // https://stackoverflow.com/questions/29171248/php-imagick-jpeg-optimization
        // setImageFormat

        // TODO: Read up on
        // https://www.smashingmagazine.com/2015/06/efficient-image-resizing-with-imagemagick/
        // https://github.com/nwtn/php-respimg

        // TODO:
        // Should we set alpha channel for PNG's like suggested here:
        // https://gauntface.com/blog/2014/09/02/webp-support-with-imagemagick-and-php ??
        // It seems that alpha channel works without... (at least I see completely transparerent pixels)

        // We used to use writeImageFile() method. But we now use getImageBlob(). See issue #43

        // This might throw - we let it!
        $imageBlob = $im->getImageBlob();

        $success = file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new CreateDestinationFileException('Failed writing file');
        }

        // Btw: check out processWebp() method here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Imagick/Encoder.php
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

/**
 * Non-functional converter, just here to tell you that it has been renamed.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ImagickBinary extends AbstractConverter
{
    public function checkOperationality()
    {
        throw new ConversionFailedException(
            'This converter has changed ID from "imagickbinary" to "imagemagick". You need to change!'
        );
    }

    protected function doActualConvert()
    {
        $this->checkOperationality();
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\ConverterFactory;
use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\GhostOption;
use WebPConvert\Options\SensitiveArrayOption;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by trying a stack of converters until success.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Stack extends AbstractConverter
{

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'alpha-quality',
            'auto-filter',
            'encoding',
            'low-memory',
            'metadata',
            'method',
            'near-lossless',
            'preset',
            'size-in-percentage',
            'use-nice',
            'skip',
            'default-quality',
            'quality',
            'max-quality',
        ];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new SensitiveArrayOption('converters', self::getAvailableConverters()),
            new SensitiveArrayOption('converter-options', []),
            new BooleanOption('shuffle', false),
            new ArrayOption('preferred-converters', []),
            new SensitiveArrayOption('extra-converters', [])
        );
    }

    /**
     * Get available converters (ids) - ordered by awesomeness.
     *
     * @return  array  An array of ids of converters that comes with this library
     */
    public static function getAvailableConverters()
    {
        return [
            'cwebp', 'vips', 'imagick', 'gmagick', 'imagemagick', 'graphicsmagick', 'wpc', 'ewww', 'gd'
        ];
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (count($this->options['converters']) == 0) {
            throw new ConverterNotOperationalException(
                'Converter stack is empty! - no converters to try, no conversion can be made!'
            );
        }

        // TODO: We should test if all converters are found in order to detect problems early

        //$this->logLn('Stack converter ignited');
    }

    protected function doActualConvert()
    {
        $options = $this->options;

        $beginTimeStack = microtime(true);

        $anyRuntimeErrors = false;

        $converters = $options['converters'];
        if (count($options['extra-converters']) > 0) {
            $converters = array_merge($converters, $options['extra-converters']);
            /*foreach ($options['extra-converters'] as $extra) {
                $converters[] = $extra;
            }*/
        }

        // preferred-converters
        if (count($options['preferred-converters']) > 0) {
            foreach (array_reverse($options['preferred-converters']) as $prioritizedConverter) {
                foreach ($converters as $i => $converter) {
                    if (is_array($converter)) {
                        $converterId = $converter['converter'];
                    } else {
                        $converterId = $converter;
                    }
                    if ($converterId == $prioritizedConverter) {
                        unset($converters[$i]);
                        array_unshift($converters, $converter);
                        break;
                    }
                }
            }
            // perhaps write the order to the log? (without options) - but this requires some effort
        }

        // shuffle
        if ($options['shuffle']) {
            shuffle($converters);
        }

        //$this->logLn(print_r($converters));
        //$options['converters'] = $converters;
        //$defaultConverterOptions = $options;
        $defaultConverterOptions = [];

        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if ($option->isValueExplicitlySet() && ! ($option instanceof GhostOption)) {
                //$this->logLn('hi' . $id);
                $defaultConverterOptions[$id] = $option->getValue();
            }
        }

        //unset($defaultConverterOptions['converters']);
        //unset($defaultConverterOptions['converter-options']);
        $defaultConverterOptions['_skip_input_check'] = true;
        $defaultConverterOptions['_suppress_success_message'] = true;
        unset($defaultConverterOptions['converters']);
        unset($defaultConverterOptions['extra-converters']);
        unset($defaultConverterOptions['converter-options']);
        unset($defaultConverterOptions['preferred-converters']);
        unset($defaultConverterOptions['shuffle']);

//        $this->logLn('converters: ' . print_r($converters, true));

        //return;
        foreach ($converters as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = isset($converter['options']) ? $converter['options'] : [];
            } else {
                $converterId = $converter;
                $converterOptions = [];
                if (isset($options['converter-options'][$converterId])) {
                    // Note: right now, converter-options are not meant to be used,
                    //       when you have several converters of the same type
                    $converterOptions = $options['converter-options'][$converterId];
                }
            }
            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);
            /*
            if ($converterId != 'stack') {
                //unset($converterOptions['converters']);
                //unset($converterOptions['converter-options']);
            } else {
                //$converterOptions['converter-options'] =
                $this->logLn('STACK');
                $this->logLn('converterOptions: ' . print_r($converterOptions, true));
            }*/

            $beginTime = microtime(true);

            $this->ln();
            $this->logLn('Trying: ' . $converterId, 'italic');

            $converter = ConverterFactory::makeConverter(
                $converterId,
                $this->source,
                $this->destination,
                $converterOptions,
                $this->logger
            );

            try {
                $converter->doConvert();

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLn($converterId . ' succeeded :)');
                //throw new ConverterNotOperationalException('...');
                return;
            } catch (ConverterNotOperationalException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionFailedException $e) {
                $this->logLn($e->getMessage(), 'italic');
                $prev = $e->getPrevious();
                if (!is_null($prev)) {
                    $this->logLn($prev->getMessage(), 'italic');
                    $this->logLn(' in ' . $prev->getFile() . ', line ' . $prev->getLine(), 'italic');
                    $this->ln();
                }
                //$this->logLn($e->getTraceAsString());
                $anyRuntimeErrors = true;
            } catch (ConversionSkippedException $e) {
                $this->logLn($e->getMessage());
            }

            $this->logLn($converterId . ' failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
        }

        $this->ln();
        $this->logLn('Stack failed in ' . round((microtime(true) - $beginTimeStack) * 1000) . ' ms');

        // Hm, Scrutinizer complains that $anyRuntimeErrors is always false. But that is not true!
        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConversionFailedException(
                'None of the converters in the stack could convert the image.'
            );
        } else {
            // All converters threw a SystemRequirementsNotMetException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Options\BooleanOption;

//require '/home/rosell/.composer/vendor/autoload.php';

/**
 * Convert images to webp using Vips extension.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Vips extends AbstractConverter
{
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'size-in-percentage',
            'use-nice'
        ];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new BooleanOption('smart-subsample', false)
        );
    }

    /**
     * Check operationality of Vips converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!extension_loaded('vips')) {
            throw new SystemRequirementsNotMetException('Required Vips extension is not available.');
        }

        if (!function_exists('vips_image_new_from_file')) {
            throw new SystemRequirementsNotMetException(
                'Vips extension seems to be installed, however something is not right: ' .
                'the function "vips_image_new_from_file" is not available.'
            );
        }

        // TODO: Should we also test if webp is available? (It seems not to be neccessary - it seems
        // that webp be well intergrated part of vips)
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Vips does not support image type
     */
    public function checkConvertability()
    {
        // It seems that png and jpeg are always supported by Vips
        // - so nothing needs to be done here

        if (function_exists('vips_version')) {
            $this->logLn('vipslib version: ' . vips_version());
        }
        $this->logLn('vips extension version: ' . phpversion('vips'));
    }

    /**
     * Create vips image resource from source file
     *
     * @throws  ConversionFailedException  if image resource cannot be created
     * @return  resource  vips image resource
     */
    private function createImageResource()
    {
        // We are currently using vips_image_new_from_file(), but we could consider
        // calling vips_jpegload / vips_pngload instead
        $result = /** @scrutinizer ignore-call */ vips_image_new_from_file($this->source, []);
        if ($result === -1) {
            /*throw new ConversionFailedException(
                'Failed creating new vips image from file: ' . $this->source
            );*/
            $message = /** @scrutinizer ignore-call */ vips_error_buffer();
            throw new ConversionFailedException($message);
        }

        if (!is_array($result)) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array, which we expected'
            );
        }

        if (count($result) != 1) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array of length 1 as we expected ' .
                '- length was: ' . count($result)
            );
        }

        $im = array_shift($result);
        return $im;
    }

    /**
     * Create parameters for webpsave
     *
     * @return  array  the parameters as an array
     */
    private function createParamsForVipsWebPSave()
    {
        // webpsave options are described here:
        // v 8.8.0:  https://libvips.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave
        // v ?.?.?:  https://jcupitt.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave
        // near_lossless option is described here: https://github.com/libvips/libvips/pull/430

        // Note that "method" is currently not supported (27 may 2019)

        $options = [
            "Q" => $this->getCalculatedQuality(),
            'lossless' => ($this->options['encoding'] == 'lossless'),
            'strip' => $this->options['metadata'] == 'none',
        ];

        // Only set the following options if they differ from the default of vipslib
        // This ensures we do not get warning if that property isn't supported
        if ($this->options['smart-subsample'] !== false) {
            $options['smart_subsample'] = $this->options['smart-subsample'];
        }
        if ($this->options['alpha-quality'] !== 100) {
            $options['alpha_q'] = $this->options['alpha-quality'];
        }

        if (!is_null($this->options['preset']) && ($this->options['preset'] != 'none')) {
            // preset. 0:default, 1:picture, 2:photo, 3:drawing, 4:icon, 5:text, 6:last
            $options['preset'] = array_search(
                $this->options['preset'],
                ['default', 'picture', 'photo', 'drawing', 'icon', 'text']
            );
        }
        if ($this->options['near-lossless'] !== 100) {
            if ($this->options['encoding'] == 'lossless') {
                // We only let near_lossless have effect when encoding is set to lossless
                // otherwise encoding=auto would not work as expected
                // Available in https://github.com/libvips/libvips/pull/430, merged 1 may 2016
                // seems it corresponds to release 8.4.2
                $options['near_lossless'] = true;

                // In Vips, the near-lossless value is controlled by Q.
                // this differs from how it is done in cwebp, where it is an integer.
                // We have chosen same option syntax as cwebp
                $options['Q'] = $this->options['near-lossless'];
            }
        }

        return $options;
    }

    /**
     * Convert with vips extension.
     *
     * Tries to create image resource and save it as webp using the calculated options.
     * Vips fails when a parameter is not supported, but we detect this and unset that parameter and try again
     * (recursively call itself until there is no more of these kind of errors).
     *
     * @param  resource  $im  A vips image resource to save
     * @throws  ConversionFailedException  if conversion fails.
     */
    private function webpsave($im, $options)
    {
        $result = /** @scrutinizer ignore-call */ vips_call('webpsave', $im, $this->destination, $options);

        //trigger_error('test-warning', E_USER_WARNING);
        if ($result === -1) {
            $message = /** @scrutinizer ignore-call */ vips_error_buffer();

            $nameOfPropertyNotFound = '';
            if (preg_match("#no property named .(.*).#", $message, $matches)) {
                $nameOfPropertyNotFound = $matches[1];
            } elseif (preg_match("#(.*)\\sunsupported$#", $message, $matches)) {
                // Actually, I am not quite sure if this ever happens.
                // I got a "near_lossless unsupported" error message in a build, but perhaps it rather a warning
                if (in_array($matches[1], ['lossless', 'alpha_q', 'near_lossless', 'smart_subsample'])) {
                    $nameOfPropertyNotFound = $matches[1];
                }
            }

            if ($nameOfPropertyNotFound != '') {
                $this->logLn(
                    'Your version of vipslib does not support the "' . $nameOfPropertyNotFound . '" property. ' .
                    'The option is ignored.'
                );
                unset($options[$nameOfPropertyNotFound]);
                $this->webpsave($im, $options);
            } else {
                throw new ConversionFailedException($message);
            }
        }
    }

    /**
     * Convert with vips extension.
     *
     * Tries to create image resource and save it as webp using the calculated options.
     * Vips fails when a parameter is not supported, but we detect this and unset that parameter and try again
     * (repeat until success).
     *
     * @throws  ConversionFailedException  if conversion fails.
     */
    protected function doActualConvert()
    {
/*
        $im = \Jcupitt\Vips\Image::newFromFile($this->source);
        //$im->writeToFile(__DIR__ . '/images/small-vips.webp', ["Q" => 10]);

        $im->webpsave($this->destination, [
            "Q" => 80,
            //'near_lossless' => true
        ]);
        return;*/

        $im = $this->createImageResource();
        $options = $this->createParamsForVipsWebPSave();
        $this->webpsave($im, $options);
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\CloudConverterTrait;
use WebPConvert\Convert\Converters\ConverterTraits\CurlTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\InvalidApiKeyException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\SensitiveStringOption;

/**
 * Convert images to webp using Wpc (a cloud converter based on WebP Convert).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Wpc extends AbstractConverter
{
    use CloudConverterTrait;
    use CurlTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    public function supportsLossless()
    {
        return ($this->options['api-version'] >= 2);
    }

    public function passOnEncodingAuto()
    {
        // We could make this configurable. But I guess passing it on is always to be preferred
        // for api >= 2.
        return ($this->options['api-version'] >= 2);
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new SensitiveStringOption('api-key', ''),   /* for communicating with wpc api v.1+ */
            new SensitiveStringOption('secret', ''),    /* for communicating with wpc api v.0 */
            new SensitiveStringOption('api-url', ''),
            new SensitiveStringOption('url', ''),       /* DO NOT USE. Only here to keep the protection */
            new IntegerOption('api-version', 2, 0, 2),
            new BooleanOption('crypt-api-key-in-transfer', false)  /* new in api v.1 */
        );
    }

    private static function createRandomSaltForBlowfish()
    {
        $salt = '';
        $validCharsForSalt = array_merge(
            range('A', 'Z'),
            range('a', 'z'),
            range('0', '9'),
            ['.', '/']
        );

        for ($i=0; $i<22; $i++) {
            $salt .= $validCharsForSalt[array_rand($validCharsForSalt)];
        }
        return $salt;
    }

    /**
     * Get api key from options or environment variable
     *
     * @return string  api key or empty string if none is set
     */
    private function getApiKey()
    {
        if ($this->options['api-version'] == 0) {
            if (!empty($this->options['secret'])) {
                return $this->options['secret'];
            }
        } elseif ($this->options['api-version'] >= 1) {
            if (!empty($this->options['api-key'])) {
                return $this->options['api-key'];
            }
        }
        if (defined('WEBPCONVERT_WPC_API_KEY')) {
            return constant('WEBPCONVERT_WPC_API_KEY');
        }
        if (!empty(getenv('WEBPCONVERT_WPC_API_KEY'))) {
            return getenv('WEBPCONVERT_WPC_API_KEY');
        }
        return '';
    }

    /**
     * Get url from options or environment variable
     *
     * @return string  URL to WPC or empty string if none is set
     */
    private function getApiUrl()
    {
        if (!empty($this->options['api-url'])) {
            return $this->options['api-url'];
        }
        if (defined('WEBPCONVERT_WPC_API_URL')) {
            return constant('WEBPCONVERT_WPC_API_URL');
        }
        if (!empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            return getenv('WEBPCONVERT_WPC_API_URL');
        }
        return '';
    }


    /**
     * Check operationality of Wpc converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met (curl)
     * @throws ConverterNotOperationalException   if key is missing or invalid, or quota has exceeded
     */
    public function checkOperationality()
    {

        $options = $this->options;

        $apiVersion = $options['api-version'];

        if ($this->getApiUrl() == '') {
            if (isset($this->options['url']) && ($this->options['url'] != '')) {
                throw new ConverterNotOperationalException(
                    'The "url" option has been renamed to "api-url" in webp-convert 2.0. ' .
                    'You must change the configuration accordingly.'
                );
            }
            throw new ConverterNotOperationalException(
                'Missing URL. You must install Webp Convert Cloud Service on a server, ' .
                'or the WebP Express plugin for Wordpress - and supply the url.'
            );
        }

        if ($apiVersion == 0) {
            if (!empty($this->getApiKey())) {
                // if secret is set, we need md5() and md5_file() functions
                if (!function_exists('md5')) {
                    throw new ConverterNotOperationalException(
                        'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                        'contents. ' .
                        'But the required md5() PHP function is not available.'
                    );
                }
                if (!function_exists('md5_file')) {
                    throw new ConverterNotOperationalException(
                        'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                        'contents. But the required md5_file() PHP function is not available.'
                    );
                }
            }
        } elseif ($apiVersion >= 1) {
            if ($options['crypt-api-key-in-transfer']) {
                if (!function_exists('crypt')) {
                    throw new ConverterNotOperationalException(
                        'Configured to crypt the api-key, but crypt() function is not available.'
                    );
                }

                if (!defined('CRYPT_BLOWFISH')) {
                    throw new ConverterNotOperationalException(
                        'Configured to crypt the api-key. ' .
                        'That requires Blowfish encryption, which is not available on your current setup.'
                    );
                }
            }
        }

        // Check for curl requirements
        $this->checkOperationalityForCurlTrait();
    }

    /*
    public function checkConvertability()
    {
        // check upload limits
        $this->checkConvertabilityCloudConverterTrait();

        // TODO: some from below can be moved up here
    }
    */

    private function createOptionsToSend()
    {
        $optionsToSend = $this->options;

        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // Ask the cloud service to do it, rather than using what we came up with.
            $optionsToSend['quality'] = 'auto';
        } else {
            $optionsToSend['quality'] = $this->getCalculatedQuality();
        }

        // The following are unset for security reasons.
        unset($optionsToSend['converters']);
        unset($optionsToSend['secret']);
        unset($optionsToSend['api-key']);
        unset($optionsToSend['api-url']);

        $apiVersion = $optionsToSend['api-version'];

        if ($apiVersion == 1) {
            // Lossless can be "auto" in api 2, but in api 1 "auto" is not supported
            //unset($optionsToSend['lossless']);
        } elseif ($apiVersion == 2) {
            //unset($optionsToSend['png']);
            //unset($optionsToSend['jpeg']);

            // The following are unset for security reasons.
            unset($optionsToSend['cwebp-command-line-options']);
            unset($optionsToSend['command-line-options']);
        }

        return $optionsToSend;
    }

    private function createPostData()
    {
        $options = $this->options;

        $postData = [
            'file' => curl_file_create($this->source),
            'options' => json_encode($this->createOptionsToSend()),
            'servername' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
        ];

        $apiVersion = $options['api-version'];

        $apiKey = $this->getApiKey();

        if ($apiVersion == 0) {
            $postData['hash'] = md5(md5_file($this->source) . $apiKey);
        } elseif ($apiVersion == 1) {
            //$this->logLn('api key: ' . $apiKey);

            if ($options['crypt-api-key-in-transfer']) {
                $salt = self::createRandomSaltForBlowfish();
                $postData['salt'] = $salt;

                // Strip off the first 28 characters (the first 6 are always "$2y$10$". The next 22 is the salt)
                $postData['api-key-crypted'] = substr(crypt($apiKey, '$2y$10$' . $salt . '$'), 28);
            } else {
                $postData['api-key'] = $apiKey;
            }
        }
        return $postData;
    }

    protected function doActualConvert()
    {
        $ch = self::initCurl();

        //$this->logLn('api url: ' . $this->getApiUrl());

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->getApiUrl(),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $this->createPostData(),
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->logLn('Curl error: ', 'bold');
            $this->logLn(curl_error($ch));
            throw new ConverterNotOperationalException('Curl error:');
        }

        // Check if we got a 404
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            curl_close($ch);
            throw new ConversionFailedException(
                'WPC was not found at the specified URL - we got a 404 response.'
            );
        }

        // Check for empty response
        if (empty($response)) {
            throw new ConversionFailedException(
                'Error: Unexpected result. We got nothing back. ' .
                    'HTTP CODE: ' . $httpCode . '. ' .
                    'Content type:' . curl_getinfo($ch, CURLINFO_CONTENT_TYPE)
            );
        };

        // The WPC cloud service either returns an image or an error message
        // Images has application/octet-stream.
        // Verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);

            if (substr($response, 0, 1) == '{') {
                $responseObj = json_decode($response, true);
                if (isset($responseObj['errorCode'])) {
                    switch ($responseObj['errorCode']) {
                        case 0:
                            throw new ConverterNotOperationalException(
                                'There are problems with the server setup: "' .
                                $responseObj['errorMessage'] . '"'
                            );
                        case 1:
                            throw new InvalidApiKeyException(
                                'Access denied. ' . $responseObj['errorMessage']
                            );
                        default:
                            throw new ConversionFailedException(
                                'Conversion failed: "' . $responseObj['errorMessage'] . '"'
                            );
                    }
                }
            }

            // WPC 0.1 returns 'failed![error messag]' when conversion fails. Handle that.
            if (substr($response, 0, 7) == 'failed!') {
                throw new ConversionFailedException(
                    'WPC failed converting image: "' . substr($response, 7) . '"'
                );
            }

            $this->logLn('Bummer, we did not receive an image');
            $this->log('What we received starts with: "');
            $this->logLn(
                str_replace("\r", '', str_replace("\n", '', htmlentities(substr($response, 0, 400)))) . '..."'
            );

            throw new ConversionFailedException('Unexpected result. We did not receive an image but something else.');
            //throw new ConverterNotOperationalException($response);
        }

        $success = file_put_contents($this->destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConversionFailedException('Error saving file. Check file permissions');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConversionSkippedException extends ConversionFailedException
{
    public $description = 'The converter declined converting';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConverterNotOperationalException extends ConversionFailedException
{
    public $description = 'The converter is not operational';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class FileSystemProblemsException extends ConversionFailedException
{
    public $description = 'Filesystem problems';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class InvalidInputException extends ConversionFailedException
{
    public $description = 'Invalid input';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

class InvalidApiKeyException extends ConverterNotOperationalException
{
    public $description = 'The converter is not operational (access denied)';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

class SystemRequirementsNotMetException extends ConverterNotOperationalException
{
    public $description = 'The converter is not operational (system requirements not met)';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems;

use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblemsException;

class CreateDestinationFileException extends FileSystemProblemsException
{
    public $description = 'The converter could not create destination file. Check file permisions!';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems;

use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblemsException;

class CreateDestinationFolderException extends FileSystemProblemsException
{
    public $description = 'The converter could not create destination folder. Check file permisions!';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class ConverterNotFoundException extends InvalidInputException
{
    public $description = 'The converter does not exist.';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class InvalidImageTypeException extends InvalidInputException
{
    public $description = 'The converter does not handle the supplied image type';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class TargetNotFoundException extends InvalidInputException
{
    public $description = 'The converter could not locate source file';
}

?><?php

namespace WebPConvert\Convert\Helpers;

/**
 * Try to detect quality of a jpeg image using various tools.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class JpegQualityDetector
{

    /**
     * Try to detect quality of jpeg using imagick extension.
     *
     * Note that the detection might fail for two different reasons:
     * 1) Imagick is not installed
     * 2) Imagick for some reason fails to detect quality for some images
     *
     * In both cases, null is returned.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImagick($filename)
    {
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            try {
                $img = new \Imagick($filename);

                // The required function is available as from PECL imagick v2.2.2
                if (method_exists($img, 'getImageCompressionQuality')) {
                    $quality = $img->getImageCompressionQuality();
                    if ($quality === 0) {
                        // We have experienced that this Imagick method returns 0 for some images,
                        // (even though the imagemagick binary is able to detect the quality)
                        // ie "/test/images/quality-undetectable-with-imagick.jpg". See #208
                        $quality = null;
                    }
                    return $quality;
                }
            } catch (\Exception $e) {
                // Well well, it just didn't work out.
                // - But perhaps next method will work...
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg using imagick binary.
     *
     * Note that the detection might fail for three different reasons:
     * 1) exec function is not available
     * 2) the 'identify' command is not available on the system
     * 3) imagemagick for some reason fails to detect quality for some images
     *
     * In the first two cases, null is returned.
     * In the third case, 92 is returned. This is what imagemagick returns when it cannot detect the quality.
     *    and unfortunately we cannot distinguish between the situation where the quality is undetectable
     *    and the situation where the quality is actually 92 (at least, I have not found a way to do so)
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImageMagick($filename)
    {
        if (function_exists('exec')) {
            // Try Imagick using exec, and routing stderr to stdout (the "2>$1" magic)
            exec("identify -format '%Q' " . escapeshellarg($filename) . " 2>&1", $output, $returnCode);
            //echo 'out:' . print_r($output, true);
            if ((intval($returnCode) == 0) && (is_array($output)) && (count($output) == 1)) {
                return intval($output[0]);
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg using graphicsmagick binary.
     *
     * It seems that graphicsmagick is never able to detect the quality! - and always returns
     * the default quality, which is 75.
     * However, as this might be solved in future versions, the method might be useful one day.
     * But we treat "75" as a failure to detect and shall return null in that case.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingGraphicsMagick($filename)
    {
        if (function_exists('exec')) {
            // Try GraphicsMagick
            exec("gm identify -format '%Q' " . escapeshellarg($filename) . " 2>&1", $output, $returnCode);
            if ((intval($returnCode) == 0) && (is_array($output)) && (count($output) == 1)) {
                $quality = intval($output[0]);

                // It seems that graphicsmagick is (currently) never able to detect the quality!
                // - and always returns 75 as a fallback
                // We shall therefore treat 75 as a failure to detect. (#209)
                if ($quality == 75) {
                    return null;
                }
                return $quality;
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg.
     *
     * Note: This method does not throw errors, but might dispatch warnings.
     * You can use the WarningsIntoExceptions class if it is critical to you that nothing gets "printed"
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    public static function detectQualityOfJpg($filename)
    {

        //trigger_error('warning test', E_USER_WARNING);

        // Test that file exists in order not to break things.
        if (!file_exists($filename)) {
            // One could argue that it would be better to throw an Exception...?
            return null;
        }

        // Try Imagick extension, if available
        $quality = self::detectQualityOfJpgUsingImagick($filename);

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingImageMagick($filename);
        }

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingGraphicsMagick($filename);
        }

        return $quality;
    }
}

?><?php

namespace WebPConvert\Convert\Helpers;

/**
 * Get/parse shorthandsize strings from php.ini as bytes.
 *
 * Parse strings like "1k" into bytes (1024).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class PhpIniSizes
{

    /**
     * Parse a shordhandsize string as the ones returned by ini_get()
     *
     * Parse a shorthandsize string having the syntax allowed in php.ini and returned by ini_get().
     * Ie "1K" => 1024.
     * Strings without units are also accepted.
     * The shorthandbytes syntax is described here: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param  string  $shortHandSize  A size string of the type returned by ini_get()
     * @return float|false  The parsed size (beware: it is float, do not check high numbers for equality),
     *                      or false if parse error
     */
    public static function parseShortHandSize($shortHandSize)
    {

        $result = preg_match("#^\\s*(\\d+(?:\\.\\d+)?)([bkmgtpezy]?)\\s*$#i", $shortHandSize, $matches);
        if ($result !== 1) {
            return false;
        }

        // Truncate, because that is what php does.
        $digitsValue = floor($matches[1]);

        if ((count($matches) >= 3) && ($matches[2] != '')) {
            $unit = $matches[2];

            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            $position = stripos('bkmgtpezy', $unit);

            return floatval($digitsValue * pow(1024, $position));
        } else {
            return $digitsValue;
        }
    }

    /*
    * Get the size of an php.ini option.
    *
    * Calls ini_get() and parses the size to a number.
    * If the configuration option is null, does not exist, or cannot be parsed as a shorthandsize, false is returned
    *
    * @param  string  $varname  The configuration option name.
    * @return float|false  The parsed size or false if the configuration option does not exist
    */
    public static function getIniBytes($iniVarName)
    {
        $iniVarValue = ini_get($iniVarName);
        if (($iniVarValue == '') || $iniVarValue === false) {
            return false;
        }
        return self::parseShortHandSize($iniVarValue);
    }
}

?><?php

namespace WebPConvert\Convert;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Converters\AbstractConverter;

/**
 * Make converters from their ids.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ConverterFactory
{
    /**
     * Get classname of a converter (by id)
     *
     * @param  string  $converterId    Id of converter (ie "cwebp")
     *
     * @throws ConverterNotFoundException  If there is no converter with that id.
     * @return string  Fully qualified class name of converter
     */
    public static function converterIdToClassname($converterId)
    {
        switch ($converterId) {
            case 'imagickbinary':
                $classNameShort = 'ImagickBinary';
                break;
            case 'imagemagick':
                $classNameShort = 'ImageMagick';
                break;
            case 'gmagickbinary':
                $classNameShort = 'GmagickBinary';
                break;
            case 'graphicsmagick':
                $classNameShort = 'GraphicsMagick';
                break;
            default:
                $classNameShort = ucfirst($converterId);
        }
        $className = 'WebPConvert\\Convert\\Converters\\' . $classNameShort;
        if (is_callable([$className, 'convert'])) {
            return $className;
        } else {
            throw new ConverterNotFoundException('There is no converter with id:' . $converterId);
        }
    }

    /**
     * Make a converter instance by class name.
     *
     * @param  string  $converterClassName    Fully qualified class name
     * @param  string  $source                The path to the file to convert
     * @param  string  $destination           The path to save the converted file to
     * @param  array   $options               (optional)
     * @param  \WebPConvert\Loggers\BaseLogger   $logger       (optional)
     *
     * @throws ConverterNotFoundException  If the specified converter class isn't found
     * @return AbstractConverter  An instance of the specified converter
     */
    public static function makeConverterFromClassname(
        $converterClassName,
        $source,
        $destination,
        $options = [],
        $logger = null
    ) {
        if (!is_callable([$converterClassName, 'convert'])) {
            throw new ConverterNotFoundException(
                'There is no converter with class name:' . $converterClassName . ' (or it is not a converter)'
            );
        }
        //$converter = new $converterClassName($source, $destination, $options, $logger);

        return call_user_func(
            [$converterClassName, 'createInstance'],
            $source,
            $destination,
            $options,
            $logger
        );
    }

    /**
     * Make a converter instance by either id or class name.
     *
     * @param  string  $converterIdOrClassName   Either a converter ID or a fully qualified class name
     * @param  string  $source                   The path to the file to convert
     * @param  string  $destination              The path to save the converted file to
     * @param  array   $options                  (optional)
     * @param  \WebPConvert\Loggers\BaseLogger   $logger       (optional)
     *
     * @throws ConverterNotFoundException  If the specified converter class isn't found
     * @return AbstractConverter  An instance of the specified converter
     */
    public static function makeConverter($converterIdOrClassName, $source, $destination, $options = [], $logger = null)
    {
        // We take it that all lowercase means it is an id rather than a class name
        if (strtolower($converterIdOrClassName) == $converterIdOrClassName) {
            $converterClassName = self::converterIdToClassname($converterIdOrClassName);
        } else {
            $converterClassName = $converterIdOrClassName;
        }

        return self::makeConverterFromClassname($converterClassName, $source, $destination, $options, $logger);
    }
}

?><?php

namespace WebPConvert\Helpers;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

use WebPConvert\Exceptions\InvalidInputException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;

/**
 * Get MimeType, results cached by path.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.6
 */
class MimeType
{
    private static $cachedDetections = [];

    /**
     * Get mime type for image (best guess).
     *
     * It falls back to using file extension. If that fails too, false is returned
     *
     * @return  string|false|null mimetype (if it is an image, and type could be determined / guessed),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    public static function getMimeTypeDetectionResult($absFilePath)
    {
        PathChecker::checkAbsolutePathAndExists($absFilePath);

        if (isset(self::$cachedDetections[$absFilePath])) {
            return self::$cachedDetections[$absFilePath];
        }
        $cachedDetections[$absFilePath] = ImageMimeTypeGuesser::lenientGuess($absFilePath);
        return $cachedDetections[$absFilePath];
    }
}

?><?php

namespace WebPConvert\Loggers;

/**
 * Base for all logger classes.
 *
 * WebPConvert can provide insights into the conversion process by means of accepting a logger which
 * extends this class.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class BaseLogger
{
    /**
     * Write a message to the log
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
    */
    abstract public function log($msg, $style = '');

    /**
     * Add new line to the log
     * @return void
     */
    abstract public function ln();

    /**
     * Write a line to the log
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
    */
    public function logLn($msg, $style = '')
    {
        $this->log($msg, $style);
        $this->ln();
    }
}

?><?php

namespace WebPConvert\Loggers;

use WebPConvert\Loggers\BaseLogger;

/**
 * Collect the logging and retrieve it later in HTML or plain text format.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class BufferLogger extends BaseLogger
{
    public $entries = array();

    /**
     * Write a message to the buffer - all entries can later be retrieved with getText() or getHtlm().
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
    */
    public function log($msg, $style = '')
    {
        $this->entries[] = [$msg, $style];
    }

    /**
     * Write a new line to the buffer.
     *
     * @return void
     */
    public function ln()
    {
        $this->entries[] = '';
    }

    /**
     * Get everything logged - as HTML.
     *
     * @return string  The log, formatted as HTML.
     */
    public function getHtml()
    {
        $html = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $html .= '<br>';
            } else {
                list($msg, $style) = $entry;
                $msg = htmlspecialchars($msg);
                if ($style == 'bold') {
                    $html .= '<b>' . $msg . '</b>';
                } elseif ($style == 'italic') {
                    $html .= '<i>' . $msg . '</i>';
                } else {
                    $html .= $msg;
                }
            }
        }
        return $html;
    }

    /**
     * Get everything logged - as markdown.
     *
     * @return string  The log, formatted as MarkDown.
     */
    public function getMarkDown($newLineChar = "\n\r")
    {
        $md = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $md .= $newLineChar;
            } else {
                list($msg, $style) = $entry;
                if ($style == 'bold') {
                    $md .= '**' . $msg . '** ';
                } elseif ($style == 'italic') {
                    $md .= '*' . $msg . '* ';
                } else {
                    $md .= $msg;
                }
            }
        }
        return $md;
    }

    /**
     * Get everything logged - as plain text.
     *
     * @param  string  $newLineChar. The character used for new lines.
     * @return string  The log, formatted as plain text.
     */
    public function getText($newLineChar = ' ')
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {  // empty string means new line
                if (substr($text, -2) != '.' . $newLineChar) {
                    $text .= '.' . $newLineChar;
                }
            } else {
                list($msg, $style) = $entry;
                $text .= $msg;
            }
        }

        return $text;
    }
}

?><?php

namespace WebPConvert\Loggers;

/**
 * Echo the logs immediately (in HTML)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class EchoLogger extends BaseLogger
{

    /**
     * Handle log() by echoing the message.
     *
     * @param  string  $msg     message to log
     * @param  string  $style   style (null | bold | italic)
     * @return void
    */
    public function log($msg, $style = '')
    {
        $msg = htmlspecialchars($msg);
        if ($style == 'bold') {
            echo '<b>' . $msg . '</b>';
        } elseif ($style == 'italic') {
            echo '<i>' . $msg . '</i>';
        } else {
            echo $msg;
        }
    }

    /**
     * Handle ln by echoing a <br> tag.
     *
     * @return void
    */
    public function ln()
    {
        echo '<br>';
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Loggers\EchoLogger;
use WebPConvert\WebPConvert;

/**
 * Class for generating a HTML report of converting an image.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Report
{
    public static function convertAndReport($source, $destination, $options)
    {
        InputValidator::checkSourceAndDestination($source, $destination);
        ?>
<html>
    <head>
        <style>td {vertical-align: top} table {color: #666}</style>
        <script>
            function showOptions(elToHide) {
                document.getElementById('options').style.display='block';
                elToHide.style.display='none';
            }
        </script>
    </head>
    <body>
        <table>
            <tr><td><i>source:</i></td><td><?php echo htmlentities($source) ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo htmlentities($destination) ?><td></tr>
        </table>
        <br>
        <?php
        try {
            $echoLogger = new EchoLogger();
            $options['log-call-arguments'] = true;
            WebPConvert::convert($source, $destination, $options['convert'], $echoLogger);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            echo '<b>' . $msg . '</b>';

            //echo '<p>Rethrowing exception for your convenience</p>';
            //throw ($e);
        }
        ?>
    </body>
    </html>
        <?php
    }
}

?><?php

namespace WebPConvert\Serve\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class ServeFailedException extends WebPConvertException
{
    public $description = 'Failed serving';
}

