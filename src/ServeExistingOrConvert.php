<?php

/**
 * This class is deliberately small.
 * We have divided the labour between this class and the ConvertAndServe class.
 *
 * This class must determine if an existing converted image can and should be served.
 * If so, it must serve it.
 * If not, it must hand the task over to ConvertAndServe
 *
 * The reason for doing it like this is that we want existing images to be served as fast as
 * possible, because that is the thing that will happen most of the time.
 *
 * Anything else, such as error handling and creating new conversion is handed off to ConvertAndServe,
 * (which with composer is only autoloaded when needed)
 */
namespace WebPConvert;

use WebPConvert\Serve\ConvertAndServe;

class ServeExistingOrConvert
{
    public static $defaultOptions = [
        'show-report' => false,
        'reconvert' => false,
        'serve-original' => false,
        'add-x-header-status' => true,
        'add-vary-header' => true,
        'error-reporting' => 'auto'
    ];

    private static function shouldWeServeExisting($source, $destination, $options)
    {
        // We should not serve existing if there are evident problems
        if (!file_exists($source) || !file_exists($destination) || !@is_readable($destination)) {
            return false;
        }

        // We should not serve existing if told directly otherwise
        if ($options['reconvert'] || $options['serve-original'] || $options['show-report']) {
            return false;
        }

        // We should not serve existing if source file is newer than destination
        $timestampSource = @filemtime($source);
        $timestampDestination = @filemtime($destination);
        if (($timestampSource !== false) &&
            ($timestampDestination !== false) &&
            ($timestampSource > $timestampDestination)) {
            return false;
        }

        // We should not serve existing if source file is lighter than destination
        $filesizeDestination = @filesize($destination);
        $filesizeSource = @filesize($source);
        if (($filesizeSource !== false) &&
            ($filesizeDestination !== false) &&
            ($filesizeDestination > $filesizeSource)) {
            return false;  // original image, because converted is larger
        }
        return true;
    }

    public static function serveExisting($destination, $options)
    {
        //echo ':' . $destination;
        header('Content-type: image/webp');

        if ($options['add-x-header-status']) {
            header('X-WebP-Convert-Status: Serving existing converted image');
        }

        if ($options['add-vary-header']) {
            header('Vary: Accept');
        }

        if (@readfile($destination) === false) {
            header('X-WebP-Convert-Error: Could not read file');
            return false;
        }
        return true;
    }

    private static function handOver($source, $destination, $options)
    {
        // Load extra php classes, if told to
        if (isset($options['require-for-conversion'])) {
            require($options['require-for-conversion']);
        }

        // We do not add "Vary Accept" header here, because ConvertAndServe will do that
        // (and we do not unset "add-vary-header", but pass it on)
        unset($options['require-for-conversion']);

        return ConvertAndServe::convertAndServe($source, $destination, $options);
    }

    private static function setErrorReporting($options)
    {
        if (($options['error-reporting'] === true) ||
            (($options['error-reporting'] === 'auto') && ($options['show-report'] === true))
        ) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } elseif (($options['error-reporting'] === false) ||
            (($options['error-reporting'] === 'auto') && ($options['show-report'] === false))
        ) {
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
    }
    public static function serveExistingOrConvert($source, $destination, $options)
    {
        $options = array_merge(self::$defaultOptions, $options);

        self::setErrorReporting($options);
        if (self::shouldWeServeExisting($source, $destination, $options)) {
            return self::serveExisting($destination, $options);
        } else {
            return self::handOver($source, $destination, $options);
        }
    }
}
