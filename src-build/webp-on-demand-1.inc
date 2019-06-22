<?php 
?><?php
namespace WebPConvert\Serve;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Helpers\InputValidator;
use WebPConvert\Helpers\MimeType;
use WebPConvert\Serve\Exceptions\ServeFailedException;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\SensitiveArrayOption;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\WebPConvert;

/**
 * Serve a converted webp image.
 *
 * The webp that is served might end up being one of these:
 * - a fresh convertion
 * - the destionation
 * - the original
 *
 * Exactly which is a decision based upon options, file sizes and file modification dates
 * (see the serve method of this class for details)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebP
{

    /**
     * Process options.
     *
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionTypeException   If the type of an option is invalid
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionValueException  If the value of an option is invalid
     * @param array $options
     */
    private static function processOptions($options)
    {
        $options2 = new Options();
        $options2->addOptions(
            new BooleanOption('reconvert', false),
            new BooleanOption('serve-original', false),
            new BooleanOption('show-report', false),
            new BooleanOption('suppress-warnings', true),
            new ArrayOption('serve-image', []),
            new SensitiveArrayOption('convert', [])
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        return $options2->getOptions();
    }

    /**
     * Serve original file (source).
     *
     * @param   string  $source                        path to source file
     * @param   array   $serveImageOptions (optional)  options for serving an image
     *                  Supported options:
     *                  - All options supported by ServeFile::serve()
     * @throws  ServeFailedException  if source is not an image or mime type cannot be determined
     * @return  void
     */
    public static function serveOriginal($source, $serveImageOptions = [])
    {
        InputValidator::checkSource($source);
        $contentType = MimeType::getMimeTypeDetectionResult($source);
        if (is_null($contentType)) {
            throw new ServeFailedException('Rejecting to serve original (mime type cannot be determined)');
        } elseif ($contentType === false) {
            throw new ServeFailedException('Rejecting to serve original (it is not an image)');
        } else {
            ServeFile::serve($source, $contentType, $serveImageOptions);
        }
    }

    /**
     * Serve destination file.
     *
     * TODO: SHould this really be public?
     *
     * @param   string  $destination                   path to destination file
     * @param   array   $serveImageOptions (optional)  options for serving (such as which headers to add)
     *       Supported options:
     *       - All options supported by ServeFile::serve()
     * @return  void
     */
    public static function serveDestination($destination, $serveImageOptions = [])
    {
        InputValidator::checkDestination($destination);
        ServeFile::serve($destination, 'image/webp', $serveImageOptions);
    }


    public static function warningHandler()
    {
        // do nothing! - as we do not return anything, the warning is suppressed
    }

    /**
     * Serve converted webp.
     *
     * Serve a converted webp. If a file already exists at the destination, that is served (unless it is
     * older than the source - in that case a fresh conversion will be made, or the file at the destination
     * is larger than the source - in that case the source is served). Some options may alter this logic.
     * In case no file exists at the destination, a fresh conversion is made and served.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       'show-report'     => (boolean)   If true, the decision will always be 'report'
     *       'serve-original'  => (boolean)   If true, the decision will be 'source' (unless above option is set)
     *       'reconvert     '  => (boolean)   If true, the decision will be 'fresh-conversion' (unless one of the
     *                                        above options is set)
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     *
     * @throws  \WebPConvert\Exceptions\WebPConvertException  If something went wrong.
     * @return  void
     */
    public static function serve($source, $destination, $options = [], $serveLogger = null, $convertLogger = null)
    {
        InputValidator::checkSourceAndDestination($source, $destination);

        $options = self::processOptions($options);

        if ($options['suppress-warnings']) {
            set_error_handler(
                array('\\WebPConvert\\Serve\\ServeConvertedWebP', "warningHandler"),
                E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE
            );
        }


        //$options = array_merge(self::$defaultOptions, $options);

        // Step 1: Is there a file at the destination? If not, trigger conversion
        // However 1: if "show-report" option is set, serve the report instead
        // However 2: "reconvert" option should also trigger conversion
        if ($options['show-report']) {
            Header::addLogHeader('Showing report', $serveLogger);
            Report::convertAndReport($source, $destination, $options);
            return;
        }

        if (!@file_exists($destination)) {
            Header::addLogHeader('Converting (there were no file at destination)', $serveLogger);
            WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
        } elseif ($options['reconvert']) {
            Header::addLogHeader('Converting (told to reconvert)', $serveLogger);
            WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
        } else {
            // Step 2: Is the destination older than the source?
            //         If yes, trigger conversion (deleting destination is implicit)
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                    Header::addLogHeader('Converting (destination was older than the source)', $serveLogger);
                    WebPConvert::convert($source, $destination, $options['convert'], $convertLogger);
            }
        }

        // Step 3: Serve the smallest file (destination or source)
        // However, first check if 'serve-original' is set
        if ($options['serve-original']) {
            Header::addLogHeader('Serving original (told to)', $serveLogger);
            self::serveOriginal($source, $options['serve-image']);
        }

        $filesizeDestination = @filesize($destination);
        $filesizeSource = @filesize($source);
        if (($filesizeSource !== false) &&
            ($filesizeDestination !== false) &&
            ($filesizeDestination > $filesizeSource)) {
                Header::addLogHeader('Serving original (it is smaller)', $serveLogger);
                self::serveOriginal($source, $options['serve-image']);
        }

        Header::addLogHeader('Serving converted file', $serveLogger);
        self::serveDestination($destination, $options['serve-image']);
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\Helpers\InputValidator;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeConvertedWeb;
use WebPConvert\Serve\Exceptions\ServeFailedException;
use WebPConvert\Exceptions\WebPConvertException;

/**
 * Serve a converted webp image and handle errors.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebPWithErrorHandling
{

    /**
     * Process options.
     *
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionTypeException   If the type of an option is invalid
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionValueException  If the value of an option is invalid
     * @param array $options
     */
    private static function processOptions($options)
    {
        $options2 = new Options();
        $options2->addOptions(
            new StringOption('fail', 'original', ['original', '404', 'throw', 'report']),
            new StringOption('fail-when-fail-fails', 'throw', ['original', '404', 'throw', 'report'])
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        return $options2->getOptions();
    }

    /**
     *  Add headers for preventing caching.
     *
     *  @return  void
     */
    private static function addHeadersPreventingCaching()
    {
        Header::setHeader("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        Header::addHeader("Cache-Control: post-check=0, pre-check=0");
        Header::setHeader("Pragma: no-cache");
    }

    /**
     * Perform fail action.
     *
     * @param  string  $fail                Action to perform (original | 404 | report)
     * @param  string  $failIfFailFails     Action to perform if $fail action fails
     * @param  string  $source              path to source file
     * @param  string  $destination         path to destination
     * @param  array   $options (optional)  options for serving/converting
     * @param  \Exception  $e               exception that was thrown when trying to serve
     * @param   string  $serveClass         (optional) Full class name to a class that has a serveOriginal() method
     * @return void
     */
    public static function performFailAction($fail, $failIfFailFails, $source, $destination, $options, $e, $serveClass)
    {
        self::addHeadersPreventingCaching();

        Header::addLogHeader('Performing fail action: ' . $fail);

        switch ($fail) {
            case 'original':
                try {
                    //ServeConvertedWebP::serveOriginal($source, $options);
                    call_user_func($serveClass . '::serveOriginal', $source, $options);
                } catch (\Exception $e) {
                    self::performFailAction($failIfFailFails, '404', $source, $destination, $options, $e, $serveClass);
                }
                break;

            case '404':
                $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
                Header::setHeader($protocol . " 404 Not Found");
                break;

            case 'report':
                $options['show-report'] = true;
                Report::convertAndReport($source, $destination, $options);
                break;

            case 'throw':
                throw $e;
                break;

            case 'report-as-image':
                // TODO: Implement or discard ?
                break;
        }
    }

    /**
     * Serve webp image and handle errors as specified in the 'fail' option.
     *
     * This method basically wraps ServeConvertedWebP:serve in order to provide exception handling.
     * The error handling is set with the 'fail' option and can be either '404', 'original' or 'report'.
     * If set to '404', errors results in 404 Not Found headers being issued. If set to 'original', an
     * error results in the original being served.
     * Look up the ServeConvertedWebP:serve method to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       - 'fail' => (string)    Action to take on failure (404 | original | report | throw).
     *               "404" or "throw" is recommended for development and "original" is recommended for production.
     *               Default: 'original'.
     *       - 'fail-when-fail-fails'  => (string) Action to take if fail action also fails. Default: '404'.
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - All options supported by DecideWhatToServe::decide)
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     * @param   string  $serveClass     (optional) Full class name to a class that has a serve() method and a
     *                                  serveOriginal() method
     * @return  void
     */
    public static function serve(
        $source,
        $destination,
        $options = [],
        $serveLogger = null,
        $convertLogger = null,
        $serveClass = '\\WebPConvert\\Serve\\ServeConvertedWebP'
    ) {

        $options = self::processOptions($options);
        try {
            InputValidator::checkSourceAndDestination($source, $destination);
            //ServeConvertedWebP::serve($source, $destination, $options, $serveLogger);
            call_user_func($serveClass . '::serve', $source, $destination, $options, $serveLogger, $convertLogger);
        } catch (\Exception $e) {
            if ($e instanceof \WebPConvert\Exceptions\WebPConvertException) {
                Header::addLogHeader($e->getShortMessage(), $serveLogger);
            }

            self::performFailAction(
                $options['fail'],
                $options['fail-when-fail-fails'],
                $source,
                $destination,
                $options,
                $e,
                $serveClass
            );
        }
    }
}

?><?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;
use WebPConvert\Helpers\InputValidator;
use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a file (send to standard output)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeFile
{

    /**
     * Process options.
     *
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionTypeException   If the type of an option is invalid
     * @throws \WebPConvert\Options\Exceptions\InvalidOptionValueException  If the value of an option is invalid
     * @param array $options
     */
    private static function processOptions($options)
    {
        $options2 = new Options();
        $options2->addOptions(
            new ArrayOption('headers', []),
            new StringOption('cache-control-header', 'public, max-age=31536000')
        );
        foreach ($options as $optionId => $optionValue) {
            $options2->setOrCreateOption($optionId, $optionValue);
        }
        $options2->check();
        $options = $options2->getOptions();

        // headers option
        // --------------

        $headerOptions = new Options();
        $headerOptions->addOptions(
            new BooleanOption('cache-control', false),
            new BooleanOption('content-length', true),
            new BooleanOption('content-type', true),
            new BooleanOption('expires', false),
            new BooleanOption('last-modified', true),
            new BooleanOption('vary-accept', false)
        );
        foreach ($options['headers'] as $optionId => $optionValue) {
            $headerOptions->setOrCreateOption($optionId, $optionValue);
        }
        $options['headers'] = $headerOptions->getOptions();
        return $options;
    }

    /**
     * Serve existing file.
     *
     * @param  string  $filename     File to serve (absolute path)
     * @param  string  $contentType  Content-type (used to set header).
     *                                    Only used when the "set-content-type-header" option is set.
     *                                    Set to ie "image/jpeg" for serving jpeg file.
     * @param  array   $options      Array of named options (optional).
     *       Supported options:
     *       'add-vary-accept-header'  => (boolean)   Whether to add *Vary: Accept* header or not. Default: true.
     *       'set-content-type-header' => (boolean)   Whether to set *Content-Type* header or not. Default: true.
     *       'set-last-modified-header' => (boolean)  Whether to set *Last-Modified* header or not. Default: true.
     *       'set-cache-control-header' => (boolean)  Whether to set *Cache-Control* header or not. Default: true.
     *       'cache-control-header' => string         Cache control header. Default: "public, max-age=86400"
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options = [])
    {
        // Check mimetype - this also checks that path is secure and file exists
        InputValidator::checkMimeType($filename, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ]);

        /*
        if (!file_exists($filename)) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }*/

        $options = self::processOptions($options);

        if ($options['headers']['last-modified']) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['headers']['content-type']) {
            Header::setHeader('Content-Type: ' . $contentType);
        }

        if ($options['headers']['vary-accept']) {
            Header::addHeader('Vary: Accept');
        }

        if (!empty($options['cache-control-header'])) {
            if ($options['headers']['cache-control']) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);
            }
            if ($options['headers']['expires']) {
                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
        }

        if ($options['headers']['content-length']) {
            Header::setHeader('Content-Length: ' . filesize($filename));
        }

        if (@readfile($filename) === false) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }
    }
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
    public $description = '';
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

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class InvalidInputException extends WebPConvertException
{
    public $description = 'Invalid input';
}

