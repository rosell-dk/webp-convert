<?php 





?><?php

namespace WebPConvert\Convert\Converters\AbstractConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;

abstract class AbstractCloudConverter extends AbstractConverter
{
    /**
     * Parse a shordhandsize string as the ones returned by ini_get()
     *
     * Parse a shorthandsize string having the syntax allowed in php.ini and returned by ini_get().
     * Ie "1K" => 1024.
     * Strings without units are also accepted.
     * The shorthandbytes syntax is described here: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param  string  $size  A size string of the type returned by ini_get()
     * @return float|false  The parsed size (beware: it is float, do not check high numbers for equality),
     *                      or false if parse error
     */
    protected static function parseShortHandSize($shortHandSize)
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
    protected static function getIniBytes($iniVarName)
    {
        $iniVarValue = ini_get($iniVarName);
        if (($iniVarValue == '') || $iniVarValue === false) {
            return false;
        }
        return self::parseShortHandSize($iniVarValue);
    }

    /**
     *  Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini
     *
     * @throws  ConversionFailedException  if filesize is larger than "upload_max_filesize" or "post_max_size"
     * @return  void
     */
    protected function testFilesizeRequirements()
    {
        $fileSize = @filesize($this->source);
        if ($fileSize !== false) {
            $uploadMaxSize = self::getIniBytes('upload_max_filesize');
            if ($uploadMaxSize === false) {
                // Not sure if we should throw an exception here, or not...
            } elseif ($uploadMaxSize < $fileSize) {
                throw new ConversionFailedException(
                    'File is larger than your max upload (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'upload_max_filesize in php.ini: ' . ini_get('upload_max_filesize') .
                        ' (parsed as ' . round($uploadMaxSize/1024) . ' kb)'
                );
            }

            $postMaxSize = self::getIniBytes(ini_get('post_max_size'));
            if ($postMaxSize === false) {
                // Not sure if we should throw an exception here, or not...
            } elseif ($postMaxSize < $fileSize) {
                throw new ConversionFailedException(
                    'File is larger than your post_max_size limit (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'post_max_size in php.ini: ' . ini_get('post_max_size') .
                        ' (parsed as ' . round($postMaxSize/1024) . ' kb)'
                );
            }

            // Should we worry about memory limit as well?
            // ini_get('memory_limit')
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     */
    protected function checkConvertability()
    {
        $this->testFilesizeRequirements();
    }
}

?><?php

namespace WebPConvert\Convert\Converters\AbstractConverters;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;

abstract class AbstractCloudCurlConverter extends AbstractCloudConverter
{

    /**
     * Check basis operationality for converters relying on curl
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    protected function checkOperationality()
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
     *  Init curl.
     *
     * @throws  SystemRequirementsNotMetException  if curl could not be initialized
     * @return  resource  curl handle
     */
    public static function initCurl()
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

namespace WebPConvert\Convert\Converters\AbstractConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\UnhandledException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Helpers\JpegQualityDetector;
use WebPConvert\Loggers\BaseLogger;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

abstract class AbstractConverter
{
    /**
     * The actual conversion must be done by a concrete class.
     *
     */
    abstract protected function doConvert();

    public $source;
    public $destination;
    public $options;
    public $logger;
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
     * The method is called internally right before calling doConvert() method.
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
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;
        $this->logger = $logger;
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

    public static function createInstance($source, $destination, $options, $logger)
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
        We do not do the following on purpose.
        We want to log all warnings and errors (also the ones that was suppressed with @)
        https://secure.php.net/manual/en/language.operators.errorcontrol.php

        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }


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
        if ($errno == E_USER_ERROR) {
            // trigger error.
            // unfortunately, we can only catch user errors
            throw new ConversionFailedException($msg);
        } else {
            $this->logLn($msg);
        }


        // We do not return false, because we want to keep this little secret.
        //
        //return false;   // let PHP handle the error from here
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

        //$instance->logLn($instance->getConverterDisplayName() . ' converter ignited');
        $instance->logLn(self::getConverterDisplayName() . ' converter ignited');
        $instance->prepareConvert();
        try {
            $instance->checkOperationality();
            $instance->checkConvertability();
            $instance->doConvert();
        } catch (ConversionFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new UnhandledException('Conversion failed due to uncaught exception', 0, $e);
        } catch (\Error $e) {
            // https://stackoverflow.com/questions/7116995/is-it-possible-in-php-to-prevent-fatal-error-call-to-undefined-function
            throw new UnhandledException('Conversion failed due to uncaught error', 0, $e);
        }
        $instance->finalizeConvert();

        //echo $instance->id;
    }

    public function logLn($msg, $style = '')
    {
        $this->logger->logLn($msg, $style);
    }

    public function logLnLn($msg)
    {
        $this->logger->logLnLn($msg);
    }

    public function ln()
    {
        $this->logger->ln();
    }

    public function log($msg)
    {
        $this->logger->log($msg);
    }

    /**
     *  Get mime type for image (best guess)
     *  It falls back to using file extension.
     *  If that fails too, false is returned
     *
     *  PS: Is it a security risk to fall back on file extension?
     *  - By setting file extension to "jpg", one can lure our library into trying to convert a file, which isn't a jpg.
     *    hmm, seems very unlikely, though not unthinkable that one of the converters could be exploited
     */
    protected static function getMimeType($filePath)
    {
        return ImageMimeTypeGuesser::lenientGuess($filePath);
    }

    protected function getMimeTypeOfSource()
    {
        if (!isset($this->sourceMimeType)) {
            $this->sourceMimeType = self::getMimeType($this->source);
        }
        return $this->sourceMimeType;
    }

    private function prepareConvert()
    {
        $this->beginTime = microtime(true);

        //set_error_handler(array($this, "warningHandler"), E_WARNING);
        set_error_handler(array($this, "errorHandler"));

        if (!isset($this->options['_skip_basic_validations'])) {
            // Run basic validations (if source exists and if file extension is valid)
            $this->runBasicValidations();

            // Prepare destination folder (may throw exception)
            $this->createWritableDestinationFolder();
        }

        // Prepare options
        $this->prepareOptions();
    }

    /**
     *  Note: As the "basic" validations are only run one time in a stack,
     *  this method is not overridable
     */
    private function runBasicValidations()
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

        // Prepare quality option (sets "_calculated_quality" option)
        $this->processQualityOption();

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

    // Creates folder in provided path & sets correct permissions
    // also deletes the file at filePath (if it already exists)
    public function createWritableDestinationFolder()
    {
        $filePath = $this->destination;

        $folder = dirname($filePath);
        if (!@file_exists($folder)) {
            // TODO: what if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir

            // First, we have to figure out which permissions to set.
            // We want same permissions as parent folder
            // But which parent? - the parent to the first missing folder

            $parentFolders = explode('/', $folder);
            $poppedFolders = [];

            while (!(@file_exists(implode('/', $parentFolders))) && count($parentFolders) > 0) {
                array_unshift($poppedFolders, array_pop($parentFolders));
            }

            // Retrieving permissions of closest existing folder
            $closestExistingFolder = implode('/', $parentFolders);
            $permissions = @fileperms($closestExistingFolder) & 000777;
            $stat = @stat($closestExistingFolder);

            // Trying to create the given folder (recursively)
            if (!@mkdir($folder, $permissions, true)) {
                throw new CreateDestinationFolderException('Failed creating folder: ' . $folder);
            }

            // `mkdir` doesn't always respect permissions, so we have to `chmod` each created subfolder
            foreach ($poppedFolders as $subfolder) {
                $closestExistingFolder .= '/' . $subfolder;
                // Setting directory permissions
                if ($permissions !== false) {
                    @chmod($folder, $permissions);
                }
                if ($stat !== false) {
                    if (isset($stat['uid'])) {
                        @chown($folder, $stat['uid']);
                    }
                    if (isset($stat['gid'])) {
                        @chgrp($folder, $stat['gid']);
                    }
                }
            }
        }

        if (@file_exists($filePath)) {
            // A file already exists in this folder...
            // We delete it, to make way for a new webp
            if (!@unlink($filePath)) {
                throw new CreateDestinationFileException(
                    'Existing file cannot be removed: ' . basename($filePath)
                );
            }
        }

        // Try to create a dummy file here, with that name, just to see if it is possible (we delete it again)
        @file_put_contents($filePath, '');
        if (@file_put_contents($filePath, '') === false) {
            throw new CreateDestinationFileException(
                'Cannot create file: ' . basename($filePath) . ' in dir:' . $folder
            );
        }
        @unlink($filePath);

        return true;
    }

    /**
     * Try to detect quality of jpeg.
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    public static function detectQualityOfJpg($filename)
    {
        return JpegQualityDetector::detectQualityOfJpg($filename);
    }

    /**
     *  Returns quality, as a number.
     *  If quality was set to auto, you get the detected quality / fallback quality, otherwise
     *  you get whatever it was set to.
     *  Use this, if you simply want quality as a number, and have no handling of "auto" quality
     */
    public function getCalculatedQuality()
    {
        return $this->options['_calculated_quality'];
    }

    public function isQualitySetToAutoAndDidQualityDetectionFail()
    {
        return isset($this->options['_quality_could_not_be_detected']);
    }

    public function processQualityOption()
    {
        if (isset($this->options['_calculated_quality'])) {
            return;
        }
        if ($this->options['quality'] == 'auto') {
            $q = self::detectQualityOfJpg($this->source);
            //$this->log('Quality set to auto... Quality of source: ');
            if (is_null($q)) {
                $q = $this->options['default-quality'];
                $this->logLn(
                    'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                    ' - Using default instead (' . $this->options['default-quality'] . ').'
                );

                // this allows the converter to know (by calling isQualitySetToAutoAndDidQualityDetectionFail())
                // that feature is btw used by wpc and imagick
                $this->options['_quality_could_not_be_detected'] = true;
            } else {
                if ($q > $this->options['max-quality']) {
                    $this->logLn(
                        'Quality of source is ' . $q . '. ' .
                        'This is higher than max-quality, so using that instead (' . $this->options['max-quality'] . ')'
                    );
                } else {
                    $this->logLn('Quality set to same as source: ' . $q);
                }
            }
            //$this->ln();
            $q = min($q, $this->options['max-quality']);

            $this->options['_calculated_quality'] = $q;
        //$this->logLn('Using quality: ' . $this->options['quality']);
        } else {
            $this->logLn(
                'Quality: ' . $this->options['quality'] . '. ' .
                'Consider setting quality to "auto" instead. It is generally a better idea'
            );
            $this->options['_calculated_quality'] = $this->options['quality'];
        }
        //$this->ln();
    }

    public function finalizeConvert()
    {
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
}

?><?php

namespace WebPConvert\Convert\Converters\AbstractConverters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

abstract class AbstractExecConverter extends AbstractConverter
{
    protected static function escapeFilename($string)
    {
        // Escaping whitespace
        $string = preg_replace('/\s/', '\\ ', $string);

        // filter_var() is should normally be available, but it is not always
        // - https://stackoverflow.com/questions/11735538/call-to-undefined-function-filter-var
        if (function_exists('filter_var')) {
            // Sanitize quotes
            $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

            // Stripping control characters
            // see https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
            $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        }

        return $string;
    }

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
     * Check basis operationality of exec converters.
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    protected function checkOperationality()
    {
        if (!function_exists('exec')) {
            throw new SystemRequirementsNotMetException('exec() is not enabled.');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractExecConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class Cwebp extends AbstractExecConverter
{
    public static $extraOptions = [
        [
            'name' => 'use-nice',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => false,
            'required' => false
        ],
        // low-memory is defined for all, in ConverterHelper
        [
            'name' => 'try-common-system-paths',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
        [
            'name' => 'try-supplied-binary-for-os',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
        [
            'name' => 'size-in-percentage',
            'type' => 'number',
            'sensitive' => false,
            'default' => null,
            'required' => false
        ],
        [
            'name' => 'command-line-options',
            'type' => 'string',
            'sensitive' => false,
            'default' => '',
            'required' => false
        ],
        [
            'name' => 'rel-path-to-precompiled-binaries',
            'type' => 'string',
            'sensitive' => false,
            'default' => './Binaries',
            'required' => false
        ],
    ];

    // System paths to look for cwebp binary
    private static $cwebpDefaultPaths = [
        '/usr/bin/cwebp',
        '/usr/local/bin/cwebp',
        '/usr/gnu/bin/cwebp',
        '/usr/syno/bin/cwebp'
    ];

    // OS-specific binaries included in this library, along with hashes
    // If other binaries are going to be added, notice that the first argument is what PHP_OS returns.
    // (possible values, see here: https://stackoverflow.com/questions/738823/possible-values-for-php-os)
    private static $suppliedBinariesInfo = [
        'WINNT' => [ 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'],
        'Darwin' => [ 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'],
        'SunOS' => [ 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'],
        'FreeBSD' => [ 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'],
        'Linux' => [ 'cwebp-linux', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568']
    ];

    /**
     * Check operationality of Cwebp converter.
     *
     */
     /*
    protected function checkOperationality()
    {
        parent::checkOperationality();
    }*/

    private static function executeBinary($binary, $commandOptions, $useNice, $logger)
    {
        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions;

        //$logger->logLn('command options:' . $commandOptions);
        //$logger->logLn('Trying to execute binary:' . $binary);
        exec($command, $output, $returnCode);
        //$logger->logLn(self::msgForExitCode($returnCode));
        return intval($returnCode);
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {
        $errorMsg = '';
        $options = $this->options;

        /*
         * Prepare cwebp options
         */

        $commandOptionsArray = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $commandOptionsArray[] = '-metadata ' . $options['metadata'];

        // Size
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource =  @filesize($this->source);
            if ($sizeSource !== false) {
                $targetSize = floor($sizeSource * $options['size-in-percentage'] / 100);
            }
        }
        if (isset($targetSize)) {
            $commandOptionsArray[] = '-size ' . $targetSize;
        } else {
            // Image quality
            $commandOptionsArray[] = '-q ' . $options['_calculated_quality'];
        }


        // Losless PNG conversion
        $commandOptionsArray[] = ($options['lossless'] ? '-lossless' : '');

        // Built-in method option
        $commandOptionsArray[] = '-m ' . strval($options['method']);

        // Built-in low memory option
        if ($options['low-memory']) {
            $commandOptionsArray[] = '-low_memory';
        }

        // command-line-options
        if ($options['command-line-options']) {
            $arr = explode(' -', ' ' . $options['command-line-options']);
            foreach ($arr as $cmdOption) {
                $pos = strpos($cmdOption, ' ');
                $cName = '';
                $cValue = '';
                if (!$pos) {
                    $cName = $cmdOption;
                    if ($cName == '') {
                        continue;
                    }
                    $commandOptionsArray[] = '-' . $cName;
                } else {
                    $cName = substr($cmdOption, 0, $pos);
                    $cValues = substr($cmdOption, $pos + 1);
                    $cValuesArr = explode(' ', $cValues);
                    foreach ($cValuesArr as &$cArg) {
                        $cArg = escapeshellarg($cArg);
                    }
                    $cValues = implode(' ', $cValuesArr);
                    $commandOptionsArray[] = '-' . $cName . ' ' . $cValues;
                }
            }
        }

        // Source file
        //$commandOptionsArray[] = self::escapeFilename($this->source);
        $commandOptionsArray[] = escapeshellarg($this->source);

        // Output
        //$commandOptionsArray[] = '-o ' . self::escapeFilename($this->destination);
        $commandOptionsArray[] = '-o ' . escapeshellarg($this->destination);


        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $commandOptionsArray[] = '2>&1';


        $useNice = (($options['use-nice']) && self::hasNiceSupport()) ? true : false;

        $commandOptions = implode(' ', $commandOptionsArray);

        $this->logLn('cwebp options:' . $commandOptions);

        // Init with common system paths
        $cwebpPathsToTest = self::$cwebpDefaultPaths;

        // Remove paths that doesn't exist
        /*
        $cwebpPathsToTest = array_filter($cwebpPathsToTest, function ($binary) {
            //return file_exists($binary);
            return @is_readable($binary);
        });
        */

        // Try all common paths that exists
        $success = false;
        $failures = [];
        $failureCodes = [];

        if (!$options['try-supplied-binary-for-os'] && !$options['try-common-system-paths']) {
            $errorMsg .= 'Configured to neither look for cweb binaries in common system locations, ' .
                'nor to use one of the supplied precompiled binaries. But these are the only ways ' .
                'this converter can convert images. No conversion can be made!';
        }

        if ($options['try-common-system-paths']) {
            foreach ($cwebpPathsToTest as $index => $binary) {
                $returnCode = self::executeBinary($binary, $commandOptions, $useNice, $this);
                if ($returnCode == 0) {
                    $this->logLn('Successfully executed binary: ' . $binary);
                    $success = true;
                    break;
                } else {
                    $failures[] = [$binary, $returnCode];
                    if (!in_array($returnCode, $failureCodes)) {
                        $failureCodes[] = $returnCode;
                    }
                }
            }
            $majorFailCode = 0;
            if (!$success) {
                if (count($failureCodes) == 1) {
                    $majorFailCode = $failureCodes[0];
                    switch ($majorFailCode) {
                        case 126:
                            $errorMsg = 'Permission denied. The user that the command was run with (' .
                                shell_exec('whoami') . ') does not have permission to execute any of the ' .
                                'cweb binaries found in common system locations. ';
                            break;
                        case 127:
                            $errorMsg .= 'Found no cwebp binaries in any common system locations. ';
                            break;
                        default:
                            $errorMsg .= 'Tried executing cwebp binaries in common system locations. ' .
                                'All failed (exit code: ' . $majorFailCode . '). ';
                    }
                } else {
                    /**
                     * $failureCodesBesides127 is used to check first position ($failureCodesBesides127[0])
                     * however position can vary as index can be 1 or something else. array_values() would
                     * always start from 0.
                     */
                    $failureCodesBesides127 = array_values(array_diff($failureCodes, [127]));

                    if (count($failureCodesBesides127) == 1) {
                        $majorFailCode = $failureCodesBesides127[0];
                        switch ($returnCode) {
                            case 126:
                                $errorMsg = 'Permission denied. The user that the command was run with (' .
                                shell_exec('whoami') . ') does not have permission to execute any of the cweb ' .
                                'binaries found in common system locations. ';
                                break;
                            default:
                                $errorMsg .= 'Tried executing cwebp binaries in common system locations. ' .
                                'All failed (exit code: ' . $majorFailCode . '). ';
                        }
                    } else {
                        $errorMsg .= 'None of the cwebp binaries in the common system locations could be executed ' .
                        '(mixed results - got the following exit codes: ' . implode(',', $failureCodes) . '). ';
                    }
                }
            }
        }

        if (!$success && $options['try-supplied-binary-for-os']) {
          // Try supplied binary (if available for OS, and hash is correct)
            if (isset(self::$suppliedBinariesInfo[PHP_OS])) {
                $info = self::$suppliedBinariesInfo[PHP_OS];

                $file = $info[0];
                $hash = $info[1];

                $binaryFile = __DIR__ . '/' . $options['rel-path-to-precompiled-binaries'] . '/' . $file;

                // The file should exist, but may have been removed manually.
                if (@file_exists($binaryFile)) {
                    // File exists, now generate its hash

                    // hash_file() is normally available, but it is not always
                    // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
                    // If available, validate that hash is correct.
                    $proceedAfterHashCheck = true;
                    if (function_exists('hash_file')) {
                        $binaryHash = hash_file('sha256', $binaryFile);

                        if ($binaryHash != $hash) {
                            $errorMsg .= 'Binary checksum of supplied binary is invalid! ' .
                                'Did you transfer with FTP, but not in binary mode? ' .
                                'File:' . $binaryFile . '. ' .
                                'Expected checksum: ' . $hash . '. ' .
                                'Actual checksum:' . $binaryHash . '.';
                            $proceedAfterHashCheck = false;
                        }
                    }
                    if ($proceedAfterHashCheck) {
                        $returnCode = self::executeBinary($binaryFile, $commandOptions, $useNice, $this);
                        if ($returnCode == 0) {
                            $success = true;
                        } else {
                            $errorMsg .= 'Tried executing supplied binary for ' . PHP_OS . ', ' .
                                ($options['try-common-system-paths'] ? 'but that failed too' : 'but failed');
                            if ($options['try-common-system-paths'] && ($majorFailCode > 0)) {
                                $errorMsg .= ' (same error)';
                            } else {
                                if ($returnCode > 128) {
                                    $errorMsg .= '. The binary did not work (exit code: ' . $returnCode . '). ' .
                                        'Check out https://github.com/rosell-dk/webp-convert/issues/92';
                                } else {
                                    switch ($returnCode) {
                                        case 0:
                                            $success = true;
                                            ;
                                            break;
                                        case 126:
                                            $errorMsg .= ': Permission denied. The user that the command was run' .
                                                ' with (' . shell_exec('whoami') . ') does not have permission to ' .
                                                'execute that binary.';
                                            break;
                                        case 127:
                                            $errorMsg .= '. The binary was not found! ' .
                                                'It ought to be here: ' . $binaryFile;
                                            break;
                                        default:
                                            $errorMsg .= ' (exit code:' .  $returnCode . ').';
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $errorMsg .= 'Supplied binary not found! It ought to be here:' . $binaryFile;
                }
            } else {
                $errorMsg .= 'No supplied binaries found for OS:' . PHP_OS;
            }
        }

        // cwebp sets file permissions to 664 but instead ..
        // .. $destination's parent folder's permissions should be used (except executable bits)
        if ($success) {
            $destinationParent = dirname($this->destination);
            $fileStatistics = @stat($destinationParent);
            if ($fileStatistics !== false) {
                // Apply same permissions as parent folder but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                @chmod($this->destination, $permissions);
            }
        }

        if (!$success) {
            throw new SystemRequirementsNotMetException($errorMsg);
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractCloudCurlConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

class Ewww extends AbstractCloudCurlConverter
{
    public static $extraOptions = [
        [
            'name' => 'key',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
    ];

    /**
     * Check operationality of Ewww converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met (curl)
     * @throws ConverterNotOperationalException   if key is missing or invalid, or quota has exceeded
     */
    protected function checkOperationality()
    {
        // First check for curl requirements
        parent::checkOperationality();

        $options = $this->options;

        if ($options['key'] == '') {
            throw new ConverterNotOperationalException('Missing API key.');
        }
        if (strlen($options['key']) < 20) {
            throw new ConverterNotOperationalException(
                'Key is invalid. Keys are supposed to be 32 characters long - your key is much shorter'
            );
        }

        $keyStatus = self::getKeyStatus($options['key']);
        switch ($keyStatus) {
            case 'great':
                break;
            case 'exceeded':
                throw new ConverterNotOperationalException('quota has exceeded');
                break;
            case 'invalid':
                throw new ConverterNotOperationalException('key is invalid');
                break;
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {

        $options = $this->options;

        $ch = self::initCurl();

        $curlOptions = [
            'api_key' => $options['key'],
            'webp' => '1',
            'file' => curl_file_create($this->source),
            'domain' => $_SERVER['HTTP_HOST'],
            'quality' => $options['_calculated_quality'],
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
            CURLOPT_POSTFIELDS => $curlOptions,
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
                throw new ConverterNotOperationalException('The key is invalid');
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

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class Gd extends AbstractConverter
{
    public static $extraOptions = [];

    /**
     * Check (general) operationality of Gd converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
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
    protected function checkConvertability()
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
     * Try to convert image pallette to true color.
     *
     * Try to convert image pallette to true color. If imageistruecolor() exists, that is used (available from
     * PHP >= 5.5.0). Otherwise using workaround found on the net.
     *
     * @param  resource  &$image
     * @return boolean  TRUE if the convertion was complete, or if the source image already is a true color image,
     *          otherwise FALSE is returned.
     */
    public static function makeTrueColor(&$image)
    {
        if (function_exists('imagepalettetotruecolor')) {
            return imagepalettetotruecolor($image);
        } else {
            // Got the workaround here: https://secure.php.net/manual/en/function.imagepalettetotruecolor.php
            if ((function_exists('imageistruecolor') && !imageistruecolor($image))
                || !function_exists('imageistruecolor')
            ) {
                if (self::functionsExist(['imagecreatetruecolor', 'imagealphablending', 'imagecolorallocatealpha',
                        'imagefilledrectangle', 'imagecopy', 'imagedestroy', 'imagesx', 'imagesy'])) {
                    $dst = imagecreatetruecolor(imagesx($image), imagesy($image));

                    //prevent blending with default black
                    imagealphablending($dst, false);

                     //change the RGB values if you need, but leave alpha at 127
                    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);

                     //simpler than flood fill
                    imagefilledrectangle($dst, 0, 0, imagesx($image), imagesy($image), $transparent);
                    imagealphablending($dst, true);     //restore default blending

                    imagecopy($dst, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                    imagedestroy($image);

                    $image = $dst;
                    return true;
                }
            } else {
                return false;
            }
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {

        $this->logLn('GD Version: ' . gd_info()["GD Version"]);

        // Btw: Check out processWebp here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Gd/Encoder.php

        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                $image = imagecreatefrompng($this->source);
                if (!$image) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefrompng() failed)'
                    );
                }
                break;

            case 'image/jpeg':
                $image = imagecreatefromjpeg($this->source);
                if (!$image) {
                    throw new ConversionFailedException(
                        'Gd failed when trying to load/create image (imagecreatefromjpeg() failed)'
                    );
                }
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false

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
                    'Continuing, but this does not look good.'
                );
            }
        }

        if ($mimeType == 'png') {
            if (function_exists('imagealphablending')) {
                if (!imagealphablending($image, true)) {
                    $this->logLn('Warning: imagealphablending() failed');
                }
            } else {
                $this->logLn(
                    'Warning: imagealphablending() is not available on your system.' .
                    ' Converting PNGs with transparency might fail on some systems'
                );
            }

            if (function_exists('imagesavealpha')) {
                if (!imagesavealpha($image, true)) {
                    $this->logLn('Warning: imagesavealpha() failed');
                }
            } else {
                $this->logLn(
                    'Warning: imagesavealpha() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
        }

        $success = imagewebp($image, $this->destination, $this->options['_calculated_quality']);

        if (!$success) {
            throw new ConversionFailedException(
                'Gd failed when trying to save the image as webp (call to imagewebp() failed). ' .
                'It probably failed writing file. Check file permissions!'
            );
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */
        if (filesize($this->destination) % 2 == 1) {
            file_put_contents($this->destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Gmagick extends AbstractConverter
{
    public static $extraOptions = [];

    /**
     * Check (general) operationality of Gmagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
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
    protected function checkConvertability()
    {
        $im = new \Gmagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
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

        /*
        Seems there are currently no way to set webp options
        As noted in the following link, it should probably be done with a $im->addDefinition() method
        - but that isn't exposed (yet)
        (TODO: see if anyone has answered...)
        https://stackoverflow.com/questions/47294962/how-to-write-lossless-webp-files-with-perlmagick
        */
        // The following two does not have any effect... How to set WebP options?
        //$im->setimageoption('webp', 'webp:lossless', $options['lossless'] ? 'true' : 'false');
        //$im->setimageoption('WEBP', 'method', strval($options['method']));

        // It seems there is no COMPRESSION_WEBP...
        // http://php.net/manual/en/imagick.setimagecompression.php
        //$im->setImageCompression(Imagick::COMPRESSION_JPEG);
        //$im->setImageCompression(Imagick::COMPRESSION_UNDEFINED);



        $im->setimageformat('WEBP');

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        // Ps: Imagick automatically uses same quality as source, when no quality is set
        // This feature is however not present in Gmagick
        $im->setcompressionquality($this->getCalculatedQuality());

        try {
            // We call getImageBlob().
            // That method is undocumented, but it is there!
            // - just like it is in imagick, as pointed out here:
            //   https://www.php.net/manual/ru/gmagick.readimageblob.php

            /** @scrutinizer ignore-call */
            $imageBlob = $im->getImageBlob();
        } catch (\ImagickException $e) {
            throw new ConversionFailedException(
                'Gmagick failed converting - getImageBlob() threw an exception)',
                0,
                $e
            );
        }


        //$success = $im->writeimagefile(fopen($destination, 'wb'));
        $success = @file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new ConversionFailedException('Failed writing file');
        } else {
            //$logger->logLn('sooms we made it!');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Imagick extends AbstractConverter
{
    public static $extraOptions = [];


    /**
     * Check operationality of Imagick converter.
     *
     * Note:
     * It may be that Gd has been compiled without jpeg support or png support.
     * We do not check for this here, as the converter could still be used for the other.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
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

        if (!in_array('WEBP', $im->queryFormats())) {
            throw new SystemRequirementsNotMetException('iMagick was compiled without WebP support.');
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Imagick does not support image type
     */
    protected function checkConvertability()
    {
        $im = new \Imagick();
        $mimeType = $this->getMimeTypeOfSource();
        switch ($mimeType) {
            case 'image/png':
                if (!in_array('PNG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without PNG support and can therefore not convert this PNG image.'
                    );
                }
                break;
            case 'image/jpeg':
                if (!in_array('JPEG', $im->queryFormats())) {
                    throw new SystemRequirementsNotMetException(
                        'Imagick has been compiled without Jpeg support and can therefore not convert this Jpeg image.'
                    );
                }
                break;
        }
    }

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {


        $options = $this->options;

        try {
            $im = new \Imagick($this->source);
        } catch (\Exception $e) {
            throw new ConversionFailedException(
                'Failed creating Gmagick object of file',
                'Failed creating Gmagick object of file: "' . $this->source . '" - Imagick threw an exception.',
                $e
            );
        }

        //$im = new \Imagick();
        //$im->readImage($this->source);

        $im->setImageFormat('WEBP');

        /*
         * More about iMagick's WebP options:
         * http://www.imagemagick.org/script/webp.php
         * https://developers.google.com/speed/webp/docs/cwebp
         * https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        // TODO: We could easily support all webp options with a loop

        /*
        After using getImageBlob() to write image, the following setOption() calls
        makes settings makes imagick fail. So can't use those. But its a small price
        to get a converter that actually makes great quality conversions.

        $im->setOption('webp:method', strval($options['method']));
        $im->setOption('webp:low-memory', strval($options['low-memory']));
        $im->setOption('webp:lossless', strval($options['lossless']));
        */

        if ($options['metadata'] == 'none') {
            // Strip metadata and profiles
            $im->stripImage();
        }

        if ($this->isQualitySetToAutoAndDidQualityDetectionFail()) {
            // Luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-quality functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $logger->logLn('Converting without setting quality, to achieve auto quality');
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

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)

        // We used to use writeImageFile() method. But we now use getImageBlob(). See issue #43
        //$success = $im->writeImageFile(fopen($destination, 'wb'));

        try {
            $imageBlob = $im->getImageBlob();
        } catch (\ImagickException $e) {
            throw new ConversionFailedException(
                'Imagick failed converting - getImageBlob() threw an exception)',
                0,
                $e
            );
        }

        $success = file_put_contents($this->destination, $imageBlob);

        if (!$success) {
            throw new CreateDestinationFileException('Failed writing file');
        }

        // Btw: check out processWebp() method here:
        // https://github.com/Intervention/image/blob/master/src/Intervention/Image/Imagick/Encoder.php
    }
}

?><?php
// TODO: Quality option
namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractExecConverter;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

// To futher improve this converter, I could check out:
// https://github.com/Orbitale/ImageMagickPHP
class ImagickBinary extends AbstractExecConverter
{
    public static $extraOptions = [
        [
            'name' => 'use-nice',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
    {
        if (!self::imagickInstalled()) {
            throw new SystemRequirementsNotMetException('imagick is not installed');
        }
        if (!self::webPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('webp delegate missing');
        }
    }

    protected function doConvert()
    {
        //$this->logLn('Using quality:' . $this->getCalculatedQuality());
        // Should we use "magick" or "convert" command?
        // It seems they do the same. But which is best supported? Which is mostly available (whitelisted)?
        // Should we perhaps try both?
        // For now, we just go with "convert"
        $command = 'convert ' .
            escapeshellarg($this->source) . ' ' . escapeshellarg('webp:' . $this->destination);
            //self::escapeFilename($this->source) . ' webp:' . self::escapeFilename($this->destination);

        // TODO:
        // quality. Like this: 'convert -quality 100 small.jpg small.webp'
        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        exec($command, $output, $returnCode);
        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('imagick is not installed');
        }
        if ($returnCode != 0) {
            $this->logLn('command:' . $command);
            $this->logLn('return code:' . $returnCode);
            $this->logLn('output:' . print_r(implode("\n", $output), true));
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }

    public static function imagickInstalled()
    {
        exec('convert -version', $output, $returnCode);
        return ($returnCode == 0);
    }
    
    // Check if webp delegate is installed
    public static function webPDelegateInstalled()
    {
        /* HM. We should not rely on grep being available
        $command = 'convert -list configure | grep -i "delegates" | grep -i webp';
        exec($command, $output, $returnCode);
        return (count($output) > 0);
        */
        $command = 'convert -version';
        exec($command, $output, $returnCode);
        $hasDelegate = false;
        foreach ($output as $line) {
            if (preg_match('/Delegate.*webp.*/i', $line)) {
                return true;
            }
        }
        return false;
    }
}

?><?php

// TODO: Quality option

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

class Stack extends AbstractConverter
{
    public static $extraOptions = [
        [
            'name' => 'converters',
            'type' => 'array',
            'sensitive' => true,
            'default' => ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'],
            'required' => false
        ],
        /*
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => false,
            'required' => false
        ],*/
        /*[
            'name' => 'quality',
            'type' => 'quality',
            'sensitive' => false,
            'default' => 'auto',
            'required' => false
        ],*/
    ];

    public static $availableConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary', 'wpc', 'ewww'];
    public static $localConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'];

    public static function getClassNameOfConverter($converterId)
    {
        if (strtolower($converterId) == $converterId) {
            $className = 'WebPConvert\\Convert\\Converters\\' . ucfirst($converterId);
            if (is_callable([$className, 'convert'])) {
                return $className;
            } else {
                throw new ConverterNotFoundException('There is no converter with id:' . $converterId);
            }
        }
        $className = $converterId;
        if (!is_callable([$className, 'convert'])) {
            throw new ConverterNotFoundException('There is no converter with class name:' . $className);
        }

        return $className;
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
    {
        if (count($this->options) == 0) {
            throw new ConverterNotOperationalException(
                'Converter stack is empty! - no converters to try, no conversion can be made!'
            );
        }

        // TODO: We should test if all converters are found in order to detect problems early
    }

    protected function doConvert()
    {
        $options = $this->options;

        $beginTimeStack = microtime(true);

        // If we have set converter options for a converter, which is not in the converter array,
        // then we add it to the array
        if (isset($options['converter-options'])) {
            foreach ($options['converter-options'] as $converterName => $converterOptions) {
                if (!in_array($converterName, $options['converters'])) {
                    $options['converters'][] = $converterName;
                }
            }
        }

        //$this->logLn('converters: ' . print_r($options['converters'], true));

        $defaultConverterOptions = $options;

        unset($defaultConverterOptions['converters']);
        unset($defaultConverterOptions['converter-options']);
        $defaultConverterOptions['_skip_basic_validations'] = true;
        $defaultConverterOptions['_suppress_success_message'] = true;

        $anyRuntimeErrors = false;
        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = $converter['options'];
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

            // If quality is different, we must recalculate
            if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                unset($converterOptions['_calculated_quality']);
            }

            $beginTime = microtime(true);

            $className = self::getClassNameOfConverter($converterId);

            try {
                $converterDisplayName = call_user_func(
                    [$className, 'getConverterDisplayName']
                );

                $this->ln();
                $this->logLn('Trying: ' . $converterId, 'italic');

                call_user_func(
                    [$className, 'convert'],
                    $this->source,
                    $this->destination,
                    $converterOptions,
                    $this->logger
                );

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLn($converterDisplayName . ' succeeded :)');
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
            } catch (ConversionDeclinedException $e) {
                $this->logLn($e->getMessage());
            }

            $this->logLn($converterDisplayName . ' failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
        }

        $this->ln();
        $this->logLn('Stack failed in ' . round((microtime(true) - $beginTimeStack) * 1000) . ' ms');

        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConversionFailedException(
                'None of the converters in the stack could convert the image. ' .
                'At least one failed, even though its requirements seemed to be met.'
            );
        } else {
            // All converters threw a SystemRequirementsNotMetException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }
    }
}

?><?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractCloudCurlConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

class Wpc extends AbstractCloudCurlConverter
{
    public static $extraOptions = [
        [
            'name' => 'api-version',        /* Can currently be 0 or 1 */
            'type' => 'number',
            'sensitive' => false,
            'default' => 0,
            'required' => false
        ],
        [
            'name' => 'secret',        /* only in api v.0 */
            'type' => 'string',
            'sensitive' => true,
            'default' => 'my dog is white',
            'required' => false
        ],
        [
            'name' => 'api-key',        /* new in api v.1 (renamed 'secret' to 'api-key') */
            'type' => 'string',
            'sensitive' => true,
            'default' => 'my dog is white',
            'required' => false
        ],
        [
            'name' => 'url',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
        [
            'name' => 'crypt-api-key-in-transfer',  /* new in api v.1 */
            'type' => 'boolean',
            'sensitive' => false,
            'default' => false,
            'required' => false
        ],

        /*
        [
            'name' => 'web-services',
            'type' => 'array',
            'sensitive' => true,
            'default' => [
                [
                    'label' => 'test',
                    'api-key' => 'my dog is white',
                    'url' => 'http://we0/wordpress/webp-express-server',
                    'crypt-api-key-in-transfer' => true
                ]
            ],
            'required' => true
        ],
        */
    ];

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

    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in AbstractConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    protected function doConvert()
    {
        $options = $this->options;

        $apiVersion = $options['api-version'];

        if (!function_exists('curl_file_create')) {
            throw new SystemRequirementsNotMetException(
                'Required curl_file_create() PHP function is not available (requires PHP > 5.5).'
            );
        }

        if ($apiVersion == 0) {
            if (!empty($options['secret'])) {
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
        }

        if ($apiVersion == 1) {
        /*
                if (count($options['web-services']) == 0) {
                    throw new SystemRequirementsNotMetException('No remote host has been set up');
                }*/
        }

        if ($options['url'] == '') {
            throw new ConverterNotOperationalException(
                'Missing URL. You must install Webp Convert Cloud Service on a server, ' .
                'or the WebP Express plugin for Wordpress - and supply the url.'
            );
        }

        // Got some code here:
        // https://coderwall.com/p/v4ps1a/send-a-file-via-post-with-curl-and-php

        $ch = self::initCurl();

        $optionsToSend = $options;

        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // Ask the cloud service to do it, rather than using what we came up with.
            $optionsToSend['quality'] = 'auto';
        } else {
            $optionsToSend['quality'] = $options['_calculated_quality'];
        }

        unset($optionsToSend['converters']);
        unset($optionsToSend['secret']);
        unset($optionsToSend['_quality_could_not_be_detected']);
        unset($optionsToSend['_calculated_quality']);

        $postData = [
            'file' => curl_file_create($this->source),
            'options' => json_encode($optionsToSend),
            'servername' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
        ];

        if ($apiVersion == 0) {
            $postData['hash'] = md5(md5_file($this->source) . $options['secret']);
        }

        if ($apiVersion == 1) {
            $apiKey = $options['api-key'];

            if ($options['crypt-api-key-in-transfer']) {
                if (CRYPT_BLOWFISH == 1) {
                    $salt = self::createRandomSaltForBlowfish();
                    $postData['salt'] = $salt;

                    // Strip off the first 28 characters (the first 6 are always "$2y$10$". The next 22 is the salt)
                    $postData['api-key-crypted'] = substr(crypt($apiKey, '$2y$10$' . $salt . '$'), 28);
                } else {
                    if (!function_exists('crypt')) {
                        throw new ConverterNotOperationalException(
                            'Configured to crypt the api-key, but crypt() function is not available.'
                        );
                    } else {
                        throw new ConverterNotOperationalException(
                            'Configured to crypt the api-key. ' .
                            'That requires Blowfish encryption, which is not available on your current setup.'
                        );
                    }
                }
            } else {
                $postData['api-key'] = $apiKey;
            }
        }


        // Try one host at the time
        // TODO: shuffle the array first
        /*
        foreach ($options['web-services'] as $webService) {

        }
        */


        curl_setopt_array($ch, [
            CURLOPT_URL => $options['url'],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new ConverterNotOperationalException('Curl error:' . curl_error($ch));
        }

        // Check if we got a 404
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            curl_close($ch);
            throw new ConversionFailedException(
                'WPC was not found at the specified URL - we got a 404 response.'
            );
        }

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
                            throw new ConverterNotOperationalException(
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

            if (empty($response)) {
                $errorMsg = 'Error: Unexpected result. We got nothing back. HTTP CODE: ' . $httpCode;
                throw new ConversionFailedException($errorMsg);
            } else {
                $errorMsg = 'Error: Unexpected result. We did not receive an image. We received: "';
                $errorMsg .= str_replace("\r", '', str_replace("\n", '', htmlentities(substr($response, 0, 400))));
                throw new ConversionFailedException($errorMsg . '..."');
            }
            //throw new ConverterNotOperationalException($response);
        }

        $success = @file_put_contents($this->destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConversionFailedException('Error saving file. Check file permissions');
        }
        /*
                $curlOptions = [
                    'api_key' => $options['key'],
                    'webp' => '1',
                    'file' => curl_file_create($this->source),
                    'domain' => $_SERVER['HTTP_HOST'],
                    'quality' => $options['quality'],
                    'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
                ];

                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: WebPConvert',
                        'Accept: image/*'
                    ],
                    CURLOPT_POSTFIELDS => $curlOptions,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);*/
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
 *          ConversionDeclinedException
 *          ConverterNotOperationalException
 *              SystemRequirementsNotMetException
 *          FileSystemProblemsException
 *              CreateDestinationFileException
 *              CreateDestinationFolderException
 *          InvalidInputException
 *              ConverterNotFoundException
 *              InvalidImageTypeException
 *              TargetNotFoundException
 *          UnhandledException
 */
class ConversionFailedException extends WebPConvertException
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}

?><?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConversionDeclinedException extends ConversionFailedException
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

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class UnhandledException extends ConversionFailedException
{
    public $description = 'The converter failed due to uncaught exception';

    /*
    Nah, do not add message of the uncaught exception to this.
    - because it might be long and contain characters which consumers for example cannot put inside a
    x-webpconvert-error header
    The messages we throw are guaranteed to be short

    public function __construct($message="", $code=0, $previous)
    {
        parent::__construct(
            'The converter failed due to uncaught exception: ' . $previous->getMessage(),
            $code,
            $previous
        );
        //$this->$message = 'hello.' . $message . ' ' . $previous->getMessage();
    }*/
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

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

/**
 *  WebPConvertException is the base exception for all exceptions in this library.
 *
 *  Note that the parameters for the constructor differs from that of the Exception class.
 *  We do not use exception code here, but are instead allowing two version of the error message:
 *  a short version and a long version.
 *  The short version may not contain special characters or dynamic content.
 *  The detailed version may.
 *  If the detailed version isn't provided, getDetailedMessage will return the short version.
 *
 */
class WarningException extends WebPConvertException
{
    public $description = 'A warning was issued and turned into an exception';
}

?><?php

namespace WebPConvert\Exceptions;

/**
 *  WebPConvertException is the base exception for all exceptions in this library.
 *
 *  Note that the parameters for the constructor differs from that of the Exception class.
 *  We do not use exception code here, but are instead allowing two version of the error message:
 *  a short version and a long version.
 *  The short version may not contain special characters or dynamic content.
 *  The detailed version may.
 *  If the detailed version isn't provided, getDetailedMessage will return the short version.
 *
 */
class WebPConvertException extends \Exception
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
    protected $detailedMessage;
    protected $shortMessage;

    public function getDetailedMessage()
    {
        return $this->detailedMessage;
    }

    public function getShortMessage()
    {
        return $this->shortMessage;
    }

    public function __construct($shortMessage = "", $detailedMessage = "", $previous = null)
    {
        $detailedMessage = ($detailedMessage != '') ? $detailedMessage : $shortMessage;
        $this->detailedMessage = $detailedMessage;
        $this->shortMessage = $shortMessage;

        parent::__construct(
            $detailedMessage,
            0,
            $previous
        );
    }
}

?><?php

namespace WebPConvert\Helpers;

use WebPConvert\Helpers\WarningsIntoExceptions;

abstract class JpegQualityDetector
{

    /**
     * Try to detect quality of jpeg using imagick extension
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImagickExtension($filename)
    {
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            try {
                $img = new \Imagick($filename);

                // The required function is available as from PECL imagick v2.2.2
                if (method_exists($img, 'getImageCompressionQuality')) {
                    return $img->getImageCompressionQuality();
                }
            } catch (\Exception $e) {
                // Well well, it just didn't work out.
                // - But perhaps next method will work...
            }
        }
        return null;
    }


    /**
     * Try to detect quality of jpeg using imagick binary
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingImagickBinary($filename)
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
     * Try to detect quality of jpeg using gmagick binary
     *
     * @param  string  $filename  A complete file path to file to be examined
     * @return int|null  Quality, or null if it was not possible to detect quality
     */
    private static function detectQualityOfJpgUsingGmagickBinary($filename)
    {
        if (function_exists('exec')) {
            // Try GraphicsMagick
            exec("gm identify -format '%Q' " . escapeshellarg($filename) . " 2>&1", $output, $returnCode);
            if ((intval($returnCode) == 0) && (is_array($output)) && (count($output) == 1)) {
                return intval($output[0]);
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
        $quality = self::detectQualityOfJpgUsingImagickExtension($filename);

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingImagickBinary($filename);
        }

        if (is_null($quality)) {
            $quality = self::detectQualityOfJpgUsingGmagickBinary($filename);
        }

        return $quality;
    }
}

?><?php

namespace WebPConvert\Helpers;

use WebPConvert\Exceptions\WarningException;

abstract class WarningsIntoExceptions
{

    public static function warningHandler($errno, $errstr, $errfile, $errline)
    {
        //echo 'aeothsutsanoheutsnhaoeu: ' . E_USER_WARNING . ':' . E_WARNING;
        throw new WarningException(
            'A warning was issued',
            'A warning was issued: ' . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline .
                ', PHP ' . PHP_VERSION .
                ' (' . PHP_OS . ')'
        );

        /* Don't execute PHP internal error handler */
        return true;
    }

    public static function activate()
    {
        set_error_handler(
            array('\\WebPConvert\\Helpers\\WarningsIntoExceptions', "warningHandler"),
            E_WARNING | E_USER_WARNING | E_ALL
        );   // E_USER_WARNING
    }

    public static function deactivate()
    {
        restore_error_handler();
    }
}

?><?php

namespace WebPConvert\Loggers;

abstract class BaseLogger
{
    /*
    $msg: message to log
    $style: null | bold | italic
    */
    abstract public function log($msg, $style = '');

    abstract public function ln();

    public function logLn($msg, $style = '')
    {
        $this->log($msg, $style);
        $this->ln();
    }

    public function logLnLn($msg, $style = '')
    {
        $this->logLn($msg, $style);
        $this->ln();
    }
}

?><?php

namespace WebPConvert\Loggers;

use WebPConvert\Loggers\BaseLogger;

class BufferLogger extends BaseLogger
{
    public $entries = array();

    public function log($msg, $style = '')
    {
        $this->entries[] = [$msg, $style];
    }

    public function ln()
    {
        $this->entries[] = '';
    }

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

    public function getText($newLineChar = ' ')
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                if (substr($text, -2) != '. ') {
                    $text .= '. ';
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

class EchoLogger extends BaseLogger
{
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

    public function ln()
    {
        echo '<br>';
    }
}

?><?php

namespace WebPConvert\Loggers;

class VoidLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
    }

    public function ln()
    {
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\EchoLogger;

//use WebPConvert\Loggers\EchoLogger;

class Report
{

    /**
     *   Input: We have a converter array where the options are defined
     *   Output:  the converter array is "flattened" to be just names.
     *            and the options have been moved to the "converter-options" option.
     */
    public static function flattenConvertersArray($options)
    {
        // TODO: If there are more of the same converters,
        // they should be added as ie 'wpc-2', 'wpc-3', etc

        $result = $options;
        $result['converters'] = [];
        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterName = $converter['converter'];
                if (!isset($options['converter-options'][$converterName])) {
                    if (isset($converter['options'])) {
                        if (!isset($result['converter-options'])) {
                            $result['converter-options'] = [];
                        }
                        $result['converter-options'][$converterName] = $converter['options'];
                    }
                }
                $result['converters'][] = $converterName;
            } else {
                $result['converters'][] = $converter;
            }
        }
        return $result;
    }

    /* Hides sensitive options */
    public static function getPrintableOptions($options)
    {
        $printable_options = [];

        /*
        TODO: This piece of code should be "translated" to work in 2.0
        if (is_callable('ConverterHelper', 'getClassNameOfConverter')) {
            $printable_options = self::flattenConvertersArray($options);
            if (isset($printable_options['converter-options'])) {
                foreach ($printable_options['converter-options'] as $converterName => &$converterOptions) {
                    $className = ConverterHelper::getClassNameOfConverter($converterName);

                    // (pstt: the isset check is needed in order to work with WebPConvert v1.0)
                    if (isset($className::$extraOptions)) {
                        foreach ($className::$extraOptions as $extraOption) {
                            if ($extraOption['sensitive']) {
                                if (isset($converterOptions[$extraOption['name']])) {
                                    $converterOptions[$extraOption['name']] = '*******';
                                }
                            }
                        }
                    }
                }
            }
        }
        */
        return $printable_options;
    }

    public static function getPrintableOptionsAsString($options, $glue = '. ')
    {
        $optionsForPrint = [];
        foreach (self::getPrintableOptions($options) as $optionName => $optionValue) {
            $printValue = '';
            if ($optionName == 'converter-options') {
                $converterNames = [];
                $extraConvertOptions = $optionValue;
                //print_r($extraConvertOptions);
                /*
                foreach ($optionValue as $converterName => $converterOptions) {

                    if (is_array($converter)) {
                        $converterName = $converter['converter'];
                        if (isset($converter['options'])) {
                            $extraConvertOptions[$converter['converter']] = $converter['options'];
                        }
                    } else {
                        $converterName = $converter;
                    }
                    $converterNames[] = $converterName;
                }*/
                $glueMe = [];
                foreach ($extraConvertOptions as $converter => $extraOptions) {
                    $opt = [];
                    foreach ($extraOptions as $oName => $oValue) {
                        $opt[] = $oName . ':"' . $oValue . '"';
                    }
                    $glueMe[] = '(' . $converter . ': (' . implode($opt, ', ') . '))';
                }
                $printValue = implode(',', $glueMe);
            } elseif ($optionName == 'web-service') {
                $printValue = 'sensitive, so not displaying here...';
            } else {
                switch (gettype($optionValue)) {
                    case 'boolean':
                        if ($optionValue === true) {
                            $printValue = 'true';
                        } elseif ($optionValue === false) {
                            $printValue = 'false';
                        }
                        break;
                    case 'string':
                        $printValue = '"' . $optionValue . '"';
                        break;
                    case 'array':
                        $printValue = implode(', ', $optionValue);
                        break;
                    case 'integer':
                        $printValue = $optionValue;
                        break;
                    default:
                        $printValue = $optionValue;
                }
            }
            $optionsForPrint[] = $optionName . ': ' . $printValue;
        }
        return implode($glue, $optionsForPrint);
    }

    public static function convertAndReport($source, $destination, $options)
    {
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
            <tr><td><i>source:</i></td><td><?php echo $source ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo $destination ?><td></tr>
            <tr>
                <td><i>options:</i></td>
                <td>
                    <i style="text-decoration:underline;cursor:pointer" onclick="showOptions(this)">click to see</i>
                    <pre id="options" style="display:none"><?php
                        echo print_r(self::getPrintableOptionsAsString($options, '<br>'), true);
                    ?></pre>
                    <?php //echo json_encode(self::getPrintableOptions($options)); ?>
                    <?php //echo print_r(self::getPrintableOptions($options), true); ?>
                </td>
            </tr>
        </table>
        <br>
        <?php
        // TODO:
        // We could display warning if unknown options are set
        // but that requires that WebPConvert also describes its general options

        try {
            $echoLogger = new EchoLogger();
            WebPConvert::convert($source, $destination, $options, $echoLogger);
        } catch (\Exception $e) {
            $success = false;

            $msg = $e->getMessage();

            echo '<b>' . $msg . '</b>';
            exit;
        }
        ?>
    </body>
    </html>
        <?php
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

use WebPConvert\Loggers\BufferLogger;
use WebPConvert\Serve\Report;

/**
 * This class must serves a converted image (either a fresh convertion, the destionation, or
 * the original). Upon failure, the fail action given in the options will be exectuted
 */
class ServeConverted extends ServeBase
{

    /*
    Not used, currently...
    private function addXOptionsHeader()
    {
        if ($this->options['add-x-header-options']) {
            $this->header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($this->options));
        }
    }
    */

    private function addHeadersPreventingCaching()
    {
        $this->header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $this->header("Cache-Control: post-check=0, pre-check=0", false);
        $this->header("Pragma: no-cache");
    }

    public function serve404()
    {
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        $this->header($protocol . " 404 Not Found");
    }

    public function serveOriginal()
    {
        if (!$this->callAboutToServeImageCallBack('source')) {
            return true;    // we shall not trigger the fail callback
        }

        if ($this->options['add-content-type-header']) {
            $arr = explode('.', $this->source);
            $ext = array_pop($arr);
            switch (strtolower($ext)) {
                case 'jpg':
                case 'jpeg':
                    $this->header('Content-type: image/jpeg');
                    break;
                case 'png':
                    $this->header('Content-type: image/png');
                    break;
            }
        }

        $this->addVaryHeader();

        switch ($this->whyServingThis) {
            case 'source-lighter':
            case 'explicitly-told-to':
                $this->addCacheControlHeader();
                $this->addLastModifiedHeader(@filemtime($this->source));
                break;
            default:
                $this->addHeadersPreventingCaching();
        }

        if (@readfile($this->source) === false) {
            $this->header('X-WebP-Convert: Could not read file');
            return false;
        }
        return true;
    }

    public function serveFreshlyConverted()
    {

        $criticalFail = false;
        $bufferLogger = new BufferLogger();

        try {
            WebPConvert::convert($this->source, $this->destination, $this->options, $bufferLogger);

            // We are here, so it was successful :)

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($this->destination);
            $filesizeSource = @filesize($this->source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                $this->whatToServe = 'original';
                $this->whyServingThis = 'source-lighter';
                return $this->serveOriginal();
            }

            if (!$this->callAboutToServeImageCallBack('fresh-conversion')) {
                return;
            }
            if ($this->options['add-content-type-header']) {
                $this->header('Content-type: image/webp');
            }
            if ($this->whyServingThis == 'explicitly-told-to') {
                $this->addXStatusHeader(
                    'Serving freshly converted image (was explicitly told to reconvert)'
                );
            } elseif ($this->whyServingThis == 'source-modified') {
                $this->addXStatusHeader(
                    'Serving freshly converted image (the original had changed)'
                );
            } elseif ($this->whyServingThis == 'no-existing') {
                $this->addXStatusHeader(
                    'Serving freshly converted image (there were no existing to serve)'
                );
            } else {
                $this->addXStatusHeader(
                    'Serving freshly converted image (dont know why!)'
                );
            }

            if ($this->options['add-vary-header']) {
                $this->header('Vary: Accept');
            }

            if ($this->whyServingThis == 'no-existing') {
                $this->addCacheControlHeader();
            } else {
                $this->addHeadersPreventingCaching();
            }
            $this->addLastModifiedHeader(time());

            // Should we add Content-Length header?
            // $this->header('Content-Length: ' . filesize($file));
            if (@readfile($this->destination)) {
                return true;
            } else {
                $this->fail('Error', 'could not read the freshly converted file');
                return false;
            }
        } catch (InvalidImageTypeException $e) {
            $criticalFail = true;
            $description = 'Invalid file extension';
            $msg = $e->getMessage();
        } catch (TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Source file not found';
            $msg = $e->getMessage();
        } catch (ConversionFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'No converters could convert the image';
            $msg = $e->getMessage();
        } catch (ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'No converters could/wanted to convert the image';
            $msg = $e->getMessage();
        } catch (ConverterNotFoundException $e) {
            $description = 'A converter was not found!';
            $msg = $e->getMessage();
        } catch (CreateDestinationFileException $e) {
            $description = 'Cannot create destination file';
            $msg = $e->getMessage();
        } catch (CreateDestinationFolderException $e) {
            $description = 'Cannot create destination folder';
            $msg = $e->getMessage();
        } catch (\Exception $e) {
            $description = 'An unanticipated exception was thrown';
            $msg = $e->getMessage();
        }

        // Next line is commented out, because we need to be absolute sure that the details does not violate syntax
        // We could either try to filter it, or we could change WebPConvert, such that it only provides safe texts.
        // $this->header('X-WebP-Convert-And-Serve-Details: ' . $bufferLogger->getText());

        $this->fail('Conversion failed', $description, $criticalFail);
        return false;
        //echo '<p>This is how conversion process went:</p>' . $bufferLogger->getHtml();
    }

    protected function serveErrorMessageImage($msg)
    {
        // Generate image containing error message
        if ($this->options['add-content-type-header']) {
            $this->header('Content-type: image/gif');
        }

        try {
            if (function_exists('imagecreatetruecolor') &&
                function_exists('imagestring') &&
                function_exists('imagecolorallocate') &&
                function_exists('imagegif')
            ) {
                $image = imagecreatetruecolor(620, 200);
                if ($image !== false) {
                    imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
                    // echo imagewebp($image);
                    echo imagegif($image);
                    imagedestroy($image);
                    return;
                }
            }
        } catch (\Exception $e) {
            //
        }

        // Above failed.
        // TODO: what to do?
    }

    /**
     *
     * @return  void
     */
    protected function fail($title, $description, $critical = false)
    {
        $action = $critical ? $this->options['fail-when-original-unavailable'] : $this->options['fail'];

        if (isset($this->options['aboutToPerformFailActionCallback'])) {
            if (call_user_func(
                $this->options['aboutToPerformFailActionCallback'],
                $title,
                $description,
                $action,
                $this
            ) === false) {
                return;
            }
        }

        $this->addXStatusHeader('Failed (' . $description . ')');

        $this->addHeadersPreventingCaching();

        $title = 'Conversion failed';
        switch ($action) {
            case 'serve-original':
                if (!$this->serveOriginal()) {
                    $this->serve404();
                };
                break;
            case '404':
                $this->serve404();
                break;
            case 'report-as-image':
                // todo: handle if this fails
                self::serveErrorMessageImage($title . '. ' . $description);
                break;
            case 'report':
                echo '<h1>' . $title . '</h1>' . $description;
                break;
        }
    }

    /**
     *
     * @return  void
     */
    protected function criticalFail($title, $description)
    {
        $this->fail($title, $description, true);
    }

    /**
     *  Serve the thing specified in $whatToServe and $whyServingThis
     *  These are first set my the decideWhatToServe() method, but may later change, if a fresh
     *  conversion is made
     */
    public function serve()
    {

        //$this->addXOptionsHeader();

        switch ($this->whatToServe) {
            case 'destination':
                return $this->serveExisting();
            case 'source':
                if ($this->whyServingThis == 'explicitly-told-to') {
                    $this->addXStatusHeader(
                        'Serving original image (was explicitly told to)'
                    );
                } else {
                    $this->addXStatusHeader(
                        'Serving original image (it is smaller than the already converted)'
                    );
                }
                if (!$this->serveOriginal()) {
                    $this->criticalFail('Error', 'could not serve original');
                    return false;
                }
                return true;
            case 'fresh-conversion':
                return $this->serveFreshlyConverted();
                break;
            case 'critical-fail':
                $this->criticalFail('Error', $this->whyServingThis);
                return false;
            case 'fail':
                $this->fail('Error', $this->whyServingThis);
                return false;
            case 'report':
                $this->addXStatusHeader('Reporting...');
                Report::convertAndReport($this->source, $this->destination, $this->options);
                return true;  // yeah, lets say that a report is always a success, even if conversion is a failure
        }
    }

    public function decideWhatToServeAndServeIt()
    {
        $this->decideWhatToServe();
        return $this->serve();
    }

    /**
     * Main method
     */
    public static function serveConverted($source, $destination, $options)
    {
        if (isset($options['fail']) && ($options['fail'] == 'original')) {
            $options['fail'] = 'serve-original';
        }
        // For backward compatability:
        if (isset($options['critical-fail']) && !isset($options['fail-when-original-unavailable'])) {
            $options['fail-when-original-unavailable'] = $options['critical-fail'];
        }

        $cs = new static($source, $destination, $options);

        return $cs->decideWhatToServeAndServeIt();
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

abstract class AbstractDetector
{

    abstract protected function doDetect($filePath);

    public static function createInstance()
    {
        return new static();
    }

    /**
     *
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public static function detect($filePath)
    {
        if (!@file_exists($filePath)) {
            return false;
        }
        return self::createInstance()->doDetect($filePath);
    }
}

?><?php

/**
 * ImageMimeTypeGuesser - Detect / guess mime type of an image
 *
 * @link https://github.com/rosell-dk/image-mime-type-guesser
 * @license MIT
 */

namespace ImageMimeTypeGuesser;

class GuessFromExtension
{


    /**
     *  Make a wild guess based on file extension
     *  - and I mean wild!
     *
     *  Only most popular image types are recognized.
     *  Many are not. See this list: https://www.iana.org/assignments/media-types/media-types.xhtml
     *                - and the constants here: https://secure.php.net/manual/en/function.exif-imagetype.php
     *  TODO: jp2, jpx, ...
     */
    public static function guess($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileExtension = strtolower($fileExtension);

        switch ($fileExtension) {
            case 'bmp':
            case 'gif':
            case 'jpeg':
            case 'png':
            case 'tiff':
            case 'webp':
                return 'image/' . $fileExtension;

            case 'ico':
                return 'image/vnd.microsoft.icon';      // or perhaps 'x-icon' ?

            case 'jpg':
                return 'image/jpeg';

            case 'svg':
                return 'image/svg+xml';

            case 'tif':
                return 'image/tiff';
        }
        return false;
    }
}

?><?php

/**
 * ImageMimeTypeGuesser - Detect / guess mime type of an image
 *
 * @link https://github.com/rosell-dk/image-mime-type-guesser
 * @license MIT
 */

namespace ImageMimeTypeGuesser;

use \ImageMimeTypeGuesser\Detectors\Stack;

class ImageMimeTypeGuesser
{


    /**
     *  Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     *
     *  returns:
     *  - null  (if it cannot be determined)
     *  - false (if it is not an image that the server knows about)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime type | null | false.
     */
    public static function detect($filePath)
    {
        // Result of the discussion here:
        // https://github.com/rosell-dk/webp-convert/issues/98

        return Stack::detect($filePath);
    }

    /**
     *  Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     *  If that fails, fall back to wild west guessing based solely on file extension, which always has an answer
     *  (this method never returns null)
     *
     *  returns:
     *  - false (if it is not an image that the server knows about)
     *  - mime  (if it looks like an image)
     *  @return  mime type | false.
     */
    public static function guess($filePath)
    {
        $detectionResult = self::detect($filePath);
        if (!is_null($detectionResult)) {
            return $detectionResult;
        }

        // fall back to the wild west method
        return GuessFromExtension::guess($filePath);
    }

    /**
     *  Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     *  But do not take no for an answer, as "no", really only means that the server has not registred that mime type
     *
     *  (this method never returns null)
     *
     *  returns:
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it looks like an image)
     *  @return  mime type | false.
     */
    public static function lenientGuess($filePath)
    {
        $detectResult = self::detect($filePath);
        if ($detectResult === false) {
            // The server does not recognize this image type.
            // - but perhaps it is because it does not know about this image type.
            // - so we turn to mapping the file extension
            return GuessFromExtension::guess($filePath);
        } elseif (is_null($detectResult)) {
            // the mime type could not be determined
            // perhaps we also in this case want to turn to mapping the file extension
            return GuessFromExtension::guess($filePath);
        }
        return $detectResult;
    }



    public static function guessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::guess($filePath), $mimeTypes);
    }

    public static function detectIsIn($filePath, $mimeTypes)
    {
        return in_array(self::detect($filePath), $mimeTypes);
    }

    public static function lenientGuessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::lenientGuess($filePath), $mimeTypes);
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

use \ImageMimeTypeGuesser\Detectors\AbstractDetector;

class ExifImageType extends AbstractDetector
{

    /**
     *  Try to detect mime type of image using "exif_imagetype"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    protected function doDetect($filePath)
    {
        // exif_imagetype is fast, however not available on all systems,
        // It may return false. In that case we can rely on that the file is not an image (and return false)
        if (function_exists('exif_imagetype')) {
            try {
                $imageType = exif_imagetype($filePath);
                return ($imageType ? image_type_to_mime_type($imageType) : false);
            } catch (\Exception $e) {
                // Might for example get "Read error!"
                // well well, don't let this stop us
                //echo $e->getMessage();
//                throw($e);
            }
        }
        return;
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class FInfo extends AbstractDetector
{

    /**
     *  Try to detect mime type of image using finfo
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    protected function doDetect($filePath)
    {

        if (class_exists('finfo')) {
            // phpcs:ignore PHPCompatibility.PHP.NewClasses.finfoFound
            $finfo = new \finfo(FILEINFO_MIME);
            $mime = explode('; ', $finfo->file($filePath));
            $result = $mime[0];

            if (strpos($result, 'image/') === 0) {
                return $result;
            } else {
                return false;
            }

            return $type;
        }
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class GetImageSize extends AbstractDetector
{

    /**
     *  Try to detect mime type of image using "getimagesize"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    protected function doDetect($filePath)
    {
        // getimagesize is slower than exif_imagetype
        // It may not return "mime". In that case we can rely on that the file is not an image (and return false)
        if (function_exists('getimagesize')) {
            try {
                $imageSize = getimagesize($filePath);
                return (isset($imageSize['mime']) ? $imageSize['mime'] : false);
            } catch (\Exception $e) {
                // well well, don't let this stop us either
            }
        }
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class MimeContentType extends AbstractDetector
{

    /**
     *  Try to detect mime type of image using "mime_content_type"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    protected function doDetect($filePath)
    {
        // mime_content_type supposedly used to be deprecated, but it seems it isn't anymore
        // it may return false on failure.
        if (function_exists('mime_content_type')) {
            try {
                $result = mime_content_type($filePath);
                if ($result !== false) {
                    if (strpos($result, 'image/') === 0) {
                        return $result;
                    } else {
                        return false;
                    }
                }
            } catch (\Exception $e) {
                // we are unstoppable!
            }
        }
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class Stack extends AbstractDetector
{

    /**
     *  Try to detect mime type of image using all available detectors
     *  returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    protected function doDetect($filePath)
    {
        $detectors = [
            'ExifImageType',
            'GetImageSize',
            'FInfo',
            'MimeContentType'
        ];

        foreach ($detectors as $className) {
            $result = call_user_func(
                array("\\ImageMimeTypeGuesser\\Detectors\\" . $className, 'detect'),
                $filePath
            );
            if (!is_null($result)) {
                return $result;
            }
        }

        return;     // undetermined
    }
}

