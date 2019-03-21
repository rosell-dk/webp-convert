<?php

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
