<?php

// TODO:
// Read this: https://sourcemaking.com/design_patterns/strategy

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\UnhandledException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\BaseConverters\BaseTraits\AutoQualityTrait;
use WebPConvert\Convert\BaseConverters\BaseTraits\LoggerTrait;
use WebPConvert\Convert\BaseConverters\BaseTraits\OptionsTrait;
use WebPConvert\Convert\BaseConverters\BaseTraits\WarningLoggerTrait;
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
     * The actual conversion must be done by a concrete class.
     *
     * At the stage this method is called, the abstract converter has taken preparational steps.
     * - It has created the destination folder (if neccesary)
     * - It has checked the input (valid mime type)
     * - It has set up an error handler, mostly in order to catch and log warnings during the doConvert fase
     *
     * Note: This method is not meant to be called from the outside. Use the *convert* method for that.
     *
     */
    abstract protected function doActualConvert();

    /**
     *  Set to false for converters that should hand the lossless option over (stack and wpc)
     */
    protected $processLosslessAuto = true;

    /**
     *  Set this on all converters (unfortunately, properties cannot be declared abstract)
     */
    protected $supportsLossless = false;


    /** @var string  The filename of the image to convert (complete path) */
    public $source;

    /** @var string  Where to save the webp (complete path) */
    public $destination;

    /** @var string  Where to save the webp (complete path) */
    private $sourceMimeType;

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

    public function __construct($source, $destination, $options = [], $logger = null)
    {
        $this->source = $source;
        $this->destination = $destination;

        $this->setLogger($logger);
        $this->setProvidedOptions($options);
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

    //$instance->logLn($instance->getConverterDisplayName() . ' converter ignited');
    //$instance->logLn(self::getConverterDisplayName() . ' converter ignited');

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


    private function runActualConvert()
    {
        if ($this->processLosslessAuto && ($this->options['lossless'] === 'auto') && $this->supportsLossless) {
            $destination = $this->destination;
            $destinationLossless =  $this->destination . '.lossless.webp';
            $destinationLossy =  $this->destination . '.lossy.webp';

            $this->logLn(
                'Lossless is set to auto. Converting to both lossless and lossy and selecting the smallest file'
            );


            $this->ln();
            $this->logLn('Converting to lossy');
            $this->destination = $destinationLossy;
            $this->options['lossless'] = false;
            $this->doActualConvert();
            $this->logLn('Reduction: ' .
                round((filesize($this->source) - filesize($this->destination))/filesize($this->source) * 100) . '% ');

            $this->ln();
            $this->logLn('Converting to lossless');
            $this->destination = $destinationLossless;
            $this->options['lossless'] = true;
            $this->doActualConvert();
            $this->logLn('Reduction: ' .
                round((filesize($this->source) - filesize($this->destination))/filesize($this->source) * 100) . '% ');

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
            $this->destination = $destination;
        } else {
            $this->doActualConvert();
        }
    }

    /**
     * Convert an image to webp.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for conversion
     * @param   \WebPConvert\Loggers\BaseLogger $logger (optional)
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
     * @return  string|false
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
        if ($fileMimeType === false) {
            throw new InvalidImageTypeException('Image type could not be detected');
        } elseif (!in_array($fileMimeType, self::$allowedMimeTypes)) {
            throw new InvalidImageTypeException('Unsupported mime type: ' . $fileMimeType);
        }
    }

    private function checkFileSystem()
    {
        // TODO:
        // Instead of creating dummy file,
        // perhaps something like this ?
        // if (@is_writable($dirName) && @is_executable($dirName) || self::isWindows() )
        // Or actually, probably best with a mix.
        // First we test is_writable and is_executable. If that fails and we are on windows, we can do the dummy
        // function isWindows(){
        // return (boolean) preg_match('/^win/i', PHP_OS);
        //}

        // Try to create a dummy file here, with that name, just to see if it is possible (we delete it again)
        file_put_contents($this->destination, '');
        if (file_put_contents($this->destination, '') === false) {
            throw new CreateDestinationFileException(
                'Cannot create file: ' . basename($this->destination) . ' in dir:' . dirname($this->destination)
            );
        }
        unlink($this->destination);
    }

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

    // Creates folder in provided path & sets correct permissions
    // also deletes the file at filePath (if it already exists)
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
                throw new CreateDestinationFolderException('Failed creating folder: ' . $folder);
            }
        }
    }
}
