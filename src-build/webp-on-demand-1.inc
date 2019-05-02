<?php 
?><?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\DecideWhatToServe;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;

use WebPConvert\Serve\Exceptions\ServeFailedException;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

/**
 * Serve a converted webp image.
 *
 * The webp that is served might end up being one of these:
 * - a fresh convertion
 * - the destionation
 * - the original
 *
 * Exactly which is a decision based upon options, file sizes and file modification dates (see DecideWhatToServe class)
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
     * @throws  ServeFailedException  if source is not an image or mime type cannot be determined
     */
    public static function serveOriginal($source, $options)
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

    public static function serveDestination($destination, $options)
    {
        ServeFile::serve($destination, 'image/webp', $options);
    }

    /**
     * Serve converted webp.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *
     * @throws  ServeFailedException  If an argument is invalid or source file does not exists
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
                ServeFreshConversion::serve($source, $destination, $options);
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

    private static function addHeadersPreventingCaching()
    {
        Header::setHeader("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        Header::addHeader("Cache-Control: post-check=0, pre-check=0");
        Header::setHeader("Pragma: no-cache");
    }

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
                // TODO
                break;
        }
    }

    /**
     * Serve webp image and handle errors.
     *
     * @throws  ServeFailedException  If an argument is invalid or source file does not exists
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
     * The possible combinations are:
     *
     * - "destination"  (serve existing converted image at the destination path)
     *      - "no-reason-not-to"
     * - "source"
     *      - "explicitly-told-to"
     *      - "source-lighter"
     * - "fresh-conversion" (note: this may still fail)
     *      - "explicitly-told-to"
     *      - "source-modified"
     *      - "no-existing"
     * - "fail"
     *       - "Missing destination argument"
     * - "critical-fail"   (a failure where the source file cannot be served)
     *       - "missing-source-argument"
     *       - "source-not-found"
     * - "report"
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
     * Serve existing file
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options)
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
    public static function addHeader($header)
    {
        header($header, false);
    }

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
     * @param  array   $options      (optional) Array of named options, such as 'quality' and 'metadata'.
     * @param   \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws \WebPConvert\Exceptions\WebPConvertException
     * @return void
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        //return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
        //return Convert::runConverterStack($source, $destination, $options, $logger);
        Stack::convert($source, $destination, $options, $logger);
    }

    public static function convertAndServe($source, $destination, $options = [])
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

