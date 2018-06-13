<?php

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
    ];

    public static function getSpecialOptions()
    {
        return [
            'use-nice' => 'boolean',
        ];
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
        // Escaping whitespaces & quotes
        $string = preg_replace('/\s/', '\\ ', $string);
        $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

        // Stripping control characters
        // see https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);

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

    public static function convert($source, $destination, $options = [], $prepareDestinationFolder = true)
    {
        if ($prepareDestinationFolder) {
            ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);
        }

        $options = ConverterHelper::mergeOptions($options, self::$extraOptions);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        if (!function_exists('exec')) {
            throw new ConverterNotOperationalException('exec() is not enabled.');
        }


        // Init with common system paths
        $cwebpPathsToTest = self::$cwebpDefaultPaths;

        // Remove paths that doesn't exist
        $cwebpPathsToTest = array_filter($cwebpPathsToTest, function ($binary) {
            return file_exists($binary);
        });

        // Add supplied binary to array (if available for OS, and hash is correct)
        if (isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $info = self::$suppliedBinariesInfo[PHP_OS];

            $file = $info[0];
            $hash = $info[1];

            $binaryFile = __DIR__ . '/Binaries/' . $file;

            // The file should exist, but may have been removed manually. That would be ok, I suppose

            if (file_exists($binaryFile)) {
                // File exists, now generate its hash
                $binaryHash = hash_file('sha256', $binaryFile);

                // Throw an exception if binary file checksum & deposited checksum do not match
                if ($binaryHash != $hash) {
                    throw new ConverterNotOperationalException('Binary checksum is invalid.');
                }
                $cwebpPathsToTest[] = $binaryFile;
            }
        }

        /*
         * Preparing options
         */

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $metadata = '-metadata ' . $options['metadata'];

        // Image quality
        $quality = '-q ' . $options['quality'];

        // Losless PNG conversion
        $lossless = ($options['lossless'] ? '-lossless' : '');

        // Built-in method option
        $method = ' -m ' . strval($options['method']);


        // TODO:
        // Why not use -af ?  (https://developers.google.com/speed/webp/docs/cwebp)
        // Would it be possible get a quality similar to source?
        // It seems so: "identify -format '%Q' yourimage.jpg" (https://stackoverflow.com/questions/2024947/is-it-possible-to-tell-the-quality-level-of-a-jpeg)
        // -- With -jpeg_like option, or perhaps the -size option

        // Built-in low memory option
        $lowMemory = '';
        if ($options['low-memory']) {
            $lowMemory = '-low_memory';
        }

        $commandOptionsArray = [
            $metadata = $metadata,
            $quality = $quality,
            $lossless = $lossless,
            $method = $method,
            $lowMemory = $lowMemory,
            $input = self::escapeFilename($source),
            $output = '-o ' . self::escapeFilename($destination),
            $stderrRedirect = '2>&1'
        ];

        $nice = (($options['use-nice']) && self::hasNiceSupport()) ? 'nice ' : '';

        $commandOptions = implode(' ', $commandOptionsArray);

        // Try all paths
        foreach ($cwebpPathsToTest as $index => $binary) {
            $command = $nice . $binary . ' ' . $commandOptions;

            //throw new ConverterNotOperationalException($command);
            exec($command, $output, $returnCode);

            if ($returnCode == 0) { // Everything okay!
                // cwebp sets file permissions to 664 but instead ..
                // .. $destination's parent folder's permissions should be used (except executable bits)
                $destinationParent = dirname($destination);
                $fileStatistics = stat($destinationParent);

                // Apply same permissions as parent folder but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                chmod($destination, $permissions);

                $success = true;
                break;
            }

            $success = false;
        }

        if (!$success) {
            throw new ConverterNotOperationalException('No working binaries were found');
        }
    }
}