?><?php

namespace WebPConvert\Exceptions\InvalidInput;

use WebPConvert\Exceptions\InvalidInputException;

class InvalidImageTypeException extends InvalidInputException
{
    public $description = 'The converter does not handle the supplied image type';
}

?><?php

namespace WebPConvert\Exceptions\InvalidInput;

use WebPConvert\Exceptions\InvalidInputException;

class TargetNotFoundException extends InvalidInputException
{
    public $description = 'The converter could not locate source file';
}

?><?php

namespace WebPConvert\Helpers;

use WebPConvert\Exceptions\InvalidInputException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;

/**
 * Functions for sanitizing.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.6
 */
class PathChecker
{

     /**
      * Check absolute file path to prevent attacks.
      *
      * - Prevents non printable characters
      * - Prevents stream wrappers
      * - Prevents directory traversal
      *
      * Preventing non printable characters is especially done to prevent the NUL character, which can be used
      * to bypass other tests. See https://st-g.de/2011/04/doing-filename-checks-securely-in-PHP.
      *
      * Preventeng stream wrappers is especially done to protect against Phar Deserialization.
      * See https://blog.ripstech.com/2018/new-php-exploitation-technique/
      *
      * @param  string  $absFilePath
      * @return string  sanitized file path
      */
    public static function checkAbsolutePath($absFilePath, $text = 'file')
    {
        if (empty($absFilePath)) {
            throw new InvalidInputException('Empty filepath for ' . $text);
        }

        // Prevent non printable characters
        /*
        if (!ctype_print($absFilePath)) {
            throw new InvalidInputException('Non-printable characters are not allowed in ' . $text);
        }*/

        // Prevent control characters (at least the first 32 (#0 - #1f)
        if (preg_match('#[\x{0}-\x{1f}]#', $absFilePath)) {
            throw new InvalidInputException('Non-printable characters are not allowed');
        }

        // Prevent directory traversal
        if (preg_match('#\.\.\/#', $absFilePath)) {
            throw new InvalidInputException('Directory traversal is not allowed in ' . $text . ' path');
        }

        // Prevent stream wrappers ("phar://", "php://" and the like)
        // https://www.php.net/manual/en/wrappers.phar.php
        if (preg_match('#^\\w+://#', $absFilePath)) {
            throw new InvalidInputException('Stream wrappers are not allowed in ' . $text . ' path');
        }
    }

