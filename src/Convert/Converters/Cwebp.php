<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
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
            new BooleanOption('try-cwebp', true),
            new BooleanOption('try-common-system-paths', false),
            new BooleanOption('try-discovering-cwebp', true),
            new BooleanOption('try-supplied-binary-for-os', true)
        );
    }

    // System paths to look for cwebp binary
    private static $cwebpDefaultPaths = [
        //'cwebp',
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
        'WINNT' => [
            ['cwebp-1.0.3-windows-x64.exe', 'b3aaab03ca587e887f11f6ae612293d034ee04f4f7f6bc7a175321bb47a10169'],
        ],
        'Darwin' => [
            ['cwebp-1.0.3-mac-10.14', '7332ed5f0d4091e2379b1eaa32a764f8c0d51b7926996a1dc8b4ef4e3c441a12'],
        ],
        'SunOS' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-0.6.0-solaris', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f']
        ],
        'FreeBSD' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-0.6.0-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573']
        ],
        'Linux' => [
            // Dynamically linked executable.
            // It seems it is slightly faster than the statically linked
            ['cwebp-1.0.3-linux-x86-64', 'a663215a46d347f63e1ca641c18527a1ae7a2c9a0ae85ca966a97477ea13dfe0'],

            // Statically linked executable
            // It may be that it on some systems works, where the dynamically linked does not (see #196)
            ['cwebp-1.0.3-linux-x86-64-static', 'ab96f01b49336da8b976c498528080ff614112d5985da69943b48e0cb1c5228a'],

            // Old executable for systems in case both of the above fails
            ['cwebp-0.6.1-linux-x86-64', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568'],
        ]
    ];

    /**
     *  Check all hashes of the precompiled binaries.
     *
     *  This isn't used when converting, but can be used as a startup check.
     */
    public function checkAllHashes()
    {
        foreach (self::$suppliedBinariesInfo as $os => $arr) {
            foreach ($arr as $i => list($filename, $hash)) {
                if ($hash != hash_file("sha256", __DIR__ . '/Binaries/' . $filename)) {
                    throw new \Exception('Hash for ' . $filename . ' is incorrect!');
                }
            }
        }
    }

    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        $options = $this->options;
        if (!$options['try-supplied-binary-for-os'] &&
            !$options['try-common-system-paths'] &&
            !$options['try-cwebp'] &&
            !$options['try-discovering-cwebp']
        ) {
            throw new ConverterNotOperationalException(
                'Configured to neither try pure cwebp command, ' .
                'nor look for cweb binaries in common system locations and ' .
                'nor to use one of the supplied precompiled binaries. ' .
                'But these are the only ways this converter can convert images. No conversion can be made!'
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
     * @param  string $version  Version of cwebp (ie "1.0.3")
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
            /*
            In some years, we can use the splat instead (requires PHP 5.6)
            array_push(
                $cmdOptions,
                ...self::escapeShellArgOnCommandLineOptions($options['command-line-options'])
            );
            */
            foreach (self::escapeShellArgOnCommandLineOptions($options['command-line-options']) as $cmdLineOption) {
                array_push($cmdOptions, $cmdLineOption);
            }
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
        $this->log('Checking if we have a supplied precompiled binary for your OS (' . PHP_OS . ')... ');

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $this->logLn('No we dont - not for that OS');
            return [];
        }

        $result = [];
        $files = self::$suppliedBinariesInfo[PHP_OS];
        if (count($files) == 1) {
            $this->logLn('We do.');
        } else {
            $this->logLn('We do. We in fact have ' . count($files));
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

    /**
     * A fileExist function that actually works! (it requires exec(), though).
     *
     * A warning-free fileExists method that works even when open_basedir is in effect.
     * For performance reasons, the file_exists method will be tried first. If that issues a warning,
     * we fall back to using exec().
     *
     */
    private function fileExists($path)
    {
        // There is a challenges here:
        // We want to suppress warnings, but at the same time we want to know that it happened.
        // We achieve this by registering an error handler (that is done automatically for all converters
        // in WarningLoggerTrait).

        // Disable warnings. This does not disable the warning counter (in WarningLoggerTrait).
        $this->disableWarningsTemporarily();

        // Reset warning count. This way we will be able to determine if a warning occured after
        // the file_exists() call (by checking warning count)
        $this->resetWarningCount();
        $found = @file_exists($path);
        $this->reenableWarnings();

        if ($found) {
            return true;
        }

        if ($this->getWarningCount() == 0) {
            // no warnings were issued. So no open_basedir restriction in effect that messes
            // things up. We can safely conclude that the file does not exist.
            return false;
        }

        // The path was not found with file_exists, but on the other hand:
        // a warning was issued, which probably means we got a open_basedir restriction in effect warning.
        // So the file COULD exist.

        // Lets try to find out by executing "ls path/to/cwebp"
        exec('ls ' . $path, $output, $returnCode);
        if (($returnCode == 0) && (isset($output[0]))) {
            return true;
        }

        // We assume that "ls" command is general available!
        // As that failed, we can conclude the file does not exist.
        return false;
    }


    /**
     * Discover binaries by looking in common system paths.
     *
     * We try a small set of common system paths, such as "/usr/bin/cwebp", filtering out
     * non-existing paths.
     *
     * @return array Cwebp binaries found in common system locations
     */
    private function discoverCwebpsInCommonSystemPaths()
    {
        $binaries = [];
        foreach (self::$cwebpDefaultPaths as $binary) {
            if ($this->fileExists($binary)) {
                $binaries[] = $binary;
            }
        }
        return $binaries;
    }

    /**
     * Discover installed binaries using "whereis -b cwebp"
     *
     * @return array  Array of cwebp paths discovered (possibly empty)
     */
    private function discoverBinariesUsingWhereIsCwebp()
    {
        // This method was added due to #226.
        exec('whereis -b cwebp', $output, $returnCode);
        if (($returnCode == 0) && (isset($output[0]))) {
            $result = $output[0];
            // Ie: "cwebp: /usr/bin/cwebp /usr/local/bin/cwebp"
            if (preg_match('#^cwebp:\s(.*)$#', $result, $matches)) {
                $paths = explode(' ', $matches[1]);
                $this->logLn(
                    'Discovered ' . count($paths) . ' ' .
                    'installed versions of cwebp using "whereis -b cwebp" command. Result: "' . $result . '"'
                );
                return $paths;
            }
        }
        return [];
    }

    /**
     * Discover installed binaries using "which -a cwebp"
     *
     * @return array  Array of cwebp paths discovered (possibly empty)
     */
    private function discoverBinariesUsingWhichCwebp()
    {
        // As suggested by @cantoute here:
        // https://wordpress.org/support/topic/sh-1-usr-local-bin-cwebp-not-found/
        exec('which -a cwebp', $output, $returnCode);
        if ($returnCode == 0) {
            $paths = $output;
            $this->logLn(
                'Discovered ' . count($paths) . ' ' .
                'installed versions of cwebp using "which -a cwebp" command. Result: "' . implode('", "', $paths) . '"'
            );
            return $paths;
        }
        return [];
    }

    private function discoverBinaries()
    {
        $paths = $this->discoverBinariesUsingWhereIsCwebp();
        if (count($paths) > 0) {
            return $paths;
        }

        $paths = $this->discoverBinariesUsingWhichCwebp();
        if (count($paths) > 0) {
            return $paths;
        }
        return [];
    }

    private function who()
    {
        exec('whoami', $whoOutput, $whoReturnCode);
        if (($whoReturnCode == 0) && (isset($whoOutput[0]))) {
            return 'user: "' . $whoOutput[0] . '"';
        } else {
            return 'the user that the command was run with';
        }
    }

    /**
     *
     * @return  string|int  Version string (ie "1.0.2") OR return code, in case of failure
     */
    private function detectVersion($binary)
    {
        $command = $binary . ' -version';
        $this->log('- Executing: ' . $command);
        exec($command, $output, $returnCode);

        if ($returnCode == 0) {
            if (isset($output[0])) {
                $this->logLn('. Result: version: *' . $output[0] . '*');
                return $output[0];
            }
        } else {
            $this->log('. Result: ');
            if ($returnCode == 127) {
                $this->logLn('*Exec failed* (the cwebp binary was not found at path: ' . $binary. ')');
            } else {
                if ($returnCode == 126) {
                    $this->logLn(
                        '*Exec failed*. ' .
                        'Permission denied (' . $this->who() . ' does not have permission to execute that binary)'
                    );
                } else {
                    $this->logLn(
                        '*Exec failed* (return code: ' . $returnCode . ')'
                    );
                    $this->logExecOutput($output);
                }
            }
            return $returnCode;
        }
    }

    /**
     *  Check versions for an array of binaries.
     *
     *  @return  array  the "detected" key holds working binaries and their version numbers, the
     *                  the "failed" key holds failed binaries and their error codes.
     */
    private function detectVersions($binaries)
    {
        $binariesWithVersions = [];
        $binariesWithFailCodes = [];

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
     *  Detect versions of all cwebps that are of relevance (according to configuration).
     *
     *  @return  array  the "detected" key holds working binaries and their version numbers, the
     *                  the "failed" key holds failed binaries and their error codes.
     */
    private function getCwebpVersions()
    {
        if (defined('WEBPCONVERT_CWEBP_PATH')) {
            $this->logLn('WEBPCONVERT_CWEBP_PATH was defined, so using that path and ignoring any other');
            return $this->detectVersions([constant('WEBPCONVERT_CWEBP_PATH')]);
        }
        if (!empty(getenv('WEBPCONVERT_CWEBP_PATH'))) {
            $this->logLn(
                'WEBPCONVERT_CWEBP_PATH environment variable was set, so using that path and ignoring any other'
            );
            return $this->detectVersions([getenv('WEBPCONVERT_CWEBP_PATH')]);
        }

        $versions = [];
        if ($this->options['try-cwebp']) {
            $this->logLn(
                'Detecting version of plain cwebp command (it may not be available, but we try nonetheless)'
            );
            $versions = $this->detectVersions(['cwebp']);
        }
        if ($this->options['try-discovering-cwebp']) {
            $this->logLn(
                'Detecting versions of of cwebp discovered using "whereis cwebp" command'
            );
            $versions = array_replace_recursive($versions, $this->detectVersions($this->discoverBinaries()));
        }
        if ($this->options['try-common-system-paths']) {
            $this->logLn(
                'Detecting versions of cwebp binaries found in common system paths'
            );
            $versions = array_replace_recursive(
                $versions,
                $this->detectVersions($this->discoverCwebpsInCommonSystemPaths())
            );
        }
        if ($this->options['try-supplied-binary-for-os']) {
            $versions = array_merge_recursive(
                $versions,
                $this->detectVersions($this->getSuppliedBinaryPathForOS())
            );
        }
        return $versions;
    }

    /**
     * Try executing a cwebp binary (or command, like: "cwebp")
     *
     * @param  string  $binary
     * @param  string  $version  Version of cwebp (ie "1.0.3")
     * @param  boolean $useNice  Whether to use "nice" command or not
     *
     * @return boolean  success or not.
     */
    private function tryCwebpBinary($binary, $version, $useNice)
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

    /**
     *  Helper for composing an error message when no converters are working.
     *
     *  @param  array  $versions  The array which we get from calling ::getCwebpVersions()
     *  @return string  An informative and to the point error message.
     */
    private function composeMeaningfullErrorMessageNoVersionsWorking($versions)
    {

        // PS: array_values() is used to reindex
        $uniqueFailCodes = array_values(array_unique(array_values($versions['failed'])));
        $justOne = (count($versions['failed']) == 1);

        if (count($uniqueFailCodes) == 1) {
            if ($uniqueFailCodes[0] == 127) {
                return 'No cwebp binaries located. Check the conversion log for details.';
            }
        }
        // If there are more failures than 127, the 127 failures are unintesting.
        // It is to be expected that some of the common system paths does not contain a cwebp.
        $uniqueFailCodesBesides127 = array_values(array_diff($uniqueFailCodes, [127]));

        if (count($uniqueFailCodesBesides127) == 1) {
            if ($uniqueFailCodesBesides127[0] == 126) {
                return 'No cwebp binaries could be executed (permission denied for ' . $this->who() . ').';
            }
        }

        $errorMsg = '';
        if ($justOne) {
            $errorMsg .= 'The cwebp file found cannot be can be executed ';
        } else {
            $errorMsg .= 'None of the cwebp files can be executed ';
        }
        if (count($uniqueFailCodesBesides127) == 1) {
            $errorMsg .= '(failure code: ' . $uniqueFailCodesBesides127[0] . ')';
        } else {
            $errorMsg .= '(failure codes: ' . implode(', ', $uniqueFailCodesBesides127) . ')';
        }
        return $errorMsg;
    }

    protected function doActualConvert()
    {
        $versions = $this->getCwebpVersions();
        $binaryVersions = $versions['detected'];
        if (count($binaryVersions) == 0) {
            // No working cwebp binaries found.

            throw new SystemRequirementsNotMetException(
                $this->composeMeaningfullErrorMessageNoVersionsWorking($versions)
            );
        }

        // Sort binaries so those with highest numbers comes first
        arsort($binaryVersions);
        $this->logLn(
            'Here is what we found, ordered by version number.'
        );
        foreach ($binaryVersions as $binary => $version) {
            $this->logLn('- ' . $binary . ': (version: ' . $version .')');
        }

        // Execute!
        $this->logLn(
            'Trying the first of these. If that should fail (it should not), the next will be tried and so on.'
        );
        $useNice = (($this->options['use-nice']) && self::hasNiceSupport());
        $success = false;
        foreach ($binaryVersions as $binary => $version) {
            if ($this->tryCwebpBinary($binary, $version, $useNice)) {
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
