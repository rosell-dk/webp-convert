<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\SensitiveStringOption;
use WebPConvert\Options\StringOption;

/**
 * Convert images to webp by calling cwebp binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Cwebp extends AbstractConverter
{

    use EncodingAutoTrait;
    use ExecTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new StringOption('command-line-options', ''),
            new SensitiveStringOption('rel-path-to-precompiled-binaries', './Binaries'),
            new BooleanOption('try-common-system-paths', true),
            new BooleanOption('try-supplied-binary-for-os', true)
        );
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
    // Got the precompiled binaries here: https://developers.google.com/speed/webp/docs/precompiled
    private static $suppliedBinariesInfo = [
        'WINNT' => [['cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873']],
        'Darwin' => [['cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379']],
        'SunOS' => [['cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f']],
        'FreeBSD' => [['cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573']],
        'Linux' => [
            // Dynamically linked executable.
            // It seems it is slightly faster than the statically linked
            ['cwebp-linux-1.0.2-shared', 'd6142e9da2f1cab541de10a31527c597225fff5644e66e31d62bb391c41bfbf4'],

            // Statically linked executable
            // It may be that it on some systems works, where the dynamically linked does not (see #196)
            ['cwebp-linux-1.0.2-static', 'a67092563d9de0fbced7dde61b521d60d10c0ad613327a42a81845aefa612b29'],

            // Old executable for systems where both of the above fails
            ['cwebp-linux-0.6.1', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568'],
        ]
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
        //$version = $this->detectVersion($binary);

        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions;

        //$logger->logLn('command options:' . $commandOptions);
        $this->logLn('Trying to convert by executing the following command:');
        $this->logLn($command);
        exec($command, $output, $returnCode);
        $this->logExecOutput($output);
        /*
        if ($returnCode == 255) {
            if (isset($output[0])) {
                // Could be an error like 'Error! Cannot open output file' or 'Error! ...preset... '
                $this->logLn(print_r($output[0], true));
            }
        }*/
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
        if (!ctype_print($commandLineOptions)) {
            throw new ConversionFailedException(
                'Non-printable characters are not allowed in the extra command line options'
            );
        }

        if (preg_match('#[^a-zA-Z0-9_\s\-]#', $commandLineOptions)) {
            throw new ConversionFailedException('The extra command line options contains inacceptable characters');
        }

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
     * Build command line options for a given version of cwebp.
     *
     * The "-near_lossless" param is not supported on older versions of cwebp, so skip on those.
     *
     * @param  string $version  Version of cwebp.
     * @return string
     */
    private function createCommandLineOptions($version)
    {

        $this->logLn('Creating command line options for version: ' . $version);

        // we only need two decimal places for version.
        // convert to number to make it easier to compare
        $version = preg_match('#^\d+\.\d+#', $version, $matches);
        $versionNum = 0;
        if (isset($matches[0])) {
            $versionNum = floatval($matches[0]);
        } else {
            $this->logLn(
                'Could not extract version number from the following version string: ' . $version,
                'bold'
            );
        }

        //$this->logLn('version:' . strval($versionNum));

        $options = $this->options;

        $cmdOptions = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        if ($versionNum >= 0.3) {
            $cmdOptions[] = '-metadata ' . $options['metadata'];
        }

        // preset. Appears first in the list as recommended in the docs
        if (!is_null($options['preset'])) {
            if ($options['preset'] != 'none') {
                $cmdOptions[] = '-preset ' . $options['preset'];
            }
        }

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
        if ($options['encoding'] == 'lossless') {
            // No need to add -lossless when near-lossless is used (on version >= 0.5)
            if (($options['near-lossless'] === 100) || ($versionNum < 0.5)) {
                $cmdOptions[] = '-lossless';
            }
        }

        // Near-lossles
        if ($options['near-lossless'] !== 100) {
            if ($versionNum < 0.5) {
                $this->logLn(
                    'The near-lossless option is not supported on this (rather old) version of cwebp' .
                        '- skipping it.',
                    'italic'
                );
            } else {
                // We only let near_lossless have effect when encoding is set to "lossless"
                // otherwise encoding=auto would not work as expected

                if ($options['encoding'] == 'lossless') {
                    $cmdOptions[] ='-near_lossless ' . $options['near-lossless'];
                } else {
                    $this->logLn(
                        'The near-lossless option ignored for lossy'
                    );
                }
            }
        }

        if ($options['auto-filter'] === true) {
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
        //$this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    /**
     *  Get path for supplied binary for current OS - and validate hash.
     *
     *  @return  array  Array of supplied binaries (which actually exists, and where hash validates)
     */
    private function getSuppliedBinaryPathForOS()
    {
        $this->log('Checking if we have a supplied binary for OS: ' . PHP_OS . '... ');

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $this->logLn('No we dont - not for that OS');
            return [];
        }
        $this->logLn('We do.');

        $result = [];
        $files = self::$suppliedBinariesInfo[PHP_OS];
        if (count($files) > 0) {
            $this->logLn('We in fact have ' . count($files));
        }

        foreach ($files as $i => list($file, $hash)) {
            //$file = $info[0];
            //$hash = $info[1];

            $binaryFile = __DIR__ . '/' . $options['rel-path-to-precompiled-binaries'] . '/' . $file;

            // Replace "/./" with "/" in path (we could alternatively use realpath)
            //$binaryFile = preg_replace('#\/\.\/#', '/', $binaryFile);
            // The file should exist, but may have been removed manually.
            /*
            if (!file_exists($binaryFile)) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                return false;
            }*/

            $realPathResult = realpath($binaryFile);
            if ($realPathResult === false) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                continue;
            }
            $binaryFile = $realPathResult;

            // File exists, now generate its hash
            // hash_file() is normally available, but it is not always
            // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
            // If available, validate that hash is correct.

            if (function_exists('hash_file')) {
                $binaryHash = hash_file('sha256', $binaryFile);

                if ($binaryHash != $hash) {
                    $this->logLn(
                        'Binary checksum of supplied binary is invalid! ' .
                        'Did you transfer with FTP, but not in binary mode? ' .
                        'File:' . $binaryFile . '. ' .
                        'Expected checksum: ' . $hash . '. ' .
                        'Actual checksum:' . $binaryHash . '.',
                        'bold'
                    );
                    continue;
                }
            }
            $result[] = $binaryFile;
        }

        return $result;
    }

    private function discoverBinaries()
    {
        $this->logLn('Locating cwebp binaries');

        if (defined('WEBPCONVERT_CWEBP_PATH')) {
            $this->logLn('WEBPCONVERT_CWEBP_PATH was defined, so using that path and ignoring any other');
            //$this->logLn('Value: "' . getenv('WEBPCONVERT_CWEBP_PATH') . '"');
            return [constant('WEBPCONVERT_CWEBP_PATH')];
        }
        if (!empty(getenv('WEBPCONVERT_CWEBP_PATH'))) {
            $this->logLn(
                'WEBPCONVERT_CWEBP_PATH environment variable was set, so using that path and ignoring any other'
            );
            //$this->logLn('Value: "' . getenv('WEBPCONVERT_CWEBP_PATH') . '"');
            return [getenv('WEBPCONVERT_CWEBP_PATH')];
        }

        $binaries = [];
        if ($this->options['try-common-system-paths']) {
            foreach (self::$cwebpDefaultPaths as $binary) {
                if (@file_exists($binary)) {
                    $binaries[] = $binary;
                }
            }
            if (count($binaries) == 0) {
                $this->logLn('No cwebp binaries where located in common system locations');
            } else {
                $this->logLn(strval(count($binaries)) . ' cwebp binaries found in common system locations');
            }
        }
        // TODO: exec('whereis cwebp');
        if ($this->options['try-supplied-binary-for-os']) {
            $suppliedBinaries = $this->getSuppliedBinaryPathForOS();
            foreach ($suppliedBinaries as $suppliedBinary) {
                $binaries[] = $suppliedBinary;
            }
        } else {
            $this->logLn('Configured not to try the cwebp binary that comes bundled with webp-convert');
        }

        if (count($binaries) == 0) {
            $this->logLn('No cwebp binaries to try!');
        }
        $this->logLn('A total of ' . strval(count($binaries)) . ' cwebp binaries where found');
        return $binaries;
    }

    /**
     *
     * @return  string|int  Version string (ie "1.0.2") OR return code, in case of failure
     */
    private function detectVersion($binary)
    {
        //$this->logLn('Examining binary: ' . $binary);
        $command = $binary . ' -version';
        $this->log('Executing: ' . $command);
        exec($command, $output, $returnCode);

        if ($returnCode == 0) {
            //$this->logLn('Success');
            if (isset($output[0])) {
                $this->logLn('. Result: version: ' . $output[0]);
                return $output[0];
            }
        } else {
            $this->logExecOutput($output);
            $this->logLn('');
            if ($returnCode == 127) {
                $this->logLn('Exec failed (the cwebp binary was not found at path: ' . $binary. ')');
            } else {
                $this->logLn(
                    'Exec failed (return code: ' . $returnCode . ')'
                );
                if ($returnCode == 126) {
                    $this->logLn(
                        'PS: Return code 126 means "Permission denied". The user that the command was run with does ' .
                            'not have permission to execute that binary.'
                    );
                    // TODO: further info: shell_exec('whoami')
                }
            }
            return $returnCode;
        }
    }

    /**
     *  Check versions for binaries, and return array (indexed by the binary, value being the version of the binary).
     *
     *  @return  array
     */
    private function detectVersions($binaries)
    {
        $binariesWithVersions = [];
        $binariesWithFailCodes = [];

        $this->logLn(
            'Detecting versions of the cwebp binaries found (and verifying that they can be executed in the process)'
        );
        foreach ($binaries as $binary) {
            $versionStringOrFailCode = $this->detectVersion($binary);
        //    $this->logLn($binary . ': ' . $versionString);
            if (gettype($versionStringOrFailCode) == 'string') {
                $binariesWithVersions[$binary] = $versionStringOrFailCode;
            } else {
                $binariesWithFailCodes[$binary] = $versionStringOrFailCode;
            }
        }
        return ['detected' => $binariesWithVersions, 'failed' => $binariesWithFailCodes];
    }

    /**
     * @return  boolean  success or not.
     */
    private function tryBinary($binary, $version, $useNice)
    {

        //$this->logLn('Trying binary: ' . $binary);
        $commandOptions = $this->createCommandLineOptions($version);

        $returnCode = $this->executeBinary($binary, $commandOptions, $useNice);
        if ($returnCode == 0) {
            // It has happened that even with return code 0, there was no file at destination.
            if (!file_exists($this->destination)) {
                $this->logLn('executing cweb returned success code - but no file was found at destination!');
                return false;
            } else {
                $this->logLn('Success');
                return true;
            }
        } else {
            $this->logLn(
                'Exec failed (return code: ' . $returnCode . ')'
            );
            return false;
        }
    }

    protected function doActualConvert()
    {
        $binaries = $this->discoverBinaries();

        if (count($binaries) == 0) {
            throw new SystemRequirementsNotMetException(
                'No cwebp binaries located. Check the conversion log for details.'
            );
        }

        $versions = $this->detectVersions($binaries);
        if (count($versions['detected']) == 0) {
            //$this->logLn('None of the cwebp files located can be executed.');
            if (count($binaries) == 1) {
                $errorMsg = 'The cwebp file found cannot be can be executed.';
            } else {
                $errorMsg = 'None of the cwebp files located can be executed.';
            }
            $uniqueFailCodes = array_unique(array_values($versions['failed']));
            if (count($uniqueFailCodes) == 1) {
                $errorMsg .= ' ' . (count($binaries) == 1 ? 'It' : 'All') .
                    ' failed with return code ' . $uniqueFailCodes[0];
                if ($uniqueFailCodes[0] == 126) {
                    $errorMsg .= ' (permission denied)';
                }
            } else {
                $errorMsg .= ' Failure codes : ' . implode(', ', $uniqueFailCodes);
            }

            throw new SystemRequirementsNotMetException($errorMsg);
        }

        $binaryVersions = $versions['detected'];

        if (count($binaries) > 1) {
            $this->logLn(
                'Trying executing the cwebs found until success. Starting with the ones with highest version number.'
            );
        }
        //$this->logLn('binary versions: ' . print_r($binaryVersions, true));

        // Sort binaries so those with highest numbers comes first
        arsort($binaryVersions);

        //$this->logLn('binary versions (ordered by version): ' . print_r($binaryVersions, true));

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport());

        $success = false;
        foreach ($binaryVersions as $binary => $version) {
            if ($this->tryBinary($binary, $version, $useNice)) {
                $success = true;
                break;
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
        } else {
            throw new SystemRequirementsNotMetException('Failed converting. Check the conversion log for details.');
        }
    }
}
