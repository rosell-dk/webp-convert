<?php 
?><?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\DecideWhatToServe;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;

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

/*
    public static $defaultOptions = [
        'add-content-type-header' => true,
        'add-last-modified-header' => true,
        'add-vary-header' => true,
        'add-x-header-status' => true,
        'add-x-header-options' => false,
        'aboutToServeImageCallBack' => null,
        'aboutToPerformFailAction' => null,
        'cache-control-header' => 'public, max-age=86400',
        'converters' =>  ['cwebp', 'gd', 'imagick'],
        'error-reporting' => 'auto',
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',
        'reconvert' => false,
        'serve-original' => false,
        'show-report' => false,
    ];*/

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
     * is larger than the source - in that case the source is served). Some options may alter this logic
     * (see the options of DecideWhatToServe::decide). In case no file exists at the destination, a fresh
     * conversion is made and served.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - All options supported by DecideWhatToServe::decide)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException  If conversion failed
     * @throws  ServeFailedException       If an argument is invalid or source file does not exists
     * @return  void
     */
    public static function serve($source, $destination, $options = [])
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

        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        Header::setHeader('X-WebP-Convert-Action: ' . $msg);

        switch ($whatToServe) {
            case 'destination':
                self::serveDestination($destination, $options);
                break;

            case 'source':
                self::serveOriginal($source, $options);
                break;

            case 'fresh-conversion':
                ConvertAndServeSmallest::serve($source, $destination, $options);
                break;

            case 'report':
                Report::convertAndReport($source, $destination, $options);
                break;
        }
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeConvertedWeb;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a converted webp image and handle errors.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebPWithErrorHandling
{

    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',
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
     * @return void
     */
    public static function performFailAction($fail, $failIfFailFails, $source, $destination, $options)
    {
        self::addHeadersPreventingCaching();

        switch ($fail) {
            case 'original':
                try {
                    ServeConvertedWebP::serveOriginal($source, $options);
                } catch (\Exception $e) {
                    self::performFailAction($failIfFailFails, '404', $source, $destination, $options);
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
     *       - 'fail'       * @throws \WebPConvert\Exceptions\WebPConvertException
     * @return void
=> (string)    Action to take on failure (404 | original | report).
     *               '404' is recommended for development and 'original' is recommended for production.
     *               Default: 'original'.
     *       - 'fail-when-original-unavailable'  => (string)    Action to take if fail action also fails. Default: '404'.
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - All options supported by DecideWhatToServe::decide)
     * @return  void
     */
    public static function serve($source, $destination, $options = [])
    {
        $options = array_merge(self::$defaultOptions, $options);

        try {
            ServeConvertedWebP::serve($source, $destination, $options);
        } catch (\Exception $e) {
            self::performFailAction(
                $options['fail'],
                $options['fail-when-original-unavailable'],
                $source,
                $destination,
                $options
            );
        }
    }
}

?><?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;

/**
 * Decide what to serve based on options, file sizes and file modification dates.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class DecideWhatToServe
{

    public static $defaultOptions = [
        'reconvert' => false,
        'serve-original' => false,
        'show-report' => false,
    ];

    /**
     * Decides what to serve.
     *
     * It both decides what to serve and supplies the reason behind.
     *
     * The possible combinations are:
     *
     * - "destination"  (serve existing converted image at the destination path)
     *      - "no-reason-not-to"
     * - "source"
     *      - "explicitly-told-to"
     *      - "source-lighter"      (happens if a file exists on the destination
     *                               but the source file is smaller)
     * - "fresh-conversion" (note: this may still fail)
     *      - "explicitly-told-to"
     *      - "source-modified"     (happens if a file exists on the destination
     *                               but its modification date is older than that of the source)
     *      - "no-existing"
     * - "fail"
     *       - "Missing destination argument"
     * - "critical-fail"   (a failure where the source file cannot be served)
     *       - "missing-source-argument"
     *       - "source-not-found"
     * - "report"
     *
     * @param  string              $source        The path to the file to convert
     * @param  string              $destination   The path to save the converted file to
     * @param  array               $options       (optional)
     *       Supported options:
     *       'show-report'     => (boolean)   If true, the decision will always be 'report'
     *       'serve-original'  => (boolean)   If true, the decision will be 'source' (unless above option is set)
     *       'reconvert     '  => (boolean)   If true, the decision will be 'fresh-conversion' (unless one of the
     *                                        above options is set)
     *
     * @return  array  Three items: what to serve (id), why to serve (id) and suggested message
     */
    public static function decide($source, $destination, $options)
    {
        $options = array_merge(self::$defaultOptions, $options);

        if ($options['show-report']) {
            return ['report', 'explicitly-told-to', 'Serving report (explicitly told to)'];
        }
        if ($options['serve-original']) {
            return ['source', 'explicitly-told-to', 'Serving original image (was explicitly told to)'];
        }
        if ($options['reconvert']) {
            return ['fresh-conversion', 'explicitly-told-to', 'Serving fresh conversion (was explicitly told to)'];
        }

        if (@file_exists($destination)) {
            // Reconvert if existing conversion is older than the original
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                return [
                    'fresh-conversion',
                    'source-modified',
                    'Serving fresh conversion ' .
                        '(the existing conversion is discarded because original has been modified since then)'
                ];
            }

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($destination);
            $filesizeSource = @filesize($source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                return [
                    'source',
                    'source-lighter',
                    'Serving original image (it is smaller than the already converted)'
                ];
            }

            // Destination exists, and there is no reason left not to serve it
            return ['destination', 'no-reason-not-to', 'Serving existing conversion'];
        } else {
            return [
                'fresh-conversion',
                'no-existing',
                'Serving fresh conversion (there were no existing conversion to serve)'
            ];
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

    public static $defaultOptions = [
        'add-vary-accept-header' => true,
        'set-content-type-header' => true,
        'set-last-modified-header' => true,
        'set-cache-control-header' => true,
        'cache-control-header' => 'public, max-age=86400',
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
     *       'set-content-type-header' => (boolean)   Whether to set *Content-type* header or not. Default: true.
     *       'set-last-modified-header' => (boolean)  Whether to set *Last-Modified* header or not. Default: true.
     *       'set-cache-control-header' => (boolean)  Whether to set *Cache-Control* header or not. Default: true.
     *       'cache-control-header' => string         Cache control header. Default: "public, max-age=86400"
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options = [])
    {
        $options = array_merge(self::$defaultOptions, $options);

        if ($options['set-last-modified-header'] === true) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['set-content-type-header'] === true) {
            Header::setHeader('Content-type: ' . $contentType);
        }

        if ($options['add-vary-accept-header'] === true) {
            Header::addHeader('Vary: Accept');
        }

        if ($options['set-cache-control-header'] === true) {
            if (!empty($options['cache-control-header'])) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);

                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
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
     * @return void
     */
    public static function addHeader($header)
    {
        header($header, false);
    }

    /**
     * Convenience function for replacing header.
     *
     * @return void
     */
    public static function setHeader($header)
    {
        header($header, true);
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
     */
    public static function serveConverted($source, $destination, $options = [])
    {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options);
        }
    }
}

