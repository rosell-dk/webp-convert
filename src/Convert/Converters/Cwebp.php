<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\LosslessAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
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
class Cwebp extends AbstractConverter
{

    use LosslessAutoTrait;
    use ExecTrait;

    protected function getOptionDefinitionsExtra()
    {
        return [
            ['alpha-quality', 'integer', 80],
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
        'cwebp',
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

    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        $options = $this->options;
        if (!$options['try-supplied-binary-for-os'] && !$options['try-common-system-paths']) {
            throw new ConverterNotOperationalException(
                'Configured to neither look for cweb binaries in common system locations, ' .
                'nor to use one of the supplied precompiled binaries. But these are the only ways ' .
                'this converter can convert images. No conversion can be made!'
            );
        }
    }

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
     *  Use "escapeshellarg()" on all arguments in a commandline string of options
     *
     *  For example, passing '-sharpness 5 -crop 10 10 40 40 -low_memory' will result in:
     *  [
     *    "-sharpness '5'"
     *    "-crop '10' '10' '40' '40'"
     *    "-low_memory"
     *  ]
     * @param  string $commandLineOptions  string which can contain multiple commandline options
     * @return array  Array of command options
     */
    private static function escapeShellArgOnCommandLineOptions($commandLineOptions)
    {
        $cmdOptions = [];
        $arr = explode(' -', ' ' . $commandLineOptions);
        foreach ($arr as $cmdOption) {
            $pos = strpos($cmdOption, ' ');
            $cName = '';
            if (!$pos) {
                $cName = $cmdOption;
                if ($cName == '') {
                    continue;
                }
                $cmdOptions[] = '-' . $cName;
            } else {
                $cName = substr($cmdOption, 0, $pos);
                $cValues = substr($cmdOption, $pos + 1);
                $cValuesArr = explode(' ', $cValues);
                foreach ($cValuesArr as &$cArg) {
                    $cArg = escapeshellarg($cArg);
                }
                $cValues = implode(' ', $cValuesArr);
                $cmdOptions[] = '-' . $cName . ' ' . $cValues;
            }
        }
        return $cmdOptions;
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        $options = $this->options;

        $cmdOptions = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $cmdOptions[] = '-metadata ' . $options['metadata'];

        // Size
        $addedSizeOption = false;
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource = filesize($this->source);
            if ($sizeSource !== false) {
                $targetSize = floor($sizeSource * $options['size-in-percentage'] / 100);
                $cmdOptions[] = '-size ' . $targetSize;
                $addedSizeOption = true;
            }
        }

        // quality
        if (!$addedSizeOption) {
            $cmdOptions[] = '-q ' . $this->getCalculatedQuality();
        }

        // alpha-quality
        if ($this->options['alpha-quality'] !== 100) {
            $cmdOptions[] = '-alpha_q ' . escapeshellarg($this->options['alpha-quality']);
        }

        // Losless PNG conversion
        if ($options['lossless'] === true) {
            // No need to add -lossless when near-lossless is used
            if ($options['near-lossless'] === 100) {
                $cmdOptions[] = '-lossless';
            }
        }

        // Near-lossles
        if ($options['near-lossless'] !== 100) {
            // We only let near_lossless have effect when lossless is set.
            // otherwise lossless auto would not work as expected
            if ($options['lossless'] === true) {
                $cmdOptions[] ='-near_lossless ' . $options['near-lossless'];
            }
        }

        if ($options['autofilter'] === true) {
            $cmdOptions[] = '-af';
        }

        // Built-in method option
        $cmdOptions[] = '-m ' . strval($options['method']);

        // Built-in low memory option
        if ($options['low-memory']) {
            $cmdOptions[] = '-low_memory';
        }

        // command-line-options
        if ($options['command-line-options']) {
            array_push(
                $cmdOptions,
                ...self::escapeShellArgOnCommandLineOptions($options['command-line-options'])
            );
        }

        // Source file
        $cmdOptions[] = escapeshellarg($this->source);

        // Output
        $cmdOptions[] = '-o ' . escapeshellarg($this->destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $cmdOptions[] = '2>&1';

        $commandOptions = implode(' ', $cmdOptions);
        $this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    /**
     *
     *
     * @return  string  Error message if failure, empty string if successful
     */
    private function composeErrorMessageForCommonSystemPathsFailures($failureCodes)
    {
        if (count($failureCodes) == 1) {
            switch ($failureCodes[0]) {
                case 126:
                    return 'Permission denied. The user that the command was run with (' .
                        shell_exec('whoami') . ') does not have permission to execute any of the ' .
                        'cweb binaries found in common system locations. ';
                case 127:
                    return 'Found no cwebp binaries in any common system locations. ';
                default:
                    return 'Tried executing cwebp binaries in common system locations. ' .
                        'All failed (exit code: ' . $failureCodes[0] . '). ';
            }
        } else {
            /**
             * $failureCodesBesides127 is used to check first position ($failureCodesBesides127[0])
             * however position can vary as index can be 1 or something else. array_values() would
             * always start from 0.
             */
            $failureCodesBesides127 = array_values(array_diff($failureCodes, [127]));

            if (count($failureCodesBesides127) == 1) {
                switch ($failureCodesBesides127[0]) {
                    case 126:
                        return 'Permission denied. The user that the command was run with (' .
                        shell_exec('whoami') . ') does not have permission to execute any of the cweb ' .
                        'binaries found in common system locations. ';
                        break;
                    default:
                        return 'Tried executing cwebp binaries in common system locations. ' .
                        'All failed (exit code: ' . $failureCodesBesides127[0] . '). ';
                }
            } else {
                return 'None of the cwebp binaries in the common system locations could be executed ' .
                '(mixed results - got the following exit codes: ' . implode(',', $failureCodes) . '). ';
            }
        }
    }

    /**
     * Try executing cwebp in common system paths
     *
     * @param  boolean  $useNice          Whether to use nice
     * @param  string   $commandOptions   for the exec call
     *
     * @return  array  Unique failure codes in case of failure, empty array in case of success
     */
    private function tryCommonSystemPaths($useNice, $commandOptions)
    {
        $errorMsg = '';
        //$failures = [];
        $failureCodes = [];

        // Loop through paths
        foreach (self::$cwebpDefaultPaths as $index => $binary) {
            $returnCode = $this->executeBinary($binary, $commandOptions, $useNice);
            if ($returnCode == 0) {
                $this->logLn('Successfully executed binary: ' . $binary);
                return [];
            } else {
                //$failures[] = [$binary, $returnCode];
                if ($returnCode == 127) {
                    $this->logLn(
                        'Trying to execute binary: ' . $binary . '. Failed (not found)'
                    );
                } else {
                    $this->logLn(
                        'Trying to execute binary: ' . $binary . '. Failed (return code: ' . $returnCode . ')'
                    );
                }
                if (!in_array($returnCode, $failureCodes)) {
                    $failureCodes[] = $returnCode;
                }
            }
        }
        return $failureCodes;
    }

    /**
     * Try executing supplied cwebp for PHP_OS.
     *
     * @param  boolean  $useNice          Whether to use nice
     * @param  string   $commandOptions   for the exec call
     * @param  array    $failureCodesForCommonSystemPaths  Return codes from the other attempt
     *                                                     (in order to produce short error message)
     *
     * @return  string  Error message if failure, empty string if successful
     */
    private function trySuppliedBinaryForOS($useNice, $commandOptions, $failureCodesForCommonSystemPaths)
    {
        $this->logLn('Trying to execute supplied binary for OS: ' . PHP_OS);

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            return 'No supplied binaries found for OS:' . PHP_OS;
        }

        $info = self::$suppliedBinariesInfo[PHP_OS];

        $file = $info[0];
        $hash = $info[1];

        $binaryFile = __DIR__ . '/' . $options['rel-path-to-precompiled-binaries'] . '/' . $file;


        // The file should exist, but may have been removed manually.
        if (!file_exists($binaryFile)) {
            return 'Supplied binary not found! It ought to be here:' . $binaryFile;
        }

        // File exists, now generate its hash

        // hash_file() is normally available, but it is not always
        // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
        // If available, validate that hash is correct.

        if (function_exists('hash_file')) {
            $binaryHash = hash_file('sha256', $binaryFile);

            if ($binaryHash != $hash) {
                return 'Binary checksum of supplied binary is invalid! ' .
                    'Did you transfer with FTP, but not in binary mode? ' .
                    'File:' . $binaryFile . '. ' .
                    'Expected checksum: ' . $hash . '. ' .
                    'Actual checksum:' . $binaryHash . '.';
            }
        }

        $returnCode = $this->executeBinary($binaryFile, $commandOptions, $useNice);
        if ($returnCode == 0) {
            // yay!
            $this->logLn('success!');
            return '';
        }

        $errorMsg = 'Tried executing supplied binary for ' . PHP_OS . ', ' .
            ($options['try-common-system-paths'] ? 'but that failed too' : 'but failed');


        if (($options['try-common-system-paths']) && (count($failureCodesForCommonSystemPaths) > 0)) {
            // check if it was the same error
            // if it was, simply refer to that with "(same problem)"
            $majorFailCode = 0;
            if (count($failureCodesForCommonSystemPaths) == 1) {
                $majorFailCode = $failureCodesForCommonSystemPaths[0];
            } else {
                $failureCodesBesides127 = array_values(array_diff($failureCodesForCommonSystemPaths, [127]));
                if (count($failureCodesBesides127) == 1) {
                    $majorFailCode = $failureCodesBesides127[0];
                } else {
                    // it cannot be summarized into a single code
                }
            }
            if ($majorFailCode != 0) {
                $errorMsg .= ' (same problem)';
                return $errorMsg;
            }
        }

        if ($returnCode > 128) {
            $errorMsg .= '. The binary did not work (exit code: ' . $returnCode . '). ' .
                'Check out https://github.com/rosell-dk/webp-convert/issues/92';
        } else {
            switch ($returnCode) {
                case 0:
                    // success!
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
        return $errorMsg;
    }

    protected function doActualConvert()
    {
        $errorMsg = '';
        $options = $this->options;
        $useNice = (($options['use-nice']) && self::hasNiceSupport());

        $commandOptions = $this->createCommandLineOptions();

        // Try all common paths that exists
        $success = false;

        $failureCodes = [];

        if ($options['try-common-system-paths']) {
            $failureCodes = $this->tryCommonSystemPaths($useNice, $commandOptions);
            $success = (count($failureCodes) == 0);
            $errorMsg = $this->composeErrorMessageForCommonSystemPathsFailures($failureCodes);
        }

        if (!$success && $options['try-supplied-binary-for-os']) {
            $errorMsg2 = $this->trySuppliedBinaryForOS($useNice, $commandOptions, $failureCodes);
            $errorMsg .= $errorMsg2;
            $success = ($errorMsg2 == '');
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
