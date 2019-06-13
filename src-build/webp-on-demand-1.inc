<?php 
?><?php
namespace WebPConvert\Serve;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
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
        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
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
     * @param   string  $destination                   path to destination file
     * @param   array   $serveImageOptions (optional)  options for serving (such as which headers to add)
     *       Supported options:
     *       - All options supported by ServeFile::serve()
     * @return  void
     */
    public static function serveDestination($destination, $serveImageOptions = [])
    {
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

        if (empty($source)) {
            throw new ServeFailedException('Source argument missing');
        }
        if (empty($destination)) {
            throw new ServeFailedException('Destination argument missing');
        }
        if (@!file_exists($source)) {
            throw new ServeFailedException('Source file was not found');
        }

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

        //Header::addLogHeader('Failure');
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
        if (!file_exists($filename)) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }

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