    public static function checkAbsolutePathAndExists($absFilePath, $text = 'file')
    {
        if (empty($absFilePath)) {
            throw new TargetNotFoundException($text . ' argument missing');
        }
        self::checkAbsolutePath($absFilePath, $text);
        if (@!file_exists($absFilePath)) {
            throw new TargetNotFoundException($text . ' file was not found');
        }
        if (@is_dir($absFilePath)) {
            throw new InvalidInputException($text . ' is a directory');
        }
    }

    /**
     *  Checks that source path is secure, file exists and it is not a dir.
     *
     *  To also check mime type, use InputValidator::checkSource
     */
    public static function checkSourcePath($source)
    {
        self::checkAbsolutePathAndExists($source, 'source');
    }

    public static function checkDestinationPath($destination)
    {
        if (empty($destination)) {
            throw new InvalidInputException('Destination argument missing');
        }
        self::checkAbsolutePath($destination, 'destination');
        if (@is_dir($destination)) {
            throw new InvalidInputException('Destination is a directory');
        }
    }

    public static function checkSourceAndDestinationPaths($source, $destination)
    {
        self::checkSourcePath($source);
        self::checkDestinationPath($destination);
    }
}

?><?php

namespace WebPConvert\Helpers;

use WebPConvert\Helpers\MimeType;
use WebPConvert\Helpers\PathChecker;
use WebPConvert\Exceptions\InvalidInputException;
use WebPConvert\Exceptions\InvalidInput\InvalidImageTypeException;

