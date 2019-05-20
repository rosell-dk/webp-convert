<?php 
?><?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;

use WebPConvert\WebPConvert;
use WebPConvert\Serve\Exceptions\ServeFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

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

    /** @var array  Array of default options */
    public static $defaultOptions = [
        'reconvert' => false,
        'serve-original' => false,
        'show-report' => false,
    ];

    /**
     * Serve original file (source).
     *
     * @param   string  $source              path to source file
     * @param   array   $options (optional)  options for serving
     *                  Supported options:
     *                  - All options supported by ServeFile::serve()
     * @throws  ServeFailedException  if source is not an image or mime type cannot be determined
     * @return  void
     */
    public static function serveOriginal($source, $options = [])
    {
        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        if (is_null($contentType)) {
            throw new ServeFailedException('Rejecting to serve original (mime type cannot be determined)');
        } elseif ($contentType === false) {
            throw new ServeFailedException('Rejecting to serve original (it is not an image)');
        } else {
            ServeFile::serve($source, $contentType, $options);
        }
    }

    /**
     * Serve destination file.
     *
     * @param   string  $destination         path to destination file
     * @param   array   $options (optional)  options for serving (such as which headers to add)
     *       Supported options:
     *       - All options supported by ServeFile::serve()
     * @return  void
     */
    public static function serveDestination($destination, $options = [])
    {
        ServeFile::serve($destination, 'image/webp', $options);
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
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException  If conversion failed
     * @throws  ServeFailedException       If an argument is invalid or source file does not exists
     * @return  void
     */
    public static function serve($source, $destination, $options = [], $logger = null)
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

        $options = array_merge(self::$defaultOptions, $options);

        // Step 1: Is there a file at the destination? If not, trigger conversion
        // However 1: if "show-report" option is set, serve the report instead
        // However 2: "reconvert" option should also trigger conversion
        if ($options['show-report']) {
            Header::addLogHeader('Showing report', $logger);
            Report::convertAndReport($source, $destination, $options);
            return;
        }

        if (!@file_exists($destination)) {
            Header::addLogHeader('Converting (there were no file at destination)', $logger);
            WebPConvert::convert($source, $destination, $options, $logger);
        } elseif ($options['reconvert']) {
            Header::addLogHeader('Converting (told to reconvert)', $logger);
            WebPConvert::convert($source, $destination, $options, $logger);
        } else {
            // Step 2: Is the destination older than the source?
            //         If yes, trigger conversion (deleting destination is implicit)
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                    Header::addLogHeader('Converting (destination was older than the source)', $logger);
                    WebPConvert::convert($source, $destination, $options, $logger);
            }
        }

        // Step 3: Serve the smallest file (destination or source)
        // However, first check if 'serve-original' is set
        if ($options['serve-original']) {
            Header::addLogHeader('Serving original (told to)', $logger);
            self::serveOriginal($source, $options);
        }

        $filesizeDestination = @filesize($destination);
        $filesizeSource = @filesize($source);
        if (($filesizeSource !== false) &&
            ($filesizeDestination !== false) &&
            ($filesizeDestination > $filesizeSource)) {
                Header::addLogHeader('Serving original (it is smaller)', $logger);
                self::serveOriginal($source, $options);
        }

        Header::addLogHeader('Serving converted file', $logger);
        self::serveDestination($destination, $options);
    }
}

?><?php
namespace WebPConvert\Serve;

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

    /** @var array  Array of default options */
    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-fail-fails' => 'throw',
    ];

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
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     * @param   string  $serveClass     (optional) Full class name to a class that has a serve() method and a
     *                                  serveOriginal() method
     * @return  void
     */
    public static function serve(
        $source,
        $destination,
        $options = [],
        $logger = null,
        $serveClass = '\\WebPConvert\\Serve\\ServeConvertedWebP'
    ) {
        $options = array_merge(self::$defaultOptions, $options);

        try {
            //ServeConvertedWebP::serve($source, $destination, $options, $logger);
            call_user_func($serveClass . '::serve', $source, $destination, $options, $logger);
        } catch (\Exception $e) {
            if ($e instanceof \WebPConvert\Exceptions\WebPConvertException) {
                Header::addLogHeader($e->getShortMessage(), $logger);
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

    /** @var array  Array of default options */
    public static $defaultOptions = [
        'set-cache-control-header' => false,
        'set-expires-header' => false,
        'cache-control-header' => 'public, max-age=31536000',
        'add-vary-accept-header' => false,
        'set-content-type-header' => true,
        'set-last-modified-header' => true,
        'set-content-length-header' => true,
    ];

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

        $options = array_merge(self::$defaultOptions, $options);

        if ($options['set-last-modified-header']) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['set-content-type-header']) {
            Header::setHeader('Content-Type: ' . $contentType);
        }

        if ($options['add-vary-accept-header']) {
            Header::addHeader('Vary: Accept');
        }

        if (!empty($options['cache-control-header'])) {
            if ($options['set-cache-control-header']) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);
            }
            if ($options['set-expires-header']) {
                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
        }

        if ($options['set-content-length-header']) {
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
     *       The following options are generally supported (individual converters provides more options):
     *       'quality'     => (integer|"auto")   Quality. If set to auto and source image is jpeg, the quality will
     *                                           be set to same as source - if detectable. The detection requires
     *                                           imagick or gmagick. Default: "auto".
     *       'max-quality' => (integer)          Limit quality (relevant only if "quality" is set to "auto").
     *                                           Default: 85.
     *       'default-quality' => (integer)      Default quality (used when auto detection fails). Default: 75
     *       'metadata'    => (string)           Valid values: 'all', 'none', 'exif', 'icc', 'xmp'.
     *                                           Note: Only *cwebp* supports all values. *gd* will always remove all
     *                                           metadata. The rest can either strip all or keep all (they will keep
     *                                           all, unless metadata is set to *none*). Default: 'none'.
     *        'lossless'   => (boolean|"auto")   Whether to convert into the lossless webp format or the lossy.
     *                                           If "auto" is selected, the format that results in the smallest file
     *                                           is selected (two actual conversions are made and the smallest file
     *                                           wins). Note that only *cwebp* and *vips* converters supports
     *                                           the lossless encoding. Converters that does not support lossless
     *                                           simply always converts to lossy encoding (and "auto" will not trigger
     *                                           two conversions for these). Default is "auto" when converting PNGs and
     *                                           false when converting JPEGs. The reason for this default is that it is
     *                                           probably rare that a JPEG is compressed better with lossless encoding
     *                                           (as the jpeg format typically have been choosen only for photos and
     *                                           photos almost always is best encoding with the lossy encoding. On the
     *                                           other hand, graphics and icons are sometimes compressed best with
     *                                           lossy encoding and sometimes best with lossless encoding). Note that
     *                                           you can use the 'png' and 'jpeg' options to set this option different
     *                                           for png and jpegs. Ie: ['png' => ['lossless' => 'auto'], 'jpeg' =>
     *                                           'lossless' => false]].
     *        'skip'       => (boolean)          If set to true, conversion is skipped entirely. Can for example be used
     *                                           to skip pngs for certain converters. You might for example want to use
     *                                           Gd for jpegs and ewww for pngs.
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException   in case conversion fails
     * @return  void
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        //return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
        //return Convert::runConverterStack($source, $destination, $options, $logger);
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
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     */
    public static function serveConverted($source, $destination, $options = [], $logger = null)
    {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options, $logger);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options, $logger);
        }
    }
}

