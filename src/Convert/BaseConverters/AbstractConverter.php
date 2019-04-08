<?php

// TODO:
// Read this: https://sourcemaking.com/design_patterns/strategy

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\UnhandledException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\BaseConverters\BaseTraits\AutoQualityTrait;
use WebPConvert\Convert\BaseConverters\BaseTraits\LoggerTrait;
use WebPConvert\Loggers\BaseLogger;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

abstract class AbstractConverter
{
    use AutoQualityTrait;
    use LoggerTrait;

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

    // The following must be defined in all actual converters.
    // Unfortunately properties cannot be declared abstract. TODO: We need to change to using method instead.
    public static $extraOptions;

    /** @var string  The filename of the image to convert (complete path) */
    public $source;

    /** @var string  Where to save the webp (complete path) */
    public $destination;

    public $options;
    public $beginTime;
    public $sourceMimeType;
    public static $allowedMimeTypes = ['image/jpeg', 'image/png'];
    public static $defaultOptions = [
        'quality' => 'auto',
        'max-quality' => 85,
        'default-quality' => 75,
        'metadata' => 'none',
        'method' => 6,
        'low-memory' => false,
        'lossless' => false,
        'skip-pngs' => false,
    ];

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
    protected function checkOperationality()
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
    protected function checkConvertability()
    {
    }

    public function __construct($source, $destination, $options = [], $logger = null)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;

        $this->setLogger($logger);
    }

    /**
     *  Default display name is simply the class name (short).
     *  Converters can override this.
     */
    protected static function getConverterDisplayName()
    {
        // https://stackoverflow.com/questions/19901850/how-do-i-get-an-objects-unqualified-short-class-name/25308464
        return substr(strrchr('\\' . static::class, '\\'), 1);
    }

    public static function createInstance($source, $destination, $options = [], $logger = null)
    {
        return new static($source, $destination, $options, $logger);
    }

    /**
     *
     *
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
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
            E_USER_ERROR =>          "User Error",
            E_USER_WARNING =>        "User Warning",
            E_USER_NOTICE =>         "User Notice",
            E_STRICT =>              "Strict Notice",
            E_DEPRECATED =>          "Deprecated",
            E_USER_DEPRECATED =>     "User Deprecated",

            /*
            The following can never be catched by a custom error handler:
            E_PARSE =>               "Parse Error",
            E_ERROR =>               "Error",
            E_CORE_ERROR =>          "Core Error",
            E_CORE_WARNING =>        "Core Warning",
            E_COMPILE_ERROR =>       "Compile Error",
            E_COMPILE_WARNING =>     "Compile Warning",
            */
        ];

        if (isset($errorTypes[$errno])) {
            $errType = $errorTypes[$errno];
        } else {
            $errType = "Unknown error ($errno)";
        }

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION .
            ' (' . PHP_OS . ')';
        //$this->logLn($msg);

        /*
        if(function_exists('debug_backtrace')){
            //print "backtrace:\n";
            $backtrace = debug_backtrace();
            array_shift($backtrace);
            foreach($backtrace as $i=>$l){
                $msg = '';
                $msg .= "[$i] in function <b>{$l['class']}{$l['type']}{$l['function']}</b>";
                if($l['file']) $msg .= " in <b>{$l['file']}</b>";
                if($l['line']) $msg .= " on line <b>{$l['line']}</b>";
                $this->logLn($msg);

            }
        }
        */
        $this->logLn($msg);

        if ($errno == E_USER_ERROR) {
            // trigger error.
            // unfortunately, we can only catch user errors
            throw new ConversionFailedException('Uncaught error in converter', $msg);
        }

        // We do not return false, because we want to keep this little secret.
        //
        //return false;   // let PHP handle the error from here
    }

    //$instance->logLn($instance->getConverterDisplayName() . ' converter ignited');
    //$instance->logLn(self::getConverterDisplayName() . ' converter ignited');

    public function doConvert()
    {
        $this->beginTime = microtime(true);

        //set_error_handler(array($this, "warningHandler"), E_WARNING);
        set_error_handler(array($this, "errorHandler"));

        try {
            // Prepare options
            $this->prepareOptions();

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
            $this->doActualConvert();
        } catch (ConversionFailedException $e) {
            restore_error_handler();
            throw $e;
        } catch (\Exception $e) {
            restore_error_handler();
            throw new UnhandledException('Conversion failed due to uncaught exception', 0, $e);
        } catch (\Error $e) {
            restore_error_handler();
            // https://stackoverflow.com/questions/7116995/is-it-possible-in-php-to-prevent-fatal-error-call-to-undefined-function
            throw new UnhandledException('Conversion failed due to uncaught error', 0, $e);
        }
        restore_error_handler();

        $source = $this->source;
        $destination = $this->destination;

        if (!@file_exists($destination)) {
            throw new ConversionFailedException('Destination file is not there: ' . $destination);
        } elseif (@filesize($destination) === 0) {
            @unlink($destination);
            throw new ConversionFailedException('Destination file was completely empty');
        } else {
            if (!isset($this->options['_suppress_success_message'])) {
                $this->ln();
                $msg = 'Successfully converted image in ' .
                    round((microtime(true) - $this->beginTime) * 1000) . ' ms';

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

    /**
     * Prepare options.
     */
    private function prepareOptions()
    {
        $defaultOptions = self::$defaultOptions;

        // -  Merge defaults of the converters extra options into the standard default options.
        $defaultOptions = array_merge($defaultOptions, array_column(static::$extraOptions, 'default', 'name'));

        // -  Merge $defaultOptions into provided options
        $this->options = array_merge($defaultOptions, $this->options);

        if ($this->getMimeTypeOfSource() == 'png') {
            // skip png's ?
            if ($this->options['skip-pngs']) {
                throw new ConversionDeclinedException(
                    'PNG file skipped (configured to do so)'
                );
            }

            // Force lossless option to true for PNG images
            $this->options['lossless'] = true;
        }

        // TODO: Here we could test if quality is 0-100 or auto.
        //       and if not, throw something extending InvalidArgumentException (which is a LogicException)
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