/**
 * Functions for sanitizing.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.6
 */
class InputValidator
{

    private static $allowedMimeTypes = [
        'image/jpeg',
        'image/png'
    ];

    /**
     * Check mimetype and if file path is ok and exists
     */
    public static function checkMimeType($filePath, $allowedMimeTypes = null)
    {
        if (is_null($allowedMimeTypes)) {
            $allowedMimeTypes = self::$allowedMimeTypes;
        }
        // the following also tests that file path is ok and file exists
        $fileMimeType = MimeType::getMimeTypeDetectionResult($filePath);

        if (is_null($fileMimeType)) {
            throw new InvalidImageTypeException('Image type could not be detected');
        } elseif ($fileMimeType === false) {
            throw new InvalidImageTypeException('File seems not to be an image.');
        } elseif (!in_array($fileMimeType, $allowedMimeTypes)) {
            throw new InvalidImageTypeException('Unsupported mime type: ' . $fileMimeType);
        }
    }

    public static function checkSource($source)
    {
        PathChecker::checkSourcePath($source);
        self::checkMimeType($source);
    }

    public static function checkDestination($destination)
    {
        PathChecker::checkDestinationPath($destination);
    }

    public static function checkSourceAndDestination($source, $destination)
    {
        self::checkSource($source);
        self::checkDestination($destination);
    }
}

