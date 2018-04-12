<?php

namespace WebPConvert;

//use WebPConvert\Converters\Cwebp;
use WebPConvert\Exceptions\TargetNotFoundException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\CreateDestinationFileException;

class WebPConvert
{
    private static $preferredConverters = [];
    private static $excludeConverters = false;
    private static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    private static $converterOptions = array();

    public static function setConverterOption($converter, $optionName, $optionValue)
    {
        if (!isset($converterOptions['converter'])) {
            $converterOptions['converter'] = array();
        }
        $converterOptions[$converter][$optionName] = $optionValue;
    }

    /* As there are many options available for imagick, it will be convenient to be able to set them in one go.
       So we will probably create a new public method setConverterOption($converter, $options)
       Example:

       setConverterOptions('imagick', array(
           'webp:low-memory' => 'true',
           'webp:method' => '6',
           'webp:lossless' => 'true',
       ));
       */


    // Defines the array of preferred converters
    public static function setConverterOrder($array, $exclude = false)
    {
        self::$preferredConverters = $array;

        if ($exclude) {
            self::$excludeConverters = true;
        }
    }

    // Throws an exception if the provided file doesn't exist
    private static function isValidTarget($filePath)
    {
        if (!file_exists($filePath)) {
            throw new TargetNotFoundException('File or directory not found: ' . $filePath);
        }

        return true;
    }

    // Throws an exception if the provided file's extension is invalid
    private static function isAllowedExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new InvalidFileExtensionException('Unsupported file extension: ' . $fileExtension);
        }

        return true;
    }

    // Creates folder in provided path & sets correct permissions
    private static function createWritableFolder($filePath)
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

    private static function getConverters()
    {
        // Prepare building up an array of converters
        $converters = [];

        // Saves all available converters inside the `Converters` directory to an array
        $availableConverters = array_map(function ($filePath) {
            $fileName = basename($filePath, '.php');
            return strtolower($fileName);
        }, glob(__DIR__ . '/Converters/*.php'));

        // Order the available converters so imagick comes first, then cwebp, then gd
        $availableConverters = array_unique(
            array_merge(
                array('imagick', 'cwebp', 'gd'),
                $availableConverters
            )
        );

        // Checks if preferred converters match available converters and adds all matches to $converters array
        foreach (self::$preferredConverters as $preferredConverter) {
            if (in_array($preferredConverter, $availableConverters)) {
                $converters[] = $preferredConverter;
            }
        }

        if (self::$excludeConverters) {
            return $converters;
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
        $success = false;

        self::isValidTarget($source);
        self::isAllowedExtension($source);
        self::createWritableFolder($destination);

        $firstFailExecption = null;

        foreach (self::getConverters() as $converter) {
            $converter = ucfirst($converter);
            $className = 'WebPConvert\\Converters\\' . $converter;

            if (!is_callable([$className, 'convert'])) {
                continue;
            }

            try {
                $options = (isset($converterOptions[$converter]) ? $converterOptions[$converter] : array());
                $conversion = call_user_func(
                    [$className, 'convert'],
                    $source,
                    $destination,
                    $quality,
                    $stripMetadata,
                    $options
                );

                if (file_exists($destination)) {
                    $success = true;
                    break;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
                // The converter is not operational.
                // Well, well, we will just have to try the next, then
            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                // Converter failed in an anticipated fashion.
                // If no converter is able to do a conversion, we will rethrow the exception.
                if (!$firstFailExecption) {
                    $firstFailExecption = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                if (!$firstFailExecption) {
                    $firstFailExecption = $e;
                }
            } catch (\Exception $e) {
                // Converter failed in an unanticipated fashion.
                // They should not do that. Rethrow the error!
                throw $e;
            }

            // As success will break the loop, being here means that no converters could
            // do the conversion.
            // If no converters are operational, simply return false
            // Otherwise rethrow the exception that was thrown first (the most prioritized converter)
            if ($firstFailExecption) {
                throw $e;
            }

            $success = false;
        }

        return $success;
    }
}
