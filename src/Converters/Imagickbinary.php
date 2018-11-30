<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class ImagickBinary
{
    public static $extraOptions = [];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('imagickbinary', $source, $destination, $options, true);
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


    public static function escapeFilename($string)
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

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!function_exists('exec')) {
            throw new ConverterNotOperationalException('exec() is not enabled.');
        }


        if (!self::imagickInstalled()) {
            throw new ConverterNotOperationalException('imagick is not installed');
        }


        // Should we use "magick" or "convert" command?
        // It seems they do the same. But which is best supported? Which is mostly available (whitelisted)?
        // Should we perhaps try both?
        // For now, we just go with "convert"
        $command = 'convert ' . self::escapeFilename($source) . ' webp:' . self::escapeFilename($destination);
        exec($command, $output, $returnCode);

        if ($returnCode == 127) {
            throw new ConverterNotOperationalException('imagick is not installed');
        }

        if ($returnCode != 0) {
            if (!self::webPDelegateInstalled()) {
                throw new ConverterNotOperationalException('webp delegate missing');
            }
            
            $logger->logLn('command:' . $command);
            $logger->logLn('return code:' . $returnCode);
            $logger->logLn('output:' . print_r(implode("\n", $output), true));

            throw new ConverterNotOperationalException('The exec call failed');
        }
    }
}
