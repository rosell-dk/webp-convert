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
            'name' => 'autofilter',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => false,     // the -af option is very slow, and does not have much effect
            'required' => false
        ],
        [
            'name' => 'size-in-percentage',
            'type' => 'number',
            'sensitive' => false,
            'default' => null,
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
        // Escaping whitespace
        $string = preg_replace('/\s/', '\\ ', $string);

        // filter_var() is should normally be available, but it is not always
        // - https://stackoverflow.com/questions/11735538/call-to-undefined-function-filter-var
        if (function_exists('filter_var')) {
            // Sanitize quotes
            $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

            // Stripping control characters
            // see https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
            $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        }

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

        //$logger->logLn('Trying to execute binary:' . $binary);
        //$logger->logLn();
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

        // Autofilter
        if ($options['autofilter']) {
            $commandOptionsArray[] = '-af';
        }

        //

        // Source file
        $commandOptionsArray[] = self::escapeFilename($source);

        // Output
        $commandOptionsArray[] = '-o ' . self::escapeFilename($destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $commandOptionsArray[] = '2>&1';


        $useNice = (($options['use-nice']) && self::hasNiceSupport()) ? true : false;

        $commandOptions = implode(' ', $commandOptionsArray);


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
                    $failureCodesBesides127 = array_diff($failureCodes, [127]);

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

                $binaryFile = __DIR__ . '/Binaries/' . $file;

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
                                switch ($returnCode) {
                                    case 0:
                                        $success = true;
                                        ;
                                        break;
                                    case 126:
                                        $errorMsg .= ': Permission denied. The user that the command was run with (' .
                                            shell_exec('whoami') . ') does not have permission to execute that binary.';
                                        break;
                                    case 127:
                                        $errorMsg .= '. The binary was not found! It ought to be here: ' . $binaryFile;
                                        break;
                                    default:
                                        $errorMsg .= ' (exit code:' .  $returnCode . ').';
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
            $fileStatistics = stat($destinationParent);

            // Apply same permissions as parent folder but strip off the executable bits
            $permissions = $fileStatistics['mode'] & 0000666;
            chmod($destination, $permissions);
        }

        if (!$success) {
            throw new ConverterNotOperationalException($errorMsg);
        }
    }
}
