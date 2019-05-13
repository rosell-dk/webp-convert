<?php

// TODO:
// Read this: https://sourcemaking.com/design_patterns/strategy

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\UnhandledException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Converters\BaseTraits\AutoQualityTrait;
use WebPConvert\Convert\Converters\BaseTraits\LoggerTrait;
use WebPConvert\Convert\Converters\BaseTraits\OptionsTrait;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
use WebPConvert\Loggers\BaseLogger;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

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
    use LoggerTrait;
    use OptionsTrait;
    use WarningLoggerTrait;

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
     * PS: Converters that supports lossless encoding all use the LosslessAutoTrait, which
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

    /** @var string|false|null  Where to save the webp (complete path) */
    private $sourceMimeType;

    /** @var array  Array of allowed mime types for source.  */
    public static $allowedMimeTypes = ['image/jpeg', 'image/png'];

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
        $this->source = $source;
        $this->destination = $destination;

        $this->setLogger($logger);
        $this->setProvidedOptions($options);
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
     *  Default display name is simply the class name (short).
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
        $beginTime = microtime(true);

        $this->activateWarningLogger();
        //set_error_handler(array($this, "errorHandler"));

        try {
            // Prepare options
            //$this->prepareOptions();

            $this->checkOptions();

            // Prepare destination folder
            $this->createWritableDestinationFolder();
            $this->removeExistingDestinationIfExists();

            if (!isset($this->options['_skip_input_check'])) {
                // Run basic input validations (if source exists and if file extension is valid)
                $this->checkInput();

                // Check that a file can be written to destination
                $this->checkFileSystem();
            }

            $this->checkOperationality();
            $this->checkConvertability();
            $this->runActualConvert();
        } catch (ConversionFailedException $e) {
            $this->deactivateWarningLogger();
            throw $e;
        } catch (\Exception $e) {
            $this->deactivateWarningLogger();
            throw new UnhandledException('Conversion failed due to uncaught exception', 0, $e);
        } catch (\Error $e) {
            $this->deactivateWarningLogger();
            // https://stackoverflow.com/questions/7116995/is-it-possible-in-php-to-prevent-fatal-error-call-to-undefined-function
            //throw new UnhandledException('Conversion failed due to uncaught error', 0, $e);
            throw $e;
        }
        $this->deactivateWarningLogger();

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
                $msg = 'Converted image in ' .
                    round((microtime(true) - $beginTime) * 1000) . ' ms';

                $sourceSize = @filesize($source);
                if ($sourceSize !== false) {
                    $msg .= ', reducing file size with ' .
                        round((filesize($source) - filesize($destination))/filesize($source) * 100) . '% ';

                    if ($sourceSize < 10000) {
                        $msg .= '(went from ' . round(filesize($source)) . ' bytes to ';
                        $msg .= round(filesize($destination)) . ' bytes)';
                    } else {
                        $msg .= '(went from ' . round(filesize($source)/1024) . ' kb to ';
                        $msg .= round(filesize($destination)/1024) . ' kb)';
                    }
                }
                $this->logLn($msg);
            }
        }
    }

    /**
     * Runs the actual conversion (after setup and checks)
     * Simply calls the doActualConvert() of the actual converter.
     * However, in the LosslessAutoTrait, this method is overridden to make two conversions
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
        $instance = self::createInstance($source, $destination, $options, $logger);
        $instance->doConvert();
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
        if (!isset($this->sourceMimeType)) {
            $this->sourceMimeType = ImageMimeTypeGuesser::lenientGuess($this->source);
        }
        return $this->sourceMimeType;
    }

    /**
     *  Note: As the input validations are only run one time in a stack,
     *  this method is not overridable
     */
    private function checkInput()
    {
        // Check if source exists
        if (!@file_exists($this->source)) {
            throw new TargetNotFoundException('File or directory not found: ' . $this->source);
        }

        // Check if the provided file's mime type is valid
        $fileMimeType = $this->getMimeTypeOfSource();
        if (is_null($fileMimeType)) {
            throw new InvalidImageTypeException('Image type could not be detected');
        } elseif ($fileMimeType === false) {
            throw new InvalidImageTypeException('File seems not to be an image.');
        } elseif (!in_array($fileMimeType, self::$allowedMimeTypes)) {
            throw new InvalidImageTypeException('Unsupported mime type: ' . $fileMimeType);
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
    private function checkFileSystem()
    {
        $dirName = dirname($this->destination);

        if (@is_writable($dirName) && @is_executable($dirName)) {
            // all is well
            return;
        }

        // The above might fail on Windows, even though dir is writable
        // So, to be absolute sure that we cannot write, we make an actual write test (writing a dummy file)
        // No harm in doing that for non-Windows systems either.
        if (file_put_contents($this->destination, 'dummy') !== false) {
            // all is well, after all
            unlink($this->destination);
            return;
        }

        throw new CreateDestinationFileException(
            'Cannot create file: ' . basename($this->destination) . ' in dir:' . dirname($this->destination)
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
        if (file_exists($this->destination)) {
            // A file already exists in this folder...
            // We delete it, to make way for a new webp
            if (!unlink($this->destination)) {
                throw new CreateDestinationFileException(
                    'Existing file cannot be removed: ' . basename($this->destination)
                );
            }
        }
    }

    /**
     * Create writable folder in provided path (if it does not exist already)
     *
     * @throws CreateDestinationFolderException  if folder cannot be removed
     * @return void
     */
    private function createWritableDestinationFolder()
    {
        $filePath = $this->destination;

        $folder = dirname($filePath);
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
}