?><?php
namespace WebPConvert\Serve;

/**
 * Add / Set HTTP header.
 *
 * This class does nothing more than adding two convenience functions for calling the "header" function.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Header
{
    /**
     * Convenience function for adding header (append).
     *
     * @param  string  $header  The header to add.
     * @return void
     */
    public static function addHeader($header)
    {
        header($header, false);
    }

    /**
     * Convenience function for replacing header.
     *
     * @param  string  $header  The header to set.
     * @return void
     */
    public static function setHeader($header)
    {
        header($header, true);
    }

    /**
     * Add log header and optionally send it to a logger as well.
     *
     * @param  string  $msg  Message to add to "X-WebP-Convert-Log" header
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     * @return void
     */
    public static function addLogHeader($msg, $logger = null)
    {
        self::addHeader('X-WebP-Convert-Log: ' . $msg);
        if (!is_null($logger)) {
            $logger->logLn($msg);
        }
    }
}

?><?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
//use WebPConvert\Serve\ServeExistingOrHandOver;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\ServeConvertedWebPWithErrorHandling;

/**
 * Convert images to webp and/or serve them.
 *
 * This class is just a couple of convenience methods for doing conversion and/or
 * serving.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class WebPConvert
{

    /**
     * Convert jpeg or png into webp
     *
     * Convenience method for calling Stack::convert.
     *
     * @param  string  $source       The image to convert (absolute,no backslashes)
     *                               Image must be jpeg or png.
     * @param  string  $destination  Where to store the converted file (absolute path, no backslashes).
     * @param  array   $options      (optional) Array of named options
     *                               The options are documented here:
     *                            https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException   in case conversion fails
     * @return  void
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        Stack::convert($source, $destination, $options, $logger);
    }

    /**
     * Serve webp image, converting first if neccessary.
     *
     * If an image already exists, it will be served, unless it is older or larger than the source. (If it is larger,
     * the original is served, if it is older, the existing webp will be deleted and a fresh conversion will be made
     * and served). In case of error, the action indicated in the 'fail' option will be triggered (default is to serve
     * the original). Look up the ServeConvertedWebP:serve() and the ServeConvertedWebPWithErrorHandling::serve()
     * methods to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting. The options are documented in the
     *                                       ServeConvertedWebPWithErrorHandling::serve() method
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     * @return void
     */
    public static function serveConverted(
        $source,
        $destination,
        $options = [],
        $serveLogger = null,
        $convertLogger = null
    ) {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options, $serveLogger, $convertLogger);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options, $serveLogger, $convertLogger);
        }
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
     *  Make a wild guess based on file extension.
     *
     *  - and I mean wild!
     *
     *  Only most popular image types are recognized.
     *  Many are not. See this list: https://www.iana.org/assignments/media-types/media-types.xhtml
     *                - and the constants here: https://secure.php.net/manual/en/function.exif-imagetype.php
     *
     *  If no mapping found, nothing is returned
     *
     *  TODO: jp2, jpx, ...
     * Returns:
     * - mimetype (if file extension could be mapped to an image type),
     * - false (if file extension could be mapped to a type known not to be an image type)
     * - null (if file extension could not be mapped to any mime type, using our little list)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if file extension could be mapped to an image type),
     *    false (if file extension could be mapped to a type known not to be an image type)
     *    or null (if file extension could not be mapped to any mime type, using our little list)
     */
    public static function guess($filePath)
    {
        if (!@file_exists($filePath)) {
            return false;
        }
        /*
        Not using pathinfo, as it is locale aware, and I'm not sure if that could lead to problems

        if (!function_exists('pathinfo')) {
            // This is really a just in case! - We do not expect this to happen.
            // - in fact we have a test case asserting that this does not happen.
            return null;
            //
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $fileExtension = strtolower($fileExtension);
        }*/

        $result = preg_match('#\\.([^.]*)$#', $filePath, $matches);
        if ($result !== 1) {
            return null;
        }
        $fileExtension = $matches[1];

        // Trivial image mime types
        if (in_array($fileExtension, ['bmp', 'gif', 'jpeg', 'png', 'tiff', 'webp'])) {
            return 'image/' . $fileExtension;
        }

        // Common extensions that are definitely not images
        if (in_array($fileExtension, ['txt', 'doc', 'zip', 'gz', 'exe'])) {
            return false;
        }

        // Non-trivial image mime types
        switch ($fileExtension) {
            case 'ico':
                return 'image/vnd.microsoft.icon';      // or perhaps 'x-icon' ?

            case 'jpg':
                return 'image/jpeg';

            case 'svg':
                return 'image/svg+xml';

            case 'tif':
                return 'image/tiff';
        }

        // We do not know this extension, return null
        return null;
    }

}

