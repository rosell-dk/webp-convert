<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Helpers\BinaryDiscovery;
use WebPConvert\Options\OptionFactory;
use ExecWithFallback\ExecWithFallback;

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

    /**
     * Get the options unique for this converter
     *
     * @return  array  Array of options
     */
    public function getUniqueOptions($imageType)
    {
        $binariesForOS = [];
        if (isset(self::$suppliedBinariesInfo[PHP_OS])) {
            foreach (self::$suppliedBinariesInfo[PHP_OS] as $i => list($file, $hash, $version)) {
                $binariesForOS[] = $file;
            }
        }

        return OptionFactory::createOptions([
            self::niceOption(),
            ['try-cwebp', 'boolean', [
                'title' => 'Try plain cwebp command',
                'description' =>
                    'If set, the converter will try executing "cwebp -version". In case it succeeds, ' .
                    'and the version is higher than those working cwebps found using other methods, ' .
                    'the conversion will be done by executing this cwebp.',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true
                ]
            ]],
            ['try-discovering-cwebp', 'boolean', [
                'title' => 'Try discovering cwebp binary',
                'description' =>
                    'If set, the converter will try to discover installed cwebp binaries using a "which -a cwebp" ' .
                    'command, or in case that fails, a "whereis -b cwebp" command. These commands will find ' .
                    'cwebp binaries residing in PATH',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true
                ]
            ]],
            ['try-common-system-paths', 'boolean', [
                'title' => 'Try locating cwebp in common system paths',
                'description' =>
                    'If set, the converter will look for a cwebp binaries residing in common system locations ' .
                    'such as "/usr/bin/cwebp". If such exist, it is assumed that they are valid cwebp binaries. ' .
                    'A version check will be run on the binaries found (they are executed with the "-version" flag. ' .
                    'The cwebp with the highest version found using this method and the other enabled methods will ' .
                    'be used for the actual conversion.' .
                    'Note: All methods for discovering cwebp binaries are per default enabled. You can save a few ' .
                    'microseconds by disabling some, but it is probably not worth it, as your ' .
                    'setup will then become less resilient to system changes.',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true
                ]
            ]],
            ['try-supplied-binary-for-os', 'boolean', [
                'title' => 'Try precompiled cwebp binaries',
                'description' =>
                    'If set, the converter will try use a precompiled cwebp binary that comes with webp-convert. ' .
                    'But only if it has a higher version that those found by other methods. As the library knows ' .
                    'the versions of its bundled binaries, no additional time is spent executing them with the ' .
                    '"-version" parameter. The binaries are hash-checked before executed. ' .
                    'The library btw. comes with several versions of precompiled cwebps because they have different ' .
                    'dependencies - some works on some systems and others on others.',
                'default' => true,
                'ui' => [
                    'component' => 'checkbox',
                    'advanced' => true
                ]
            ]],
            ['skip-these-precompiled-binaries', 'string', [
              'title' => 'Skip these precompiled binaries',
                  'description' =>
                      '',
                  'default' => '',
                  'ui' => [
                      'component' => 'multi-select',
                      'advanced' => true,
                      'options' => $binariesForOS,
                      'display' => "option('cwebp-try-supplied-binary-for-os') == true"
                  ]

            ]],
            ['rel-path-to-precompiled-binaries', 'string', [
              'title' => 'Rel path to precompiled binaries',
                  'description' =>
                      '',
                  'default' => './Binaries',
                  'ui' => [
                      'component' => '',
                      'advanced' => true,
                      'display' => "option('cwebp-try-supplied-binary-for-os') == true"
                  ],
                  'sensitive' => true
            ]],
            ['command-line-options', 'string', [
              'title' => 'Command line options',
                  'description' =>
                      '',
                  'default' => '',
                  'ui' => [
                      'component' => 'input',
                      'advanced' => true,
                  ]

            ]],
        ]);
    }


    // OS-specific binaries included in this library, along with hashes
    // If other binaries are going to be added, notice that the first argument is what PHP_OS returns.
    // (possible values, see here: https://stackoverflow.com/questions/738823/possible-values-for-php-os)
    // Got the precompiled binaries here: https://developers.google.com/speed/webp/docs/precompiled
    // Note when changing binaries:
    // 1: Do NOT use "." in filename. It causes unzipping to fail on some hosts
    // 2: Set permission to 775. 755 causes unzipping to fail on some hosts
    private static $suppliedBinariesInfo = [
        'WINNT' => [
            ['cwebp-120-windows-x64.exe', '2849fd06012a9eb311b02a4f8918ae4b16775693bc21e95f4cc6a382eac299f9', '1.2.0'],

            // Keep the 1.1.0 version a while, in case some may have problems with the 1.2.0 version
            ['cwebp-110-windows-x64.exe', '442682869402f92ad2c8b3186c02b0ea6d6da68d2f908df38bf905b3411eb9fb', '1.1.0'],
        ],
        'Darwin' => [
            ['cwebp-110-mac-10_15', 'bfce742da09b959f9f2929ba808fed9ade25c8025530434b6a47d217a6d2ceb5', '1.1.0'],
        ],
        'SunOS' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-060-solaris', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f', '0.6.0']
        ],
        'FreeBSD' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-060-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573', '0.6.0']
        ],
        'Linux' => [

            // PS: Some experience the following error with 1.20:
            // /lib/x86_64-linux-gnu/libm.so.6: version `GLIBC_2.29' not found
            // (see #278)

            ['cwebp-120-linux-x86-64', 'f1b7dc03e95535a6b65852de07c0404be4dba078af48369f434ee39b2abf8f4e', '1.2.0'],

            // As some experience the an error with 1.20 (see #278), we keep the 1.10
            ['cwebp-110-linux-x86-64', '1603b07b592876dd9fdaa62b44aead800234c9474ff26dc7dd01bc0f4785c9c6', '1.1.0'],

            // Statically linked executable
            // It may be that it on some systems works, where the dynamically linked does not (see #196)
            [
                'cwebp-103-linux-x86-64-static',
                'ab96f01b49336da8b976c498528080ff614112d5985da69943b48e0cb1c5228a',
                '1.0.3'
            ],

            // Old executable for systems in case all of the above fails
            ['cwebp-061-linux-x86-64', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568', '0.6.1'],
        ]
    ];

    /**
     *  Check all hashes of the precompiled binaries.
     *
     *  This isn't used when converting, but can be used as a startup check.
     */
    public static function checkAllHashes()
    {
        foreach (self::$suppliedBinariesInfo as $os => $arr) {
            foreach ($arr as $i => list($filename, $expectedHash)) {
                $actualHash = hash_file("sha256", __DIR__ . '/Binaries/' . $filename);
                if ($expectedHash != $actualHash) {
                    throw new \Exception(
                        'Hash for ' . $filename . ' is incorrect! ' .
                        'Checksum is: ' . $actualHash . ', ' .
                        ', but expected: ' . $expectedHash .
                        '. Did you transfer with FTP, but not in binary mode? '
                    );
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

        // Redirect stderr to same place as stdout with "2>&1"
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/

        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions . ' 2>&1';

        //$logger->logLn('command options:' . $commandOptions);
        $this->logLn('Trying to convert by executing the following command:');
        $startExecuteBinaryTime = self::startTimer();
        ;
        $this->logLn($command);
        ExecWithFallback::exec($command, $output, $returnCode);
        $this->logExecOutput($output);
        $this->logTimeSpent($startExecuteBinaryTime, 'Executing cwebp binary took: ');
        $this->logLn('');
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
        } else {
            $this->logLn('Ignoring metadata option (requires cwebp 0.3)', 'italic');
        }

        // preset. Appears first in the list as recommended in the docs
        if (!is_null($options['preset'])) {
            if ($options['preset'] != 'none') {
                $cmdOptions[] = '-preset ' . escapeshellarg($options['preset']);
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
                $this->logLn('Ignoring near-lossless option (requires cwebp 0.5)', 'italic');
            } else {
                // The "-near_lossless" flag triggers lossless encoding. We don't want that to happen,
                // we want the "encoding" option to be respected, and we need it to be in order for
                // encoding=auto to work.
                // So: Only set when "encoding" is set to "lossless"
                if ($options['encoding'] == 'lossless') {
                    $cmdOptions[] = '-near_lossless ' . $options['near-lossless'];
                } else {
                    $this->logLn(
                        'The near-lossless option ignored for lossy'
                    );
                }
            }
        }

        // Autofilter
        if ($options['auto-filter'] === true) {
            $cmdOptions[] = '-af';
        }

        // SharpYUV
        if ($options['sharp-yuv'] === true) {
            if ($versionNum >= 0.6) {  // #284
                $cmdOptions[] = '-sharp_yuv';
            } else {
                $this->logLn('Ignoring sharp-yuv option (requires cwebp 0.6)', 'italic');
            }
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

        $commandOptions = implode(' ', $cmdOptions);
        //$this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    private function checkHashForSuppliedBinary($binaryFile, $hash)
    {
        // File exists, now generate its hash
        // hash_file() is normally available, but it is not always
        // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
        // If available, validate that hash is correct.

        if (function_exists('hash_file')) {
            $this->logLn(
                'Checking checksum for supplied binary: ' . $binaryFile
            );
            $startHashCheckTime = self::startTimer();

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
                return false;
                ;
            }

            $this->logTimeSpent($startHashCheckTime, 'Checksum test took: ');
        }
        return true;
    }

    /**
     *  Get supplied binary info for current OS.
     *  paths are made absolute and checked. Missing are removed
     *
     *  @return  array  Two arrays.
     *                  First array:  array of files (absolute paths)
     *                  Second array: array of info objects (absolute path, hash and version)
     */
    private function getSuppliedBinaryInfoForCurrentOS()
    {
        $this->log('Checking if we have a supplied precompiled binary for your OS (' . PHP_OS . ')... ');

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $this->logLn('No we dont - not for that OS');
            return [];
        }

        $filesFound = [];
        $info = [];
        $files = self::$suppliedBinariesInfo[PHP_OS];
        if (count($files) == 1) {
            $this->logLn('We do.');
        } else {
            $this->logLn('We do. We in fact have ' . count($files));
        }

        $skipThese = explode(',', $this->options['skip-these-precompiled-binaries']);

        //$this->logLn('However, skipping' . print_r($skipThese, true));

        foreach ($files as $i => list($file, $hash, $version)) {
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
            if (in_array($file, $skipThese)) {
                $this->logLn('Skipped: ' . $file . ' (was told to in the "skip-these-precompiled-binaries" option)');
                continue;
            }


            $realPathResult = realpath($binaryFile);
            if ($realPathResult === false) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                continue;
            }
            $binaryFile = $realPathResult;
            $filesFound[] = $realPathResult;
            $info[] = [$realPathResult, $hash, $version, $file];
        }
        return [$filesFound, $info];
    }

    private function who()
    {
        ExecWithFallback::exec('whoami 2>&1', $whoOutput, $whoReturnCode);
        if (($whoReturnCode == 0) && (isset($whoOutput[0]))) {
            return 'user: "' . $whoOutput[0] . '"';
        } else {
            return 'the user that the command was run with';
        }
    }

    /**
     * Detect the version of a cwebp binary.
     *
     * @param string $binary  The binary to detect version for (path to cwebp or simply "cwebp")
     *
     * @return  string|int  Version string (ie "1.0.2") OR return code, in case of failure
     */
    private function detectVersion($binary)
    {
        $command = $binary . ' -version 2>&1';
        $this->log('- Executing: ' . $command);
        ExecWithFallback::exec($command, $output, $returnCode);

        if ($returnCode == 0) {
            if (isset($output[0])) {
                $this->logLn('. Result: version: *' . $output[0] . '*');
                return $output[0];
            }
        } else {
            $this->log('. Result: ');
            if ($returnCode == 127) {
                $this->logLn(
                    '*Exec failed* (the cwebp binary was not found at path: ' . $binary .
                    ', or it had missing library dependencies)'
                );
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
        return ''; // Will not happen. Just so phpstan doesn't complain
    }

    /**
     * Check versions for an array of binaries.
     *
     * @param  array  $binaries  array of binaries to detect the version of
     *
     * @return  array  the "detected" key holds working binaries and their version numbers, the
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

    private function logBinariesFound($binaries, $startTime)
    {
        if (count($binaries) == 0) {
            $this->logLn('Found 0 binaries' . self::getTimeStr($startTime));
        } else {
            $this->logLn('Found ' . count($binaries) . ' binaries' . self::getTimeStr($startTime));
            foreach ($binaries as $binary) {
                $this->logLn('- ' . $binary);
            }
        }
    }

    private function logDiscoverAction($optionName, $description)
    {
        if ($this->options[$optionName]) {
            $this->logLn(
                'Discovering binaries ' . $description . ' ' .
                '(to skip this step, disable the "' . $optionName . '" option)'
            );
        } else {
            $this->logLn(
                'Skipped discovering binaries ' . $description . ' ' .
                '(enable "' . $optionName . '" if you do not want to skip that step)'
            );
        }
    }

    private static function startTimer()
    {
        if (function_exists('microtime')) {
            return microtime(true);
        } else {
            return 0;
        }
    }

    private static function readTimer($startTime)
    {
        if (function_exists('microtime')) {
            $endTime = microtime(true);
            $seconds = ($endTime - $startTime);
            return round(($seconds * 1000));
        } else {
            return 0;
        }
    }

    private static function getTimeStr($startTime, $pre = ' (spent ', $post = ')')
    {
        if (function_exists('microtime')) {
            $ms = self::readTimer($startTime);
            return $pre . $ms . ' ms' . $post;
        }
        return '';
    }

    private function logTimeSpent($startTime, $pre = 'Spent: ')
    {
        if (function_exists('microtime')) {
            $ms = self::readTimer($startTime);
            $this->logLn($pre . $ms . ' ms');
        }
    }

    /**
     *  @return array   Two arrays (in an array).
     *                  First array: binaries found,
     *                  Second array: supplied binaries info for current OS
     */
    private function discoverCwebpBinaries()
    {
        $this->logLn(
            'Looking for cwebp binaries.'
        );

        $startDiscoveryTime = self::startTimer();

        $binaries = [];

        if (defined('WEBPCONVERT_CWEBP_PATH')) {
            $this->logLn('WEBPCONVERT_CWEBP_PATH was defined, so using that path and ignoring any other');
            return [constant('WEBPCONVERT_CWEBP_PATH')];
        }
        if (!empty(getenv('WEBPCONVERT_CWEBP_PATH'))) {
            $this->logLn(
                'WEBPCONVERT_CWEBP_PATH environment variable was set, so using that path and ignoring any other'
            );
            return [getenv('WEBPCONVERT_CWEBP_PATH')];
        }

        if ($this->options['try-cwebp']) {
            $startTime = self::startTimer();
            $this->logLn(
                'Discovering if a plain cwebp call works (to skip this step, disable the "try-cwebp" option)'
            );
            $result = $this->detectVersion('cwebp');
            if (gettype($result) == 'string') {
                $this->logLn('We could get the version, so yes, a plain cwebp call works ' .
                '(spent ' . self::readTimer($startTime) . ' ms)');
                $binaries[] = 'cwebp';
            } else {
                $this->logLn('Nope a plain cwebp call does not work' . self::getTimeStr($startTime));
            }
        } else {
            $this->logLn(
                'Skipped discovering if a plain cwebp call works' .
                ' (enable the "try-cwebp" option if you do not want to skip that step)'
            );
        }

        // try-discovering-cwebp
        $startTime = self::startTimer();
        $this->logDiscoverAction('try-discovering-cwebp', 'using "which -a cwebp" command.');
        if ($this->options['try-discovering-cwebp']) {
            $moreBinaries = BinaryDiscovery::discoverInstalledBinaries('cwebp');
            $this->logBinariesFound($moreBinaries, $startTime);
            $binaries = array_merge($binaries, $moreBinaries);
        }

        // 'try-common-system-paths'
        $startTime = self::startTimer();
        $this->logDiscoverAction('try-common-system-paths', 'by peeking in common system paths');
        if ($this->options['try-common-system-paths']) {
            $moreBinaries = BinaryDiscovery::discoverInCommonSystemPaths('cwebp');
            $this->logBinariesFound($moreBinaries, $startTime);
            $binaries = array_merge($binaries, $moreBinaries);
        }

        // try-supplied-binary-for-os
        $suppliedBinariesInfo = [[], []];
        $startTime = self::startTimer();
        $this->logDiscoverAction('try-supplied-binary-for-os', 'which are distributed with the webp-convert library');
        if ($this->options['try-supplied-binary-for-os']) {
            $suppliedBinariesInfo = $this->getSuppliedBinaryInfoForCurrentOS();
            $moreBinaries = $suppliedBinariesInfo[0];
            $this->logBinariesFound($moreBinaries, $startTime);
            //$binaries = array_merge($binaries, $moreBinaries);
        }

        $this->logTimeSpent($startDiscoveryTime, 'Discovering cwebp binaries took: ');
        $this->logLn('');

        return [array_values(array_unique($binaries)), $suppliedBinariesInfo];
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
     *  @param  array  $versions  The array which we get from calling ::detectVersions($binaries)
     *  @return string  An informative and to the point error message.
     */
    private function composeMeaningfullErrorMessageNoVersionsWorking($versions)
    {
        // TODO: Take "supplied" into account

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
        list($foundBinaries, $suppliedBinariesInfo) = $this->discoverCwebpBinaries();
        $suppliedBinaries = $suppliedBinariesInfo[0];
        $allBinaries = array_merge($foundBinaries, $suppliedBinaries);

        //$binaries = $this->discoverCwebpBinaries();
        if (count($allBinaries) == 0) {
            $this->logLn('No cwebp binaries found!');

            $discoverOptions = [
                'try-supplied-binary-for-os',
                'try-common-system-paths',
                'try-cwebp',
                'try-discovering-cwebp'
            ];
            $disabledDiscoverOptions = [];
            foreach ($discoverOptions as $discoverOption) {
                if (!$this->options[$discoverOption]) {
                    $disabledDiscoverOptions[] = $discoverOption;
                }
            }
            if (count($disabledDiscoverOptions) == 0) {
                throw new SystemRequirementsNotMetException(
                    'No cwebp binaries found.'
                );
            } else {
                throw new SystemRequirementsNotMetException(
                    'No cwebp binaries found. Try enabling the "' .
                    implode('" option or the "', $disabledDiscoverOptions) . '" option.'
                );
            }
        }

        $detectedVersions = [];
        if (count($foundBinaries) > 0) {
            $this->logLn(
                'Detecting versions of the cwebp binaries found' .
                (count($suppliedBinaries) > 0 ? ' (except supplied binaries)' : '.')
            );
            $startDetectionTime = self::startTimer();
            $versions = $this->detectVersions($foundBinaries);
            $detectedVersions = $versions['detected'];

            $this->logTimeSpent($startDetectionTime, 'Detecting versions took: ');
        }

        //$suppliedVersions = [];
        $suppliedBinariesHash = [];
        $suppliedBinariesFilename = [];

        $binaryVersions = $detectedVersions;
        foreach ($suppliedBinariesInfo[1] as list($path, $hash, $version, $filename)) {
            $binaryVersions[$path] = $version;
            $suppliedBinariesHash[$path] = $hash;
            $suppliedBinariesFilename[$path] = $filename;
        }

        //$binaryVersions = array_merge($detectedVersions, $suppliedBinariesInfo);

        // TODO: reimplement
        /*
        $versions['supplied'] = $suppliedBinariesInfo;

        $binaryVersions = $versions['detected'];
        if ((count($binaryVersions) == 0) && (count($suppliedBinaries) == 0)) {
            // No working cwebp binaries found, no supplied binaries found

            throw new SystemRequirementsNotMetException(
                $this->composeMeaningfullErrorMessageNoVersionsWorking($versions)
            );
        }*/

        // Sort binaries so those with highest numbers comes first
        arsort($binaryVersions);
        $this->logLn(
            'Binaries ordered by version number.'
        );
        foreach ($binaryVersions as $binary => $version) {
            $this->logLn('- ' . $binary . ': (version: ' . $version . ')');
        }

        // Execute!
        $this->logLn(
            'Starting conversion, using the first of these. If that should fail, ' .
            'the next will be tried and so on.'
        );
        $useNice = ($this->options['use-nice'] && $this->checkNiceSupport());

        $success = false;
        foreach ($binaryVersions as $binary => $version) {
            if (isset($suppliedBinariesHash[$binary])) {
                if (!$this->checkHashForSuppliedBinary($binary, $suppliedBinariesHash[$binary])) {
                    continue;
                }
            }
            if ($this->tryCwebpBinary($binary, $version, $useNice)) {
                $success = true;
                break;
            } else {
                if (isset($suppliedBinariesFilename[$binary])) {
                    $this->logLn(
                        'Note: You can prevent trying this precompiled binary, by setting the ' .
                        '"skip-these-precompiled-binaries" option to "' . $suppliedBinariesFilename[$binary] . '"'
                    );
                }
            }
        }

        // cwebp sets file permissions to 664 but instead ..
        // .. $this->source file permissions should be used

        if ($success) {
            $fileStatistics = stat($this->source);
            if ($fileStatistics !== false) {
                // Apply same permissions as source file, but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                chmod($this->destination, $permissions);
            }
        } else {
            throw new SystemRequirementsNotMetException('Failed converting. Check the conversion log for details.');
        }
    }
}
