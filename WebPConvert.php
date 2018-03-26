<?php

namespace WebPConvert;

use WebPConvert\Converters\Cwebp;

class WebPConvert
{
    private static $preferred_converters = array();
    private static $allowedExtensions = array('jpg', 'jpeg', 'png');
    public static $current_conversion_vars;

    // Defines the array of preferred converters
    public static function setPreferredConverters($preferred_converters)
    {
        self::$preferred_converters = $preferred_converters;
    }

    // Throws an exception if the provided file doesn't exist
    protected static function isValidTarget($path)
    {
        if (!file_exists($path)) {
            throw new \Exception('File or directory not found: ' . $path);
        }

        return true;
    }

    // Throws an exception if the provided file's extension is invalid
    protected static function isAllowedExtension($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), self::$allowedExtensions)) {
            throw new \Exception('Unsupported file extension: ' . $ext);
        }

        return true;
    }

    // Returns the provided file's folder name
    protected static function stripFilenameFromPath($path)
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (int) $quality (optional): Quality of converted file (0-100)
      @param (bool) $strip_metadata (optional): Whether or not to strip metadata. Default is to strip. Not all converters supports this
    */

    public static function convert($source, $destination, $quality = 85, $strip_metadata = true)
    {
        self::$current_conversion_vars = array();
        self::$current_conversion_vars['source'] =  $source;
        self::$current_conversion_vars['destination'] =  $destination;

        // Checks if source file exists and if its extension is valid
        try {
            self::isValidTarget($source);
            self::isAllowedExtension($source);
        } catch(\Exception $e) {
            echo $e->getMessage();
        }

        // Prepare destination folder
        $destination_folder = self::stripFilenameFromPath($destination);

        if (!self::isValidTarget($destination_folder)) {
            // self::logMessage('We need to create destination folder');

            // Find out which permissions to set.
            // We want same permissions as parent folder
            // But which parent? - the parent to the first missing folder
            // (TODO: what to do if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir)
            $parent_folders = explode('/', $destination_folder);
            $popped_folders = array();
            while (!(file_exists(implode('/', $parent_folders)))) {
                array_unshift($popped_folders, array_pop($parent_folders));
            }
            $closest_existing_folder = implode('/', $parent_folders);

            // self::logMessage('Using permissions of closest existing folder (' . $closest_existing_folder . ')');
            $permissions = fileperms($closest_existing_folder) & 000777;
            // self::logMessage('Permissions are: 0' . decoct($permissions));

            // if (!mkdir($destination_folder, $permissions, true)) {
            //     self::normalError('Failed creating folder:' . $folder);
            //     return;
            // };
            // self::logMessage('Folder created successfully');

            // alas, mkdir does not respect $permissions. We have to chmod each created subfolder
            $path = $closest_existing_folder;
            foreach ($popped_folders as $subfolder) {
                $path .= '/' . $subfolder;
                // self::logMessage('chmod 0' . decoct($permissions) . ' ' . $path);
                chmod($path, $permissions);
            }
        }

        // Test if it will be possible to write file
        // if (file_exists($destination)) {
        //     if (!is_writable($destination)) {
        //         self::normalError('Cannot overwrite file: ' . $destination . '. Check the file permissions.');
        //         return;
        //     }
        // } else {
        //     if (!is_writable($destination_folder)) {
        //         self::normalError('Cannot write file: ' . $destination . '. Check the folder permissions.');
        //         return;
        //     }
        // }

        // If there is already a converted file at destination, remove it
        // (actually it seems the current converters can handle that, but maybe not future converters)
        // if (file_exists($destination)) {
        //     if (unlink($destination)) {
        //         self::logMessage('Destination file already exists... - removed');
        //     } else {
        //         self::logMessage('Destination file already exists. Could not remove it');
        //     }
        // }

        // Prepare building up an array of converters
        $converters = array();

        // Add preferred converters
        // if (count(self::$preferred_converters) > 0) {
        //     self::logMessage('Preferred converters was set to: ' . implode(', ', self::$preferred_converters));
        // } else {
        //     self::logMessage('No preferred converters was set. Converters will be tried in default order');
        // }

        foreach (self::$preferred_converters as $converter) {
            $filename = __DIR__ . '/Converters/' . $converter . '.php';
            if (file_exists($filename)) {
                $converters[] = $converter;
            } else {
                // self::logMessage('<b>the converter "' . $converter . '" that was set as a preferred converter was not found at: "' . $filename . '".</b>');
            }
        }

        // Save converters in the `Converters` directory to array ..
        $files = array_map(function ($path) {
            $fileName = basename($path, '.php');
            return strtolower($fileName);
        }, glob(__DIR__ . '/Converters/*.php'));

        // .. and merge it with the $converters array, keeping the updated order of execution
        foreach ($files as $file) {
            if (in_array($file, $converters)) {
                continue;
            }
            $converters[] = $file;
        }

        // self::logMessage('Order of converters to be tried: <i>' . implode('</i>, <i>', $converters) . '</i>');

        foreach ($converters as $converter) {
            $converter = ucfirst($converter);
            $className = 'WebPConvert\\Converters\\' . $converter;

            if (!is_callable(array($className, 'convert'))) {
                continue;
            }

            call_user_func(array($className, 'convert'), $source, $destination, $quality, $strip_metadata);
        }
    }
}
