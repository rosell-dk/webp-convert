<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class ImagickBinary
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

    // Checks if 'Nice' is available
    private static function hasNiceSupport()
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

    public static function escapeFilename($string)
    {

        // filter_var() is should normally be available, but it is not always
        // - https://stackoverflow.com/questions/11735538/call-to-undefined-function-filter-var
        if (function_exists('filter_var')) {
            // Sanitize quotes
            $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

            // Stripping control characters
            // see https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
            $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        }

        // Escaping whitespace. Must be done *after* filter_var!
        $string = preg_replace('/\s/', '\\ ', $string);

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


        // TODO:
        // quality. Like this: 'convert -quality 100 small.jpg small.webp'
        $qualityOption = '';
        //$this->logLn('Using quality:' . $this->getCalculatedQuality());
        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // but luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
        } else {
            $qualityOption = '-quality ' . $options['_calculated_quality'] . ' ';
        }


        // Should we use "magick" or "convert" command?
        // It seems they do the same. But which is best supported? Which is mostly available (whitelisted)?
        // Should we perhaps try both?
        // For now, we just go with "convert"

        $command = 'convert '
            . $qualityOption
            . escapeshellarg($source)
            . ' ' . escapeshellarg('webp:' . $destination);

        // Nice
        $useNice = (($options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $logger->logLn('using nice');
            $command = 'nice ' . $command;
        }

        $logger->logLn('command:' . $command);
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