?><?php

/**
 * ImageMimeTypeGuesser - Detect / guess mime type of an image
 *
 * The library is born out of a discussion here:
 * https://github.com/rosell-dk/webp-convert/issues/98
 *
 * @link https://github.com/rosell-dk/image-mime-type-guesser
 * @license MIT
 */

namespace ImageMimeTypeGuesser;

use \ImageMimeTypeGuesser\Detectors\Stack;

class ImageMimeTypeGuesser
{


    /**
     * Try to detect mime type of image using all available detectors (the "stack" detector).
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    public static function detect($filePath)
    {
        return Stack::detect($filePath);
    }

    /**
     * Try to detect mime type of image. If that fails, make a guess based on the file extension.
     *
     * Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     * If that fails (null), fall back to wild west guessing based solely on file extension.
     *
     * Returns:
     * - mime type (string) (if it is an image, and type could be determined / mapped from file extension))
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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
     * Try to detect mime type of image. If that fails, make a guess based on the file extension.
     *
     * Try to detect mime type of image using "stack" detector (all available methods, until one succeeds)
     * If that fails (false or null), fall back to wild west guessing based solely on file extension.
     *
     * Returns:
     * - mime type (string) (if it is an image, and type could be determined / mapped from file extension)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined / guessed),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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


    /**
     * Check if the *detected* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected mime type is in the $mimeTypes array or not
     */
    public static function detectIsIn($filePath, $mimeTypes)
    {
        return in_array(self::detect($filePath), $mimeTypes);
    }

