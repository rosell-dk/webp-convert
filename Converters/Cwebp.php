<?php

namespace WebPConvert\Converters;

class Cwebp
{
    protected static $cwebpDefaultPaths = array( // System paths to look for cwebp binary
        '/usr/bin/cwebp',
        '/usr/local/bin/cwebp',
        '/usr/gnu/bin/cwebp',
        '/usr/syno/bin/cwebp'
    );

    protected static $binaryInfo = array(  // OS-specific binaries included in this library
        'WinNT' => array( 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'),
        'Darwin' => array( 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'),
        'SunOS' => array( 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'),
        'FreeBSD' => array( 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'),
        'Linux' => array( 'cwebp-linux', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568')
    )[PHP_OS];

    protected static function updateBinaries($file, $hash, $array)
    {
        $binaryFile = __DIR__ . '/Binaries/' . $file;
        $binaryHash = hash_file('sha256', $binaryFile);

        // Throws an exception if binary file does not exist
        if (!file_exists($binaryFile)) {
            throw new \Exception('Operating system is currently not supported: ' . PHP_OS);
        }

        // Throws an exception if binary file checksum & deposited checksum do not match
        if ($binaryHash != $hash) {
            throw new \Exception('Binary checksum is invalid.');
        }

        array_unshift($array, $binaryFile);

        return $array;
    }

    protected static function escapeFilename($string)
    {
        // Escaping whitespaces & quotes
        $string = preg_replace('/\s/', '\\ ', $string);
        $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

        // Stripping control characters
        // see https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);

        return $string;
    }

    protected static function cloneFolderPermissionsToFile($folder, $file)
    {
        $fileStatistics = stat($folder);

        // Same permissions as parent folder plus stripping off the executable bits
        $permissions = $fileStatistics['mode'] & 0000666;
        chmod($file, $permissions);
    }

    // Checks if 'Nice' is available
    protected static function hasNiceSupport()
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

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            if (!function_exists('exec')) {
                throw new \Exception('exec() is not enabled.');
            }

            // Checks if provided binary file & its hash match with deposited version & updates cwebp binary array
            $binaries = self::updateBinaries(
                self::$binaryInfo[0],
                self::$binaryInfo[1],
                self::$cwebpDefaultPaths
            );
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
        }

        /*
         * Preparing options
         */

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $metadata = (
            $stripMetadata
            ? '-metadata none'
            : '-metadata all'
        );

        // Image quality
        $quality = '-q ' . $quality;

        // Losless PNG conversion
        $fileExtension = pathinfo($source, PATHINFO_EXTENSION);
        $losless = (
            strtolower($fileExtension) == 'png'
            ? '-lossless'
            : ''
        );

        // Built-in method option
        $method = (
            defined('WEBPCONVERT_CWEBP_METHOD')
            ? ' -m ' . WEBPCONVERT_CWEBP_METHOD
            : ' -m 6'
        );

        // Built-in low memory option
        if (!defined('WEBPCONVERT_CWEBP_LOW_MEMORY')) {
            $lowMemory= '-low_memory';
        } else {
            $lowMemory = (
                WEBPCONVERT_CWEBP_LOW_MEMORY
                ? '-low_memory'
                : ''
            );
        }

        $optionsArray = array(
            $metadata = $metadata,
            $quality = $quality,
            $losless = $losless,
            $method = $method,
            $lowMemory = $lowMemory,
            $input = self::escapeFilename($source),
            $output = '-o ' . self::escapeFilename($destination),
            $stderrRedirect = '2>&1'
        );

        $options = implode(' ', $optionsArray);
        $nice = (self::hasNiceSupport()
            ? 'nice '
            : ''
        );

        // Try all paths
        foreach ($binaries as $index => $binary) {
            $command = $nice . $binary . ' ' . $options;
            exec($command, $output, $returnCode);

            if ($returnCode == 0) { // Everything okay!
                // cwebp sets file permissions to 664 ..
                // .. instead, $destination's parent folder's permissions should be used (except executable bits)
                $destinationParent = dirname($destination);
                self::cloneFolderPermissionsToFile($destinationParent, $destination);

                $success = true;
                break;
            }

            $success = false;
        }

        if (!$success) {
            return false;
        }

        return true;
    }
}
