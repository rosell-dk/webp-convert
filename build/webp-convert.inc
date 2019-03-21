<?php 
?><?php

namespace WebPConvert\Exceptions;

class WebPConvertBaseException extends \Exception
{
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

namespace WebPConvert;

use WebPConvert\Converters\ConverterHelper;
use WebPConvert\ServeExistingOrConvert;
use WebPConvert\Serve\ServeExistingOrHandOver;

class WebPConvert
{

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
    }

    public static function convertAndServe($source, $destination, $options = [])
    {
        //return ServeExistingOrConvert::serveExistingOrConvert($source, $destination, $options);
        return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
    }
}

?><?php

namespace WebPConvert\Converters;

//use WebPConvert\Converters\Cwebp;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Exceptions\CreateDestinationFileException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\TargetNotFoundException;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class ConverterHelper
{
    public static $availableConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary', 'wpc', 'ewww'];
    public static $localConverters = ['cwebp', 'gd', 'imagick', 'gmagick', 'imagickbinary'];

    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public static $defaultOptions = [
        'quality' => 'auto',
        'max-quality' => 85,
        'default-quality' => 75,
        'metadata' => 'none',
        'method' => 6,
        'low-memory' => false,
        'lossless' => false,
        'converters' =>  ['cwebp', 'gd', 'imagick', 'gmagick'],
        'converter-options' => []
    ];

    public static function mergeOptions($options, $extraOptions)
    {
        return $options;
    }

    public static function getClassNameOfConverter($converterId)
    {
        return 'WebPConvert\\Converters\\' . ucfirst($converterId);
    }

    /* Call the "convert" method on a converter, by id.
       - but also prepares options (merges in the $extraOptions of the converter),
         prepares destination folder, and runs some standard validations
       If it fails, it throws an exception. Otherwise it don't (there is no return value)
         */
    public static function runConverter(
        $converterId,
        $source,
        $destination,
        $options = [],
        $prepareDestinationFolder = true,
        $logger = null
    ) {


        if ($prepareDestinationFolder) {
            self::prepareDestinationFolderAndRunCommonValidations($source, $destination);
        }

        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }

        $className = self::getClassNameOfConverter($converterId);
        if (!is_callable([$className, 'convert'])) {
            throw new ConverterNotFoundException();
        }

        // Prepare options.
        // -  Remove 'converters'
        $defaultOptions = self::$defaultOptions;
        unset($defaultOptions['converters']);

        // -  Merge defaults of the converters extra options into the standard default options.
        $defaultOptions = array_merge($defaultOptions, array_column($className::$extraOptions, 'default', 'name'));

        // -  Merge $defaultOptions into provided options
        $options = array_merge($defaultOptions, $options);

        // Individual converters do not accept quality = auto. They need a number.
        // Change $options['quality'] to number, based on quality of source and several settings

        self::processQualityOption($source, $options, $logger);

        call_user_func(
            [$className, 'doConvert'],
            $source,
            $destination,
            $options,
            $logger
        );

        if (!@file_exists($destination)) {
            throw new ConverterFailedException('Destination file is not there');
        } elseif (@filesize($destination) === 0) {
            @unlink($destination);
            throw new ConverterFailedException('Destination file was completely empty');
        } else {
            $sourceSize = @filesize($source);
            if ($sourceSize !== false) {
                $msg = 'Success. ';
                $msg .= 'Reduced file size with ' .
                    round((filesize($source) - filesize($destination))/filesize($source) * 100) . '% ';

                if ($sourceSize < 10000) {
                    $msg .= '(went from ' . round(filesize($source)) . ' bytes to ';
                    $msg .= round(filesize($destination)) . ' bytes)';
                } else {
                    $msg .= '(went from ' . round(filesize($source)/1024) . ' kb to ';
                    $msg .= round(filesize($destination)/1024) . ' kb)';
                }
                $logger->logLn($msg);
            }
        }
    }

    public static function runConverterWithTiming(
        $converterId,
        $source,
        $destination,
        $options = [],
        $prepareDestinationFolder = true,
        $logger = null
    ) {
        $beginTime = microtime(true);
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        try {
            self::runConverter($converterId, $source, $destination, $options, $prepareDestinationFolder, $logger);
            $logger->logLn(
                'Successfully converted image in ' .
                round((microtime(true) - $beginTime) * 1000) . ' ms'
            );
        } catch (\Exception $e) {
            $logger->logLn('Failed in ' . round((microtime(true) - $beginTime) * 1000) . ' ms');
            throw $e;
        }
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function runConverterStack($source, $destination, $options = [], $logger = null)
    {
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        self::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $options = array_merge(self::$defaultOptions, $options);

        self::processQualityOption($source, $options, $logger);

        // Force lossless option to true for PNG images
        if (self::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        $defaultConverterOptions = $options;
        $defaultConverterOptions['converters'] = null;

        $firstFailException = null;

        // If we have set converter options for a converter, which is not in the converter array,
        // then we add it to the array
        if (isset($options['converter-options'])) {
            foreach ($options['converter-options'] as $converterName => $converterOptions) {
                if (!in_array($converterName, $options['converters'])) {
                    $options['converters'][] = $converterName;
                }
            }
        }

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

            try {
                $logger->logLn('Trying:' . $converterId, 'italic');

                // If quality is different, we must recalculate
                if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                    unset($converterOptions['_calculated_quality']);
                    self::processQualityOption($source, $converterOptions, $logger);
                }

                self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $logger->logLn('ok', 'bold');
                return true;
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
//                $logger->logLnLn($e->description . ' : ' . $e->getMessage());
                $logger->logLnLn($e->getMessage());

                // The converter is not operational.
                // Well, well, we will just have to try the next, then
            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                $logger->logLnLn($e->getMessage());

                // Converter failed in an anticipated, yet somewhat surprising fashion.
                // The converter seemed operational - requirements was in order - but it failed anyway.
                // This is moderately bad.
                // If some other converter can handle the conversion, we will let this one go.
                // But if not, we shall throw the exception

                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                $logger->logLnLn($e->getMessage());

                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                // We also possibly rethrow this, because it may have come as a surprise to the user
                // who perhaps only tested jpg
                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            }
        }

        if ($firstFailException) {
            // At least one converter failed or declined.
            $logger->logLn('Conversion failed. None of the tried converters could convert the image', 'bold');
        } else {
            // All converters threw a ConverterNotOperationalException
            $logger->logLn('Conversion failed. None of the tried converters are operational', 'bold');
        }

        // No converters could do the job.
        // If one of them failed moderately bad, rethrow that exception.
        if ($firstFailException) {
            throw $firstFailException;
        }

        return false;
    }

    /* Try to detect quality of jpeg.
       If not possible, nothing is returned (null). Otherwise quality is returned (int)
        */
    public static function detectQualityOfJpg($filename)
    {
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            // Do not risk uncaught ImagickException when trying to detect quality of jpeg
            // (it can happen in the rare case, there is no jpeg delegate)
            try {
                $img = new \Imagick($filename);

                // The required function is available as from PECL imagick v2.2.2
                // (you can see your version like this: phpversion("imagick"))
                if (method_exists($img, 'getImageCompressionQuality')) {
                    return $img->getImageCompressionQuality();
                }
            } catch (\Exception $e) {
                // do nothing.
            }
        }

        // Gmagick extension doesn't support dectecting image quality (yet):
        // https://bugs.php.net/bug.php?id=63939
        // It is not supported in 2.0.5RC1. But perhaps there is a new version out now?
        // Check here: https://pecl.php.net/package-changelog.php?package=gmagick

        if (function_exists('shell_exec')) {
            // Try Imagick
            $quality = shell_exec("identify -format '%Q' " . escapeshellarg($filename));
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . escapeshellarg($filename));
            if ($quality) {
                return intval($quality);
            }
        }
    }

    public static function processQualityOption($source, &$options, $logger)
    {
        if (isset($options['_calculated_quality'])) {
            return;
        }

        if ($options['quality'] == 'auto') {
            $q = self::detectQualityOfJpg($source);
            //$logger->log('Quality set to auto... Quality of source: ');
            if (!$q) {
                $q = $options['default-quality'];
                $logger->logLn(
                    'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                    ' - Using default instead (' . $options['default-quality'] . ').'
                );

                // this allows the wpc converter to know
                $options['_quality_could_not_be_detected'] = true;
            } else {
                if ($q > $options['max-quality']) {
                    $logger->log(
                        'Quality of source is ' . $q . '. ' .
                        'This is higher than max-quality, so using that instead (' . $options['max-quality'] . ')'
                    );
                } else {
                    $logger->log('Quality set to same as source: ' . $q);
                }
            }
            $logger->ln();
            $q = min($q, $options['max-quality']);

            $options['_calculated_quality'] = $q;
        //$logger->logLn('Using quality: ' . $options['quality']);
        } else {
            $logger->logLn(
                'Quality: ' . $options['quality'] . '. ' .
                'Consider setting quality to "auto" instead. It is generally a better idea'
            );
            $options['_calculated_quality'] = $options['quality'];
        }
        $logger->ln();
    }


    public static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    // Throws an exception if the provided file doesn't exist
    public static function isValidTarget($filePath)
    {
        if (!@file_exists($filePath)) {
            throw new TargetNotFoundException('File or directory not found: ' . $filePath);
        }

        return true;
    }

    // Throws an exception if the provided file's extension is invalid
    public static function isAllowedExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new InvalidFileExtensionException('Unsupported file extension: ' . $fileExtension);
        }

        return true;
    }

    // Creates folder in provided path & sets correct permissions
    // also deletes the file at filePath (if it already exists)
    public static function createWritableFolder($filePath)
    {
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

    public static function prepareDestinationFolderAndRunCommonValidations($source, $destination)
    {
        self::isValidTarget($source);
        self::isAllowedExtension($source);
        self::createWritableFolder($destination);
    }

    public static function initCurlForConverter()
    {
        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException(
                'Required curl_file_create() function is not available (requires PHP > 5.5).'
            );
        }

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }
        return $ch;
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Cwebp
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

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('cwebp', $source, $destination, $options, true);
    }

    // System paths to look for cwebp binary
    private static $cwebpDefaultPaths = [
        '/usr/bin/cwebp',
        '/usr/local/bin/cwebp',
        '/usr/gnu/bin/cwebp',
        '/usr/syno/bin/cwebp'
    ];

    // OS-specific binaries included in this library, along with hashes
    private static $suppliedBinariesInfo = [
        'WinNT' => [ 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'],
        'Darwin' => [ 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'],
        'SunOS' => [ 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'],
        'FreeBSD' => [ 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'],
        'Linux' => [ 'cwebp-linux', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568']
    ];

    private static function escapeFilename($string)
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
    public static function doConvert($source, $destination, $options, $logger)
    {
        $errorMsg = '';
        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        if (!function_exists('exec')) {
            throw new ConverterNotOperationalException('exec() is not enabled.');
        }

        /*
         * Prepare cwebp options
         */

        $commandOptionsArray = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $commandOptionsArray[] = '-metadata ' . $options['metadata'];

        // Size
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource =  @filesize($source);
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
        //$commandOptionsArray[] = self::escapeFilename($source);
        $commandOptionsArray[] = escapeshellarg($source);

        // Output
        $commandOptionsArray[] = '-o ' . escapeshellarg($destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $commandOptionsArray[] = '2>&1';


        $useNice = (($options['use-nice']) && self::hasNiceSupport()) ? true : false;

        $commandOptions = implode(' ', $commandOptionsArray);

        $logger->logLn('cwebp options:' . $commandOptions);

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
                $returnCode = self::executeBinary($binary, $commandOptions, $useNice, $logger);
                if ($returnCode == 0) {
                    $logger->logLn('Successfully executed binary: ' . $binary);
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
                        $returnCode = self::executeBinary($binaryFile, $commandOptions, $useNice, $logger);
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
            $destinationParent = dirname($destination);
            $fileStatistics = @stat($destinationParent);
            if ($fileStatistics !== false) {
                // Apply same permissions as parent folder but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                @chmod($destination, $permissions);
            }
        }

        if (!$success) {
            throw new ConverterNotOperationalException($errorMsg);
        }
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Ewww
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

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('ewww', $source, $destination, $options, true);
    }

    // Took this parser from Drupal
    private static function parseSize($size)
    {

        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
            $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
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

        $fileSize = @filesize($source);
        if ($fileSize !== false) {
            $uploadMaxSize = self::parseSize(ini_get('upload_max_filesize'));
            if (($uploadMaxSize !== false) && ($uploadMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your max upload (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'upload_max_filesize in php.ini: ' . ini_get('upload_max_filesize') .
                        ' (parsed as ' . round($uploadMaxSize/1024) . ' kb)'
                );
            }

            $postMaxSize = self::parseSize(ini_get('post_max_size'));
            if (($postMaxSize !== false) && ($postMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your post_max_size limit (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'post_max_size in php.ini: ' . ini_get('post_max_size') .
                        ' (parsed as ' . round($postMaxSize/1024) . ' kb)'
                );
            }

            // ini_get('memory_limit')
        }


        $ch = ConverterHelper::initCurlForConverter();

        $curlOptions = [
            'api_key' => $options['key'],
            'webp' => '1',
            'file' => curl_file_create($source),
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
            throw new ConverterNotOperationalException(curl_error($ch));
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
                //throw new ConverterNotOperationalException('The key is invalid. Blacklisted it!');
                throw new ConverterNotOperationalException('The key is invalid');
            }

            throw new ConverterNotOperationalException(
                'ewww api did not return an image. It could be that the key is invalid. Response: '
                . $response
            );
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            throw new ConverterNotOperationalException('ewww api did not return anything');
        }

        $success = file_put_contents($destination, $response);

        if (!$success) {
            throw new ConverterFailedException('Error saving file');
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
        $ch = ConverterHelper::initCurlForConverter();

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
        $ch = ConverterHelper::initCurlForConverter();

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

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

use WebPConvert\Converters\ConverterHelper;

class Gd
{
    public static $extraOptions = [
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('gd', $source, $destination, $options, true);
    }

    /**
     *
     *  @return Returns TRUE if the convertion was complete, or if the source image already is a true color image,
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
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('gd')) {
            throw new ConverterNotOperationalException('Required Gd extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new ConverterNotOperationalException(
                'Required imagewebp() function is not available. It seems Gd has been compiled without webp support.'
            );
        }

        switch (ConverterHelper::getExtension($source)) {
            case 'png':
                if (!$options['skip-pngs']) {
                    if (!function_exists('imagecreatefrompng')) {
                        throw new ConverterNotOperationalException(
                            'Required imagecreatefrompng() function is not available.'
                        );
                    }
                    $image = @imagecreatefrompng($source);
                    if (!$image) {
                        throw new ConverterFailedException(
                            'imagecreatefrompng("' . $source . '") failed'
                        );
                    }
                } else {
                    throw new ConversionDeclinedException(
                        'PNG file skipped. GD is configured not to convert PNGs'
                    );
                }
                break;
            default:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new ConverterNotOperationalException(
                        'Required imagecreatefromjpeg() function is not available.'
                    );
                }
                $image = @imagecreatefromjpeg($source);
                if (!$image) {
                    throw new ConverterFailedException('imagecreatefromjpeg("' . $source . '") failed');
                }
        }

        $mustMakeTrueColor = false;
        if (function_exists('imageistruecolor')) {
            if (imageistruecolor($image)) {
                $logger->logLn('image is true color');
            } else {
                $logger->logLn('image is not true color');
                $mustMakeTrueColor = true;
            }
        } else {
            $logger->logLn('It can not be determined if image is true color');
            $mustMakeTrueColor = true;
        }
        if ($mustMakeTrueColor) {
            $logger->logLn('converting color palette to true color');
            $success = self::makeTrueColor($image);
            if (!$success) {
                $logger->logLn(
                    'Warning: FAILED converting color palette to true color. Continuing, but this does not look good.'
                );
            }
        }
        if (ConverterHelper::getExtension($source) == 'png') {
            if (function_exists('imagealphablending')) {
                if (!imagealphablending($image, true)) {
                    $logger->logLn('Warning: imagealphablending() failed');
                }
            } else {
                $logger->logLn(
                    'Warning: imagealphablending() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
            if (function_exists('imagesavealpha')) {
                if (!imagesavealpha($image, true)) {
                    $logger->logLn('Warning: imagesavealpha() failed');
                }
            } else {
                $logger->logLn(
                    'Warning: imagesavealpha() is not available on your system. ' .
                    'Converting PNGs with transparency might fail on some systems'
                );
            }
        }

        $success = @imagewebp($image, $destination, $options['_calculated_quality']);

        if (!$success) {
            throw new ConverterFailedException(
                'Call to imagewebp() failed. Probably failed writing file. Check file permissions!'
            );
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */
        if (@filesize($destination) % 2 == 1) {
            @file_put_contents($destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Gmagick
{
    public static $extraOptions = [];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('gmagick', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('Gmagick')) {
            throw new ConverterNotOperationalException('Required Gmagick extension is not available.');
        }

        if (!class_exists('Gmagick')) {
            throw new ConverterNotOperationalException(
                'Gmagick is installed, but not correctly. The class Gmagick is not available'
            );
        }

        // This might throw an exception.
        // We let it...
        $im = new \Gmagick($source);


        // Throws an exception if Gmagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryformats())) {
            throw new ConverterNotOperationalException('Gmagick was compiled without WebP support.');
        }

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
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
        $im->setcompressionquality($options['_calculated_quality']);

        //$success = $im->writeimagefile(fopen($destination, 'wb'));
        $success = @file_put_contents($destination, $im->getImageBlob());

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        } else {
            //$logger->logLn('sooms we made it!');
        }
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Imagick
{
    public static $extraOptions = [];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('imagick', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('imagick')) {
            throw new ConverterNotOperationalException('Required iMagick extension is not available.');
        }

        if (!class_exists('Imagick')) {
            throw new ConverterNotOperationalException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        // This might throw an exception.
        // Ie "ImagickException: no decode delegate for this image format `JPEG'"
        // We let it...
        $im = new \Imagick($source);
        //$im = new \Imagick();
        //$im->readImage($source);

        // Throws an exception if iMagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryFormats())) {
            throw new ConverterNotOperationalException('iMagick was compiled without WebP support.');
        }

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

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

        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // but luckily imagick is a big boy, and automatically converts with same quality as
            // source, when the quality isn't set.
            // So we simply do not set quality.
            // This actually kills the max-height functionality. But I deem that this is more important
            // because setting image quality to something higher than source generates bigger files,
            // but gets you no extra quality. When failing to limit quality, you at least get something
            // out of it
            $logger->logLn('Converting without setting quality, to achieve auto quality');
        } else {
            // _calculated_quality is always set, actually - also when quality is set to a number
            if (isset($options['_calculated_quality'])) {
                $logger->logLn('Converting with quality:' . $options['_calculated_quality']);
                $im->setImageCompressionQuality($options['_calculated_quality']);
                //$im->setImageCompressionQuality(55);
            }
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

        $success = @file_put_contents($destination, $im->getImageBlob());

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        }
    }
}

?><?php

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

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Wpc
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

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('wpc', $source, $destination, $options, true);
    }

    // Took this parser from Drupal
    private static function parseSize($size)
    {

        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
            $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

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
    public static function doConvert($source, $destination, $options, $logger)
    {

        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        $apiVersion = $options['api-version'];

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException(
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
                    throw new ConverterNotOperationalException('No remote host has been set up');
                }*/
        }

        if ($options['url'] == '') {
            throw new ConverterNotOperationalException(
                'Missing URL. You must install Webp Convert Cloud Service on a server, ' .
                'or the WebP Express plugin for Wordpress - and supply the url.'
            );
        }

        $fileSize = @filesize($source);
        if ($fileSize !== false) {
            $uploadMaxSize = self::parseSize(ini_get('upload_max_filesize'));
            if (($uploadMaxSize !== false) && ($uploadMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your max upload (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'upload_max_filesize in php.ini: ' . ini_get('upload_max_filesize') .
                        ' (parsed as ' . round($uploadMaxSize/1024) . ' kb)'
                );
            }

            $postMaxSize = self::parseSize(ini_get('post_max_size'));
            if (($postMaxSize !== false) && ($postMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your post_max_size limit (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'post_max_size in php.ini: ' . ini_get('post_max_size') .
                        ' (parsed as ' . round($postMaxSize/1024) . ' kb)'
                );
            }

            // ini_get('memory_limit')
        }

        // Got some code here:
        // https://coderwall.com/p/v4ps1a/send-a-file-via-post-with-curl-and-php

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }

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
            'file' => curl_file_create($source),
            'options' => json_encode($optionsToSend),
            'servername' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
        ];

        if ($apiVersion == 0) {
            $postData['hash'] = md5(md5_file($source) . $options['secret']);
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
            throw new ConverterFailedException(
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
                            throw new ConverterFailedException(
                                'There are problems with the server setup: "' .
                                $responseObj['errorMessage'] . '"'
                            );
                        case 1:
                            throw new ConverterFailedException(
                                'Access denied. ' . $responseObj['errorMessage']
                            );
                        default:
                            throw new ConverterFailedException(
                                'Conversion failed: "' . $responseObj['errorMessage'] . '"'
                            );
                    }
                }
            }

            // WPC 0.1 returns 'failed![error messag]' when conversion fails. Handle that.
            if (substr($response, 0, 7) == 'failed!') {
                throw new ConverterFailedException(
                    'WPC failed converting image: "' . substr($response, 7) . '"'
                );
            }

            if (empty($response)) {
                $errorMsg = 'Error: Unexpected result. We got nothing back. HTTP CODE: ' . $httpCode;
                throw new ConverterFailedException($errorMsg);
            } else {
                $errorMsg = 'Error: Unexpected result. We did not receive an image. We received: "';
                $errorMsg .= str_replace("\r", '', str_replace("\n", '', htmlentities(substr($response, 0, 400))));
                throw new ConverterFailedException($errorMsg . '..."');
            }
            //throw new ConverterNotOperationalException($response);
        }

        $success = @file_put_contents($destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConverterFailedException('Error saving file. Check file permissions');
        }
        /*
                $curlOptions = [
                    'api_key' => $options['key'],
                    'webp' => '1',
                    'file' => curl_file_create($source),
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

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter does not exist.';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFileException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination file. Check file permisions!';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFolderException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination folder. Check file permisions!';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class InvalidFileExtensionException extends WebPConvertBaseException
{
    public $description = 'The converter does not accept the file extension';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class TargetNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter could not locate source file';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConversionDeclinedException extends WebPConvertBaseException
{
    public $description = 'The converter declined converting';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterFailedException extends WebPConvertBaseException
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotOperationalException extends WebPConvertBaseException
{
    public $description = 'The converter is not operational';
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
use WebPConvert\Converters\ConverterHelper;
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

        // (psst: the is_callable check is needed in order to work with WebPConvert v1.0)
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
            $success = WebPConvert::convert($source, $destination, $options, $echoLogger);
        } catch (\Exception $e) {
            $success = false;

            $msg = $e->getMessage();

            echo '<b>' . $msg . '</b>';
            exit;
        }

        if ($success) {
            //echo 'ok';
        } else {
            echo '<b>Conversion failed. None of the tried converters are operational</b>';
        }
        ?>
    </body>
    </html>
        <?php
    }
}

?><?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;

class ServeBase
{
    public $source;
    public $destination;
    public $options;

    // These two fellows are first set when decideWhatToServe is called
    // However, if it is decided to serve a fresh conversion, they might get modified.
    // If that for example results in a file larger than source, $whatToServe will change
    // from 'fresh-conversion' to 'original', and $whyServingThis will change to 'source-lighter'
    public $whatToServe = '';
    public $whyServingThis = '';

    public function __construct($source, $destination, $options)
    {

        $this->source = $source;
        $this->destination = $destination;
        $this->options = array_merge(self::$defaultOptions, $options);

        $this->setErrorReporting();
    }

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
    ];

    protected function setErrorReporting()
    {
        if (($this->options['error-reporting'] === true) ||
            (($this->options['error-reporting'] === 'auto') && ($this->options['show-report'] === true))
        ) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } elseif (($this->options['error-reporting'] === false) ||
            (($this->options['error-reporting'] === 'auto') && ($this->options['show-report'] === false))
        ) {
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
    }

    protected function header($header, $replace = true)
    {
        header($header, $replace);
    }

    public function addXStatusHeader($text)
    {
        if ($this->options['add-x-header-status']) {
            $this->header('X-WebP-Convert-Status: ' . $text, true);
        }
    }

    public function addVaryHeader()
    {
        if ($this->options['add-vary-header']) {
            $this->header('Vary: Accept');
        }
    }

    public function addContentTypeHeader($cType)
    {
        if ($this->options['add-content-type-header']) {
            $this->header('Content-type: ' . $cType);
        }
    }

    /* $timestamp  Unix timestamp */
    public function addLastModifiedHeader($timestamp)
    {
        if ($this->options['add-last-modified-header']) {
            $this->header("Last-Modified: " . gmdate("D, d M Y H:i:s", $timestamp) ." GMT", true);
        }
    }

    public function addCacheControlHeader()
    {
        if (!empty($this->options['cache-control-header'])) {
            $this->header('Cache-Control: ' . $this->options['cache-control-header'], true);
        }
    }

    public function serveExisting()
    {
        if (!$this->callAboutToServeImageCallBack('destination')) {
            return;
        }

        $this->addXStatusHeader('Serving existing converted image');
        $this->addVaryHeader();
        $this->addContentTypeHeader('image/webp');
        $this->addCacheControlHeader();
        $this->addLastModifiedHeader(@filemtime($this->destination));

        if (@readfile($this->destination) === false) {
            $this->header('X-WebP-Convert-Error: Could not read file');
            return false;
        }
        return true;
    }

    /**
     *   Called immidiately before serving image (either original, already converted or fresh)
     *   $whatToServe can be 'source' | 'destination' | 'fresh-conversion'
     *   $whyServingThis can be:
     *   for 'source':
     *       - "explicitly-told-to"     (when the "original" option is set)
     *       - "source-lighter"         (when original image is actually smaller than the converted)
     *   for 'fresh-conversion':
     *       - "explicitly-told-to"     (when the "reconvert" option is set)
     *       - "source-modified"        (when source is newer than existing)
     *       - "no-existing"            (when there is no existing at the destination)
     *   for 'destination':
     *       - "no-reason-not-to"       (it is lighter than source, its not older,
     *                                   and we were not told to do otherwise)
     */
    protected function callAboutToServeImageCallBack($whatToServe)
    {
        if (!isset($this->options['aboutToServeImageCallBack'])) {
            return true;
        }
        $result = call_user_func(
            $this->options['aboutToServeImageCallBack'],
            $whatToServe,
            $this->whyServingThis,
            $this
        );
        return ($result !== false);
    }

    /**
     *  Decides what to serve.
     *  Returns array. First item is what to do, second is additional info.
     *  First item can be one of these:
     *  - "destination"  (serve existing converted image at the destination path)
     *       - "no-reason-not-to"
     *  - "source"
     *       - "explicitly-told-to"
     *       - "source-lighter"
     *  - "fresh-conversion" (note: this may still fail)
     *       - "explicitly-told-to"
     *       - "source-modified"
     *       - "no-existing"
     *  - "fail"
     *        - "Missing destination argument"
     *  - "critical-fail"   (a failure where the source file cannot be served)
     *        - "Missing source argument"
     *        - "Source file was not found!"
     *  - "report"
     */
    public function decideWhatToServe()
    {
        $decisionArr = $this->doDecideWhatToServe();
        $this->whatToServe = $decisionArr[0];
        $this->whyServingThis = $decisionArr[1];
    }

    private function doDecideWhatToServe()
    {
        if (empty($this->source)) {
            return ['critical-fail', 'Missing source argument'];
        }
        if (@!file_exists($this->source)) {
            return ['critical-fail', 'Source file was not found!'];
        }
        if (empty($this->destination)) {
            return ['fail', 'Missing destination argument'];
        }
        if ($this->options['show-report']) {
            return ['report', ''];
        }
        if ($this->options['serve-original']) {
            return ['source', 'explicitly-told-to'];
        }
        if ($this->options['reconvert']) {
            return ['fresh-conversion', 'explicitly-told-to'];
        }

        if (@file_exists($this->destination)) {
            // Reconvert if source file is newer than destination
            $timestampSource = @filemtime($this->source);
            $timestampDestination = @filemtime($this->destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                return ['fresh-conversion', 'source-modified'];
            }

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($this->destination);
            $filesizeSource = @filesize($this->source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                return ['source', 'source-lighter'];
            }

            // Destination exists, and there is no reason left not to serve it
            return ['destination', 'no-reason-not-to'];
        } else {
            return ['fresh-conversion', 'no-existing'];
        }
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\BufferLogger;
use WebPConvert\Converters\ConverterHelper;
use WebPConvert\Serve\Report;

/**
 * This class must serves a converted image (either a fresh convertion, the destionation, or
 * the original). Upon failure, the fail action given in the options will be exectuted
 */
class ServeConverted extends ServeBase
{

    private function addXOptionsHeader()
    {
        if ($this->options['add-x-header-options']) {
            $this->header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($this->options));
        }
    }

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
        $success = false;
        $bufferLogger = new BufferLogger();

        try {
            $success = WebPConvert::convert($this->source, $this->destination, $this->options, $bufferLogger);

            if ($success) {
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
            } else {
                $description = 'No converters are operational';
                $msg = '';
            }
        } catch (\WebPConvert\Exceptions\InvalidFileExtensionException $e) {
            $criticalFail = true;
            $description = 'Invalid file extension';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Source file not found';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'No converters could convert the image';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'No converters could/wanted to convert the image';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\ConverterNotFoundException $e) {
            $description = 'A converter was not found!';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFileException $e) {
            $description = 'Cannot create destination file';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFolderException $e) {
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

        // TODO: handle if this fails...
        $image = imagecreatetruecolor(620, 200);
        imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
        // echo imagewebp($image);
        echo imagegif($image);
        imagedestroy($image);
    }

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

    protected function criticalFail($title, $description)
    {
        return $this->fail($title, $description, true);
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
namespace WebPConvert\Serve;

use WebPConvert\Serve\ServeBase;
use WebPConvert\Serve\ServeConverted;

/**
 * This class must determine if an existing converted image can and should be served.
 * If so, it must serve it.
 * If not, it must hand the task over to ConvertAndServe
 *
 * The reason for doing it like this is that we want existing images to be served as fast as
 * possible, because that is the thing that will happen most of the time.
 *
 * Anything else, such as error handling and creating new conversion is handed off
 * (and only autoloaded when needed)
 */

class ServeExistingOrHandOver extends ServeBase
{

    /**
     * Main method
     */
    public static function serveConverted($source, $destination, $options)
    {
        $server = new ServeExistingOrHandOver($source, $destination, $options);

        $server->decideWhatToServe();
        if ($server->whatToServe == 'destination') {
            return $server->serveExisting();
        } else {
            // Load extra php classes, if told to
            if (isset($options['require-for-conversion'])) {
                require($options['require-for-conversion']);
            }
            ServeConverted::serveConverted($source, $destination, $options);
        }
    }
}

