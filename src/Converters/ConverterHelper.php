<?php

namespace WebPConvert\Converters;

//use WebPConvert\Converters\Cwebp;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Exceptions\CreateDestinationFileException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\TargetNotFoundException;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;

class ConverterHelper
{
    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public static $defaultOptions = [
        'quality' => 85,
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
    public static function runConverter($converterId, $source, $destination, $options = [], $prepareDestinationFolder = true)
    {
        if ($prepareDestinationFolder) {
            ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);
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

        call_user_func(
            [$className, 'doConvert'],
            $source,
            $destination,
            $options,
            $prepareDestinationFolder
        );

        if (!file_exists($destination)) {
            throw new ConverterFailedException('Destination file is not there');
        }
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