    /**
     * Check if the *guessed* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected / guessed mime type is in the $mimeTypes array or not
     */
    public static function guessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::guess($filePath), $mimeTypes);
    }

    /**
     * Check if the *leniently guessed* mime type is in a list of accepted mime types.
     *
     * @param  string  $filePath  The path to the file
     * @param  string[]  $mimeTypes  Mime types to accept
     * @return bool  Whether the detected / leniently guessed mime type is in the $mimeTypes array or not
     */
    public static function lenientGuessIsIn($filePath, $mimeTypes)
    {
        return in_array(self::lenientGuess($filePath), $mimeTypes);
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

use ImageMimeTypeGuesser\Detectors\AbstractDetector;

abstract class AbstractDetector
{
    /**
     * Try to detect mime type of image
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    abstract protected function doDetect($filePath);

    /**
     * Create an instance of this class
     *
     * @param  string  $filePath  The path to the file
     * @return static
     */
    public static function createInstance()
    {
        return new static();
    }

    /**
     * Detect mime type of file (for images only)
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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

namespace ImageMimeTypeGuesser\Detectors;

use \ImageMimeTypeGuesser\Detectors\AbstractDetector;

class ExifImageType extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *exif_imagetype*.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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
        return null;
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class FInfo extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *finfo* class.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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
        }
        return null;
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class GetImageSize extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *getimagesize()*.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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
                return null;
            }
        }
        return null;
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class MimeContentType extends AbstractDetector
{

    /**
     * Try to detect mime type of image using *mime_content_type()*.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
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
        return null;
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

use \ImageMimeTypeGuesser\Detectors\AbstractDetector;

class SniffFirstFourBytes extends AbstractDetector
{

    /**
     * Try to detect mime type by sniffing the first four bytes.
     *
     * Credits: Based on the code here: http://phil.lavin.me.uk/2011/12/php-accurately-detecting-the-type-of-a-file/
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    protected function doDetect($filePath)
    {
        // PNG, GIF, JFIF JPEG, EXIF JPEF (respectively)
        $known = [
            '89504E47' => 'image/png',
            '47494638' => 'image/gif',
            'FFD8FFE0' => 'image/jpeg',  //  JFIF JPEG
            'FFD8FFE1' => 'image/jpeg',  //  EXIF JPEG
        ];

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return null;
        }
        $firstFour = @fread($handle, 4);
        if ($firstFour === false) {
            return null;
        }
        $key = strtoupper(bin2hex($firstFour));
        if (isset($known[$key])) {
            return $known[$key];
        }
    }
}

?><?php

namespace ImageMimeTypeGuesser\Detectors;

class Stack extends AbstractDetector
{
    /**
     * Try to detect mime type of image using all available detectors.
     *
     * Returns:
     * - mime type (string) (if it is in fact an image, and type could be determined)
     * - false (if it is not an image type that the server knowns about)
     * - null  (if nothing can be determined)
     *
     * @param  string  $filePath  The path to the file
     * @return string|false|null  mimetype (if it is an image, and type could be determined),
     *    false (if it is not an image type that the server knowns about)
     *    or null (if nothing can be determined)
     */
    protected function doDetect($filePath)
    {
        $detectors = [
            'ExifImageType',
            'FInfo',
            'SniffFirstFourBytes',
            'GetImageSize',
            'MimeContentType',
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

        return null;     // undetermined
    }
}

