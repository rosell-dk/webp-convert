<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractExecConverter;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

/**
 * Convert images to webp by calling cwebp binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Cwebp extends AbstractExecConverter
{
    protected $supportsLossless = true;

    protected function getOptionDefinitionsExtra()
    {
        return [
            ['autofilter', 'boolean', false],
            ['command-line-options', 'string', ''],
            ['low-memory', 'boolean', false],
            ['method', 'number', 6],
            ['near-lossless', 'integer', 60],
            ['rel-path-to-precompiled-binaries', 'string', './Binaries'],
            ['size-in-percentage', 'number', null],
            ['try-common-system-paths', 'boolean', true],
            ['try-supplied-binary-for-os', 'boolean', true],
            ['use-nice', 'boolean', false],

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
    // If other binaries are going to be added, notice that the first argument is what PHP_OS returns.
    // (possible values, see here: https://stackoverflow.com/questions/738823/possible-values-for-php-os)
    private static $suppliedBinariesInfo = [
        'WINNT' => [ 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'],
        'Darwin' => [ 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'],
        'SunOS' => [ 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'],
        'FreeBSD' => [ 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'],
        'Linux' => [ 'cwebp-linux', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568']
    ];


    private function executeBinary($binary, $commandOptions, $useNice)
    {
        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions;

        //$logger->logLn('command options:' . $commandOptions);
        //$logger->logLn('Trying to execute binary:' . $binary);
        exec($command, $output, $returnCode);
        //$logger->logLn(self::msgForExitCode($returnCode));
        return intval($returnCode);
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        $options = $this->options;

        $commandOptionsArray = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $commandOptionsArray[] = '-metadata ' . $options['metadata'];

        // Size
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource =  filesize($this->source);
            if ($sizeSource !== false) {
                $targetSize = floor($sizeSource * $options['size-in-percentage'] / 100);
            }
        }
        if (isset($targetSize)) {
            $commandOptionsArray[] = '-size ' . $targetSize;
        } else {
            // Image quality
            $commandOptionsArray[] = '-q ' . $this->getCalculatedQuality();
        }


        $commandOptionsArray[] = ($options['lossless'] ? '-lossless' : '');

        // Losless PNG conversion
        if ($options['lossless'] === true) {
            // No need to add -lossless when near-lossless is used
            if ($options['near-lossless'] === 100) {
                $commandOptionsArray[] = '-lossless';
            }
        }

        // Near-lossles
        if ($options['near-lossless'] !== 100) {
            // We only let near_lossless have effect when lossless is set.
            // otherwise lossless auto would not work as expected
            if ($options['lossless'] === true) {
                $commandOptionsArray[] ='-near_lossless ' . $options['near-lossless'];
            }
        }

        if ($options['autofilter'] === true) {
            $commandOptionsArray[] = '-af';
        }

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
        $commandOptionsArray[] = escapeshellarg($this->source);

        // Output
        $commandOptionsArray[] = '-o ' . escapeshellarg($this->destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $commandOptionsArray[] = '2>&1';

        $commandOptions = implode(' ', $commandOptionsArray);
        $this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    public function checkOperationality()
    {
        $options = $this->options;
        if (!$options['try-supplied-binary-for-os'] && !$options['try-common-system-paths']) {
            throw new ConverterNotOperationalException(
                'Configured to neither look for cweb binaries in common system locations, ' .
                'nor to use one of the supplied precompiled binaries. But these are the only ways ' .
                'this converter can convert images. No conversion can be made!'
            );
        }
    }


    protected function doActualConvert()
    {
        $errorMsg = '';
        $options = $this->options;
        $useNice = (($options['use-nice']) && self::hasNiceSupport());

        $commandOptions = $this->createCommandLineOptions();


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


        $returnCode = 0;
        $majorFailCode = 0;
        if ($options['try-common-system-paths']) {
            foreach ($cwebpPathsToTest as $index => $binary) {
                $returnCode = $this->executeBinary($binary, $commandOptions, $useNice);
                if ($returnCode == 0) {
                    $this->logLn('Successfully executed binary: ' . $binary);
                    $success = true;
                    break;
                } else {
                    $failures[] = [$binary, $returnCode];
                    if (!in_array($returnCode, $failureCodes)) {
                        $failureCodes[] = $returnCode;
                    }
                }
            }

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
                if (file_exists($binaryFile)) {
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
                        $returnCode = $this->executeBinary($binaryFile, $commandOptions, $useNice);
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
        // (or perhaps the current umask instead? https://www.php.net/umask)

        if ($success) {
            $destinationParent = dirname($this->destination);
            $fileStatistics = stat($destinationParent);
            if ($fileStatistics !== false) {
                // Apply same permissions as parent folder but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                chmod($this->destination, $permissions);
            }
        }

        if (!$success) {
            throw new SystemRequirementsNotMetException($errorMsg);
        }
    }
}
