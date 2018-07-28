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
    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public static $defaultOptions = [
        'quality' => 'auto',
        'max-quality' => 85,
        'default-quality' => 80,
        'metadata' => 'none',
        'method' => 6,
        'low-memory' => false,
        'lossless' => false,
        'converters' =>  ['cwebp', 'gd', 'imagick']
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
         prepares destination folder, and runs some standard validations */
    public static function runConverter($converterId, $source, $destination, $options = [], $prepareDestinationFolder = true, $logger = null)
    {
        if ($prepareDestinationFolder) {
            ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);
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

        if (!file_exists($destination)) {
            throw new ConverterFailedException('Destination file is not there');
        }
    }

    /* Try to detect quality of jpeg.
       If not possible, nothing is returned (null). Otherwise quality is returned (int)
        */
    public static function detectQualityOfJpg($filename)
    {
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $img = new Imagick($filename);

            // The required function is available as from PECL imagick v2.2.2
            if (method_exists($img, 'getImageCompressionQuality')) {
              return $img->getImageCompressionQuality();
            }
        }

        if (function_exists('shell_exec')) {

        // Try Imagick
            $quality = shell_exec("identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . $filename);
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
                $logger->logLn('Quality of source could not be established (Imagick or GraphicsMagick is required) - Using default instead (' . $options['default-quality'] . ').');

                // this allows the wpc converter to know
                $options['_quality_could_not_be_detected'] = true;
            } else {
                if ($q > $options['max-quality']) {
                    $logger->log('Quality of source is ' . $q . '. This is higher than max-quality, so using that instead (' . $options['max-quality'] . ')');
                } else {
                    $logger->log('Quality set to same as source: ' . $q);
                }
            }
            $logger->ln();
            $q = min($q, $options['max-quality']);

            $options['_calculated_quality'] = $q;
        //$logger->logLn('Using quality: ' . $options['quality']);
        } else {
            $logger->logLn('Quality: ' . $options['quality'] . '. Consider setting quality to "auto" instead. It is generally a better idea');
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
        if (!file_exists($filePath)) {
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
    public static function createWritableFolder($filePath)
    {
        $folder = pathinfo($filePath, PATHINFO_DIRNAME);
        if (!file_exists($folder)) {
            // TODO: what if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir

            // First, we have to figure out which permissions to set.
            // We want same permissions as parent folder
            // But which parent? - the parent to the first missing folder

            $parentFolders = explode('/', $folder);
            $poppedFolders = [];

            while (!(file_exists(implode('/', $parentFolders))) && count($parentFolders) > 0) {
                array_unshift($poppedFolders, array_pop($parentFolders));
            }

            // Retrieving permissions of closest existing folder
            $closestExistingFolder = implode('/', $parentFolders);
            $permissions = fileperms($closestExistingFolder) & 000777;

            // Trying to create the given folder
            // Notice: mkdir emits a warning on failure. It would be nice to suppress that, if possible
            if (!mkdir($folder, $permissions, true)) {
                throw new CreateDestinationFolderException('Failed creating folder: ' . $folder);
            }


            // `mkdir` doesn't respect permissions, so we have to `chmod` each created subfolder
            foreach ($poppedFolders as $subfolder) {
                $closestExistingFolder .= '/' . $subfolder;
                // Setting directory permissions
                chmod($folder, $permissions);
            }
        }

        // Checks if there's a file in $filePath & if writing permissions are correct
        if (file_exists($filePath) && !is_writable($filePath)) {
            throw new CreateDestinationFileException('Cannot overwrite ' . basename($filePath) . ' - check file permissions.');
        }

        // There's either a rewritable file in $filePath or none at all.
        // If there is, simply attempt to delete it
        if (file_exists($filePath) && !unlink($filePath)) {
            throw new CreateDestinationFileException('Existing file cannot be removed: ' . basename($filePath));
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
            throw new ConverterNotOperationalException('Required curl_file_create() function is not available (requires PHP > 5.5).');
        }

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }
        return $ch;
    }
}
