<?php

namespace WebPConvert\Convert;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Exceptions\CreateDestinationFileException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\TargetNotFoundException;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

class BaseConverter
{
    public $source;
    public $destination;
    public $options;
    public $logger;
    public $beginTime;

    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];
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

    public static function createInstance($source, $destination, $options, $logger)
    {
        return new static($source, $destination, $options, $logger);
    }

    /**
     *
     *
     */
    public function errorHandler($errno, $errstr, $errfile, $errline) {

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

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION . ' (' . PHP_OS . ')';
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
            throw new ConverterFailedException($msg);
        } else {
            $this->logLn($msg);
        }


        // We do not return false, because we want to keep this little secret.
        //
        //return false;   // let PHP handle the error from here
    }

    public static function convert($source, $destination, $options = [], $logger = null)
    {
        $instance = self::createInstance($source, $destination, $options, $logger);

        $instance->prepareConvert();
        $instance->doConvert();
        $instance->finalizeConvert();

        return true;
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

    public static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public function getSourceExtension()
    {
        return self::getExtension($this->source);
    }

    public static function getMimeType($filePath)
    {
        if (function_exists('mime_content_type')) {
            $result = mime_content_type($filePath);
            if ($result !== false) {
                return $result;
            }
        }

        // fallback to using pathinfo.
        $fileExtension = self::getExtension($filePath);
        if ($fileExtension == 'jpg') {
            $fileExtension = 'jpeg';
        }
        return 'image/' . $fileExtension;
    }

    public function getMimeTypeOfSource()
    {
        return self::getMimeType($this->source);
    }

    public function prepareConvert()
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
        $this->runValidations();

        // Prepare options
        $this->prepareOptions();
    }

    // The individual converters can override this...
    public function runValidations()
    {
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

        // Check if the provided file's extension is valid
        /*
        $fileExtension = $this->getSourceExtension();
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new InvalidFileExtensionException('Unsupported file extension: ' . $fileExtension);
        }*/

        // Check if the provided file's mime type is valid

        $fileMimeType = $this->getMimeTypeOfSource();
        if (!in_array($fileMimeType, self::$allowedMimeTypes)) {
            throw new InvalidFileExtensionException('Unsupported mime type: ' . $fileMimeType);
        }
    }

    public function prepareOptions()
    {
        $defaultOptions = self::$defaultOptions;

        // -  Merge defaults of the converters extra options into the standard default options.
        $defaultOptions = array_merge($defaultOptions, array_column(static::$extraOptions, 'default', 'name'));

        // -  Merge $defaultOptions into provided options
        $this->options = array_merge($defaultOptions, $this->options);

        // Prepare quality option (sets "_calculated_quality" option)
        $this->processQualityOption();

        $fileExtension = $this->getSourceExtension();
        if ($fileExtension == 'png') {
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

        return true;
    }

    /* Try to detect quality of jpeg.
       If not possible, nothing is returned (null). Otherwise quality is returned (int)
        */
    public static function detectQualityOfJpg($filename)
    {
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            $img = new \Imagick($filename);

            // The required function is available as from PECL imagick v2.2.2
            if (method_exists($img, 'getImageCompressionQuality')) {
                return $img->getImageCompressionQuality();
            }
        }

        // Gmagick extension doesn't seem to support this (yet):
        // https://bugs.php.net/bug.php?id=63939

        if (function_exists('shell_exec')) {
            // Try Imagick
            $quality = shell_exec("identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }
        }
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
            if (!$q) {
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

        if (!@file_exists($this->destination)) {
            throw new ConverterFailedException('Destination file is not there');
        } else {
            if (!isset($this->options['_suppress_success_message'])) {
                $this->logLn(
                    'Successfully converted image in ' .
                    round((microtime(true) - $this->beginTime) * 1000) . ' ms'
                );

                $sourceSize = @filesize($source);
                if ($sourceSize !== false) {
                    $msg = 'Reduced file size with ' .
                        round((filesize($source) - filesize($destination))/filesize($source) * 100) . '% ';

                    if ($sourceSize < 10000) {
                        $msg .= '(went from ' . round(filesize($source)) . ' bytes to ';
                        $msg .= round(filesize($destination)) . ' bytes)';
                    } else {
                        $msg .= '(went from ' . round(filesize($source)/1024) . ' kb to ';
                        $msg .= round(filesize($destination)/1024) . ' kb)';
                    }
                    $this->logLn($msg);
                }
            }
        }
    }
}
