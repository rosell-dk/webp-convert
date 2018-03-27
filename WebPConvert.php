<?php

namespace WebPConvert;

use WebPConvert\Converters\Cwebp;

class WebPConvert
{
    private static $preferredConverters = array();
    private static $allowedExtensions = array('jpg', 'jpeg', 'png');

    // Defines the array of preferred converters
    public static function setPreferredConverters($preferredConvertersArray)
    {
        self::$preferredConverters = $preferredConvertersArray;
    }

    // Throws an exception if the provided file doesn't exist
    protected static function isValidTarget($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File or directory not found: ' . $filePath);
        }
        return true;
    }

    // Throws an exception if the provided file's extension is invalid
    protected static function isAllowedExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new \Exception('Unsupported file extension: ' . $fileExtension);
        }
        return true;
    }

    // Returns the provided file's folder name
    protected static function getFilePath($filePath)
    {
        return pathinfo($filePath, PATHINFO_DIRNAME);
    }

    // Creates the provided folder & sets correct permissions
    public static function createFolder($path)
    {
        // TODO: what if this is outside open basedir?
        // see http://php.net/manual/en/ini.core.php#ini.open-basedir

        // First, we have to figure out which permissions to set.
        // We want same permissions as parent folder
        // But which parent? - the parent to the first missing folder

        $parentFolders = explode('/', $path);
        $poppedFolders = array();

        while (!(file_exists(implode('/', $parentFolders)))) {
            array_unshift($poppedFolders, array_pop($parentFolders));
        }

        // Retrieving permissions of closest existing folder
        $closestExistingFolder = implode('/', $parentFolders);
        $permissions = fileperms($closestExistingFolder) & 000777;

        // Trying to create the given folder
        if (!mkdir($path, $permissions, true)) {
             throw new \Exception('Failed creating folder: ' . $path);
        }

        // `mkdir` doesn't respect permissions, so we have to `chmod` each created subfolder
        foreach ($poppedFolders as $subfolder) {
            $closestExistingFolder .= '/' . $subfolder;
            // Setting directory permissions
            chmod($path, $permissions);
        }
    }

    protected static function deleteFile($file)
    {
        if (!unlink($file)) {
            throw new \Exception('File already exists and cannot be removed: ' . $file);
        }
        return true;
    }

    protected static function getConverters()
    {
        // Prepare building up an array of converters
        $converters = array();

        // Saves all available converters inside the `Converters` directory to an array
        $availableConverters = array_map(function ($filePath) {
            $fileName = basename($filePath, '.php');
            return strtolower($fileName);
        }, glob(__DIR__ . '/Converters/*.php'));

        // Checks if preferred converters match available converters and adds all matches to $converters array
        foreach (self::$preferredConverters as $preferredConverter) {
            if (in_array($preferredConverter, $availableConverters)) {
                $converters[] = $preferredConverter;
            }
        }

        // Fills $converters array with the remaining available converters, keeping the updated order of execution
        foreach ($availableConverters as $availableConverter) {
            if (in_array($availableConverter, $converters)) {
                continue;
            }
            $converters[] = $availableConverter;
        }

        return $converters;
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (int) $quality (optional): Quality of converted file (0-100)
      @param (bool) $stripMetadata (optional): Whether or not to strip metadata. Default is to strip. Not all converters supports this
    */

    public static function convert($source, $destination, $quality = 85, $stripMetadata = true)
    {
        try {
            self::isValidTarget($source);
            self::isAllowedExtension($source);

            // Prepares destination folder
            $destinationFolder = self::getFilePath($destination);

            // Checks if the provided destination folder exists
            if (!file_exists($destinationFolder)) {
                // If not, attempt to create it
                self::createFolder($destinationFolder);
            }

            // Checks file & folder writing permissions if provided $destination doesn't exist
            if (file_exists($destination)) {
                if (!is_writable($destination)) {
                     throw new \Exception('Cannot overwrite ' . basename($destination) . ' - check file permissions.');
                }
            } else {
                if (!is_writable($destinationFolder)) {
                     throw new \Exception('Cannot write ' . basename($destination) . ' - check folder permissions.');
                }
            }

            // Checks if there's already a converted file at $destination
            if (file_exists($destination)) {
                // If so, attempt to remove it
                self::deleteFile($destination);
            }

            foreach (self::getConverters() as $converter) {
                $converter = ucfirst($converter);
                $className = 'WebPConvert\\Converters\\' . $converter;

                if (!is_callable(array($className, 'convert'))) {
                    continue;
                }

                $conversion = call_user_func(
                    array($className, 'convert'),
                    $source,
                    $destination,
                    $quality,
                    $stripMetadata
                );

                if ($conversion) {
                    $success = true;
                    echo 'Used converter: ' . $converter;
                    break;
                }

                $success = false;
            }

            return $success;
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}
