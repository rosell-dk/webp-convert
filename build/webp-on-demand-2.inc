<?php 
?><?php

namespace WebPConvert\Exceptions;

class WebPConvertBaseException extends \Exception
{
}

?><?php

namespace WebPConvert\Loggers;

abstract class BaseLogger
{
    /*
    $msg: message to log
    $style: null | bold | italic
    */
    abstract public function log($msg, $style = '');

    abstract public function ln();

    public function logLn($msg, $style = '')
    {
        $this->log($msg, $style);
        $this->ln();
    }

    public function logLnLn($msg, $style = '')
    {
        $this->logLn($msg, $style);
        $this->ln();
    }
}

?><?php

namespace WebPConvert\Converters;

//use WebPConvert\Converters\Cwebp;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Exceptions\CreateDestinationFileException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\TargetNotFoundException;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class ConverterHelper
{
    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public static $defaultOptions = [
        'quality' => 'auto',
        'max-quality' => 85,
        'default-quality' => 80,
        'metadata' => 'none',
        'method' => 6,
        'low-memory' => false,
        'lossless' => false,
        'converters' =>  ['cwebp', 'gd', 'imagick']
    ];

    public static function mergeOptions($options, $extraOptions)
    {
        return $options;
    }

    public static function getClassNameOfConverter($converterId)
    {
        return 'WebPConvert\\Converters\\' . ucfirst($converterId);
    }


    /* Call the "convert" method on a converter, by id.
       - but also prepares options (merges in the $extraOptions of the converter),
         prepares destination folder, and runs some standard validations */
    public static function runConverter(
        $converterId,
        $source,
        $destination,
        $options = [],
        $prepareDestinationFolder = true,
        $logger = null
    ) {
        if ($prepareDestinationFolder) {
            self::prepareDestinationFolderAndRunCommonValidations($source, $destination);
        }

        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }

        $className = self::getClassNameOfConverter($converterId);
        if (!is_callable([$className, 'convert'])) {
            throw new ConverterNotFoundException();
        }

        // Prepare options.
        // -  Remove 'converters'
        $defaultOptions = self::$defaultOptions;
        unset($defaultOptions['converters']);

        // -  Merge defaults of the converters extra options into the standard default options.
        $defaultOptions = array_merge($defaultOptions, array_column($className::$extraOptions, 'default', 'name'));

        // -  Merge $defaultOptions into provided options
        $options = array_merge($defaultOptions, $options);

        // Individual converters do not accept quality = auto. They need a number.
        // Change $options['quality'] to number, based on quality of source and several settings

        self::processQualityOption($source, $options, $logger);

        call_user_func(
            [$className, 'doConvert'],
            $source,
            $destination,
            $options,
            $logger
        );

        if (!file_exists($destination)) {
            throw new ConverterFailedException('Destination file is not there');
        }
    }

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function runConverterStack($source, $destination, $options = [], $logger = null)
    {
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        self::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $options = array_merge(self::$defaultOptions, $options);

        self::processQualityOption($source, $options, $logger);

        // Force lossless option to true for PNG images
        if (self::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        $defaultConverterOptions = $options;
        $defaultConverterOptions['converters'] = null;

        $firstFailException = null;

        // If we have set converter options for a converter, which is not in the converter array,
        // then we add it to the array
        if (isset($options['converter-options'])) {
            foreach ($options['converter-options'] as $converterName => $converterOptions) {
                if (!in_array($converterName, $options['converters'])) {
                    $options['converters'][] = $converterName;
                }
            }
        }

        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = $converter['options'];
            } else {
                $converterId = $converter;
                $converterOptions = [];
                if (isset($options['converter-options'][$converterId])) {
                    // Note: right now, converter-options are not meant to be used,
                    //       when you have several converters of the same type
                    $converterOptions = $options['converter-options'][$converterId];
                }
            }

            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);

            try {
                $logger->logLn('Trying:' . $converterId, 'italic');

                // If quality is different, we must recalculate
                if ($converterOptions['quality'] != $defaultConverterOptions['quality']) {
                    unset($converterOptions['_calculated_quality']);
                    self::processQualityOption($source, $converterOptions, $logger);
                }

                self::runConverter($converterId, $source, $destination, $converterOptions, false, $logger);

                // Still here? - well, we did it! - job is done.
                $logger->logLn('ok', 'bold');
                return true;
            } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
//                $logger->logLnLn($e->description . ' : ' . $e->getMessage());
                $logger->logLnLn($e->getMessage());

                // The converter is not operational.
                // Well, well, we will just have to try the next, then
            } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
                $logger->logLnLn($e->getMessage());

                // Converter failed in an anticipated, yet somewhat surprising fashion.
                // The converter seemed operational - requirements was in order - but it failed anyway.
                // This is moderately bad.
                // If some other converter can handle the conversion, we will let this one go.
                // But if not, we shall throw the exception

                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
                $logger->logLnLn($e->getMessage());

                // The converter declined.
                // Gd is for example throwing this, when asked to convert a PNG, but configured not to
                // We also possibly rethrow this, because it may have come as a surprise to the user
                // who perhaps only tested jpg
                if (!$firstFailException) {
                    $firstFailException = $e;
                }
            }
        }

        if ($firstFailException) {
            // At least one converter failed or declined.
            $logger->logLn('Conversion failed. None of the tried converters could convert the image', 'bold');
        } else {
            // All converters threw a ConverterNotOperationalException
            $logger->logLn('Conversion failed. None of the tried converters are operational', 'bold');
        }

        // No converters could do the job.
        // If one of them failed moderately bad, rethrow that exception.
        if ($firstFailException) {
            throw $firstFailException;
        }

        return false;
    }

    /* Try to detect quality of jpeg.
       If not possible, nothing is returned (null). Otherwise quality is returned (int)
        */
    public static function detectQualityOfJpg($filename)
    {
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $img = new Imagick($filename);

            // The required function is available as from PECL imagick v2.2.2
            if (method_exists($img, 'getImageCompressionQuality')) {
                return $img->getImageCompressionQuality();
            }
        }

        if (function_exists('shell_exec')) {
        // Try Imagick
            $quality = shell_exec("identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }
        }
    }

    public static function processQualityOption($source, &$options, $logger)
    {
        if (isset($options['_calculated_quality'])) {
            return;
        }
        if ($options['quality'] == 'auto') {
            $q = self::detectQualityOfJpg($source);
            //$logger->log('Quality set to auto... Quality of source: ');
            if (!$q) {
                $q = $options['default-quality'];
                $logger->logLn(
                    'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                    ' - Using default instead (' . $options['default-quality'] . ').'
                );

                // this allows the wpc converter to know
                $options['_quality_could_not_be_detected'] = true;
            } else {
                if ($q > $options['max-quality']) {
                    $logger->log(
                        'Quality of source is ' . $q . '. ' .
                        'This is higher than max-quality, so using that instead (' . $options['max-quality'] . ')'
                    );
                } else {
                    $logger->log('Quality set to same as source: ' . $q);
                }
            }
            $logger->ln();
            $q = min($q, $options['max-quality']);

            $options['_calculated_quality'] = $q;
        //$logger->logLn('Using quality: ' . $options['quality']);
        } else {
            $logger->logLn(
                'Quality: ' . $options['quality'] . '. ' .
                'Consider setting quality to "auto" instead. It is generally a better idea'
            );
            $options['_calculated_quality'] = $options['quality'];
        }
        $logger->ln();
    }


    public static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    // Throws an exception if the provided file doesn't exist
    public static function isValidTarget($filePath)
    {
        if (!file_exists($filePath)) {
            throw new TargetNotFoundException('File or directory not found: ' . $filePath);
        }

        return true;
    }

    // Throws an exception if the provided file's extension is invalid
    public static function isAllowedExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new InvalidFileExtensionException('Unsupported file extension: ' . $fileExtension);
        }

        return true;
    }

    // Creates folder in provided path & sets correct permissions
    public static function createWritableFolder($filePath)
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
            throw new CreateDestinationFileException(
                'Cannot overwrite ' . basename($filePath) . ' - check file permissions.'
            );
        }

        // There's either a rewritable file in $filePath or none at all.
        // If there is, simply attempt to delete it
        if (file_exists($filePath) && !unlink($filePath)) {
            throw new CreateDestinationFileException(
                'Existing file cannot be removed: ' . basename($filePath)
            );
        }

        return true;
    }

    public static function prepareDestinationFolderAndRunCommonValidations($source, $destination)
    {
        self::isValidTarget($source);
        self::isAllowedExtension($source);
        self::createWritableFolder($destination);
    }

    public static function initCurlForConverter()
    {
        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException(
                'Required curl_file_create() function is not available (requires PHP > 5.5).'
            );
        }

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }
        return $ch;
    }
}

?><?php

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

    //
    private static function executeBinary($binary, $commandOptions, $useNice, $logger)
    {
        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions;

        $logger->logLn('Trying to execute binary:' . $binary);
        //$logger->logLn();

        exec($command, $output, $returnCode);

        switch ($returnCode) {
            case 0:
                $logger->logLn('Success!');
                break;
            case 126:
                $logger->logLn(
                    'Permission denied. The user that the command was run with (' .
                    shell_exec('whoami') . ') does not have permission to execute that binary.'
                );
                break;
            case 127:
                $logger->logLn('No binary found at that location');
                break;
            default:
                $logger->logLn(
                    'Failed. Return code:' .  $returnCode .
                    '. See http://tldp.org/LDP/abs/html/exitcodes.html for failcodes'
                );
        }
        return $returnCode;
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

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        $metadata = '-metadata ' . $options['metadata'];

        // Image quality
        $quality = '-q ' . $options['_calculated_quality'];

        // Losless PNG conversion
        $lossless = ($options['lossless'] ? '-lossless' : '');

        // Built-in method option
        $method = ' -m ' . strval($options['method']);


        // TODO:
        // Why not use -af ?  (https://developers.google.com/speed/webp/docs/cwebp)
        // Would it be possible get a quality similar to source?
        // It seems so: "identify -format '%Q' yourimage.jpg"
        // (https://stackoverflow.com/questions/2024947/is-it-possible-to-tell-the-quality-level-of-a-jpeg)
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

        $useNice = (($options['use-nice']) && self::hasNiceSupport()) ? true : false;

        $commandOptions = implode(' ', $commandOptionsArray);


        // Init with common system paths
        $cwebpPathsToTest = self::$cwebpDefaultPaths;

        // Remove paths that doesn't exist
        $cwebpPathsToTest = array_filter($cwebpPathsToTest, function ($binary) {
            //return file_exists($binary);
            return @is_readable($binary);
        });

        // Try all common paths that exitst
        $success = false;
        foreach ($cwebpPathsToTest as $index => $binary) {
            $success = (self::executeBinary($binary, $commandOptions, $useNice, $logger) == 0);
            if ($success) {
                break;
            }
        }
        if (!$success) {
            //$logger->logLn('');
            if (count($cwebpPathsToTest) > 0) {
                $errorMsg .= 'Found cwebp binaries at these locations: "' .
                    implode('", "', $cwebpPathsToTest) .
                    '". However, executing these failed. ';
            } else {
                $errorMsg .= 'Found no cwebp binaries in any common locations. ';
            }
        }

        if (!$success) {
          // Try supplied binary (if available for OS, and hash is correct)
            if (isset(self::$suppliedBinariesInfo[PHP_OS])) {
                $info = self::$suppliedBinariesInfo[PHP_OS];

                $file = $info[0];
                $hash = $info[1];

                $binaryFile = __DIR__ . '/Binaries/' . $file;

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
                        $returnCode = self::executeBinary($binaryFile, $commandOptions, $useNice, $logger);
                        if ($returnCode == 0) {
                            $success = true;
                        } else {
                            $errorMsg .= 'Tried executing supplied binary (' . $binaryFile . '), but that failed too: ';
                            switch ($returnCode) {
                                case 126:
                                    $errorMsg .= 'Permission denied (user "' .
                                        trim(shell_exec('whoami')) .
                                        '" does not have permission to execute the binary)';
                                    break;
                                default:
                                    $errorMsg .= 'Fail code: ' . $returnCode;
                            }
                        }
                    }
                } else {
                    $errorMsg .= 'Supplied binary not found:' . $binaryFile;
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

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Ewww
{
    public static $extraOptions = [
        [
            'name' => 'key',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('ewww', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if ($options['key'] == '') {
            throw new ConverterNotOperationalException('Missing API key.');
        }
        if (strlen($options['key']) < 20) {
            throw new ConverterNotOperationalException(
                'Key is invalid. Keys are supposed to be 32 characters long - your key is much shorter'
            );
        }

        $keyStatus = self::getKeyStatus($options['key']);
        switch ($keyStatus) {
            case 'great':
                break;
            case 'exceeded':
                throw new ConverterNotOperationalException('quota has exceeded');
                break;
            case 'invalid':
                throw new ConverterNotOperationalException('key is invalid');
                break;
        }

        $ch = ConverterHelper::initCurlForConverter();

        $curlOptions = [
            'api_key' => $options['key'],
            'webp' => '1',
            'file' => curl_file_create($source),
            'domain' => $_SERVER['HTTP_HOST'],
            'quality' => $options['_calculated_quality'],
            'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
        ];

        curl_setopt_array(
            $ch,
            [
            CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
            CURLOPT_HTTPHEADER => [
                'User-Agent: WebPConvert',
                'Accept: image/*'
            ],
            CURLOPT_POSTFIELDS => $curlOptions,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
            ]
        );

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ConverterNotOperationalException(curl_error($ch));
        }

        // The API does not always return images.
        // For example, it may return a message such as '{"error":"invalid","t":"exceeded"}
        // Messages has a http content type of ie 'text/html; charset=UTF-8
        // Images has application/octet-stream.
        // So verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            //echo curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            /* May return this: {"error":"invalid","t":"exceeded"} */
            $responseObj = json_decode($response);
            if (isset($responseObj->error)) {
                //echo 'error:' . $responseObj->error . '<br>';
                //echo $response;
                //self::blacklistKey($key);
                //throw new ConverterNotOperationalException('The key is invalid. Blacklisted it!');
                throw new ConverterNotOperationalException('The key is invalid');
            }

            throw new ConverterNotOperationalException(
                'ewww api did not return an image. It could be that the key is invalid. Response: '
                . $response
            );
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            throw new ConverterNotOperationalException('ewww api did not return anything');
        }

        $success = file_put_contents($destination, $response);

        if (!$success) {
            throw new ConverterFailedException('Error saving file');
        }
    }

    /*
        public static function blacklistKey($key)
        {
        }

        public static function isKeyBlacklisted($key)
        {
        }*/

    /**
     *  Return "great", "exceeded" or "invalid"
     */
    public static function getKeyStatus($key)
    {
        $ch = ConverterHelper::initCurlForConverter();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/verify/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );

        // The 403 forbidden is avoided with this line.
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        // echo $response;
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        // Possible responses:
        // “great” = verification successful
        // “exceeded” = indicates a valid key with no remaining image credits.
        // an empty response indicates that the key is not valid

        if ($response == '') {
            return 'invalid';
        }
        $responseObj = json_decode($response);
        if (isset($responseObj->error)) {
            if ($responseObj->error == 'invalid') {
                return 'invalid';
            } else {
                throw new \Exception('Ewww returned unexpected error: ' . $response);
            }
        }
        if (!isset($responseObj->status)) {
            throw new \Exception('Ewww returned unexpected response to verify request: ' . $response);
        }
        switch ($responseObj->status) {
            case 'great':
            case 'exceeded':
                return $responseObj->status;
        }
        throw new \Exception('Ewww returned unexpected status to verify request: "' . $responseObj->status . '"');
    }

    public static function isWorkingKey($key)
    {
        return (self::getKeyStatus($key) == 'great');
    }

    public static function isValidKey($key)
    {
        return (self::getKeyStatus($key) != 'invalid');
    }

    public static function getQuota($key)
    {
        $ch = ConverterHelper::initCurlForConverter();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/quota/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        return $response; // ie -830 23. Seems to return empty for invalid keys
        // or empty
        //echo $response;
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

use WebPConvert\Converters\ConverterHelper;

class Gd
{
    public static $extraOptions = [
        [
            'name' => 'skip-pngs',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('gd', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('gd')) {
            throw new ConverterNotOperationalException('Required GD extension is not available.');
        }

        if (!function_exists('imagewebp')) {
            throw new ConverterNotOperationalException('Required imagewebp() function is not available.');
        }

        switch (ConverterHelper::getExtension($source)) {
            case 'png':
                if (!$options['skip-pngs']) {
                    if (!function_exists('imagecreatefrompng')) {
                        throw new ConverterNotOperationalException(
                            'Required imagecreatefrompng() function is not available.'
                        );
                    }
                    $image = imagecreatefrompng($source);
                    if (!$image) {
                        throw new ConverterFailedException(
                            'imagecreatefrompng("' . $source . '") failed'
                        );
                    }
                } else {
                    throw new ConversionDeclinedException(
                        'PNG file skipped. GD is configured not to convert PNGs'
                    );
                }
                break;
            default:
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new ConverterNotOperationalException(
                        'Required imagecreatefromjpeg() function is not available.'
                    );
                }
                $image = imagecreatefromjpeg($source);
                if (!$image) {
                    throw new ConverterFailedException('imagecreatefromjpeg("' . $source . '") failed');
                }
        }

        // Checks if either imagecreatefromjpeg() or imagecreatefrompng() returned false

        $success = imagewebp($image, $destination, $options['_calculated_quality']);

        if (!$success) {
            throw new ConverterFailedException('Call to imagewebp() failed. Probably failed writing file');
        }

        /*
         * This hack solves an `imagewebp` bug
         * See https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
         *
         */

        if (filesize($destination) % 2 == 1) {
            file_put_contents($destination, "\0", FILE_APPEND);
        }

        imagedestroy($image);
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

//use WebPConvert\Exceptions\TargetNotFoundException;

class Imagick
{
    public static $extraOptions = [];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('imagick', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if (!extension_loaded('imagick')) {
            throw new ConverterNotOperationalException('Required iMagick extension is not available.');
        }

        if (!class_exists('Imagick')) {
            throw new ConverterNotOperationalException(
                'iMagick is installed, but not correctly. The class Imagick is not available'
            );
        }

        $im = new \Imagick($source);

        // Throws an exception if iMagick does not support WebP conversion
        if (!in_array('WEBP', $im->queryFormats())) {
            throw new ConverterNotOperationalException('iMagick was compiled without WebP support.');
        }

        $options = array_merge(ConverterHelper::$defaultOptions, $options);

        // Force lossless option to true for PNG images
        if (ConverterHelper::getExtension($source) == 'png') {
            $options['lossless'] = true;
        }

        $im->setImageFormat('WEBP');

        /*
         * More about iMagick's WebP options:
         * http://www.imagemagick.org/script/webp.php
         * https://developers.google.com/speed/webp/docs/cwebp
         * https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php
         */

        // TODO: We could easily support all webp options with a loop
        $im->setOption('webp:method', strval($options['method']));
        $im->setOption('webp:low-memory', strval($options['low-memory']));
        $im->setOption('webp:lossless', strval($options['lossless']));



        $im->setImageCompressionQuality($options['_calculated_quality']);

        // TODO:
        // Should we set alpha channel for PNG's like suggested here:
        // https://gauntface.com/blog/2014/09/02/webp-support-with-imagemagick-and-php ??
        // It seems that alpha channel works without... (at least I see completely transparerent pixels)

        // TODO: Check out other iMagick methods, see http://php.net/manual/de/imagick.writeimage.php#114714
        // 1. file_put_contents($destination, $im)
        // 2. $im->writeImage($destination)
        $success = $im->writeImageFile(fopen($destination, 'wb'));

        if (!$success) {
            throw new ConverterFailedException('Failed writing file');
        }
    }
}

?><?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Wpc
{
    public static $extraOptions = [
        [
            'name' => 'secret',
            'type' => 'string',
            'sensitive' => true,
            'default' => 'my dog is white',
            'required' => true
        ],
        [
            'name' => 'url',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('wpc', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if ($options['url'] == '') {
            throw new ConverterNotOperationalException(
                'Missing URL. You must install WebpConvertCloudService on a server, and supply url'
            );
        }

        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }


        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException(
                'Required curl_file_create() PHP function is not available (requires PHP > 5.5).'
            );
        }

        if (!empty($options['secret'])) {
            // if secret is set, we need md5() and md5_file() functions
            if (!function_exists('md5')) {
                throw new ConverterNotOperationalException(
                    'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                    'contents. ' .
                    'But the required md5() PHP function is not available.'
                );
            }
            if (!function_exists('md5_file')) {
                throw new ConverterNotOperationalException(
                    'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                    'contents. But the required md5_file() PHP function is not available.'
                );
            }
        }

        // Got some code here:
        // https://coderwall.com/p/v4ps1a/send-a-file-via-post-with-curl-and-php

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }

        $optionsToSend = $options;

        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // Ask the cloud service to do it, rather than using what we came up with.
            $optionsToSend['quality'] = 'auto';
        } else {
            $optionsToSend['quality'] = $options['_calculated_quality'];
        }

        unset($optionsToSend['converters']);
        unset($optionsToSend['secret']);
        unset($optionsToSend['_quality_could_not_be_detected']);
        unset($optionsToSend['_calculated_quality']);

        curl_setopt_array($ch, [
            CURLOPT_URL => $options['url'],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => [
                'file' => curl_file_create($source),
                'hash' => md5(md5_file($source) . $options['secret']),
                'options' => json_encode($optionsToSend)
            ],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new ConverterNotOperationalException('Curl error:' . curl_error($ch));
        }

        // Check if we got a 404
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            curl_close($ch);
            throw new ConverterFailedException(
                'WPC was not found at the specified URL - we got a 404 response.'
            );
        }

        // The WPC cloud service either returns an image or an error message
        // Images has application/octet-stream.
        // Verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);

            if (substr($response, 0, 1) == '{') {
                $responseObj = json_decode($response, true);
                if (isset($responseObj['errorCode'])) {
                    switch ($responseObj['errorCode']) {
                        case 0:
                            throw new ConverterFailedException(
                                'WPC reported problems with server setup: "' .
                                $responseObj['errorMessage'] . '"'
                            );
                        case 1:
                            throw new ConverterFailedException(
                                'WPC denied us access to the service: "' . $responseObj['errorMessage'] . '"'
                            );
                        default:
                            throw new ConverterFailedException(
                                'WPC failed: "' . $responseObj['errorMessage'] . '"'
                            );
                    }
                }
            }

            // WPC 0.1 returns 'failed![error messag]' when conversion fails. Handle that.
            if (substr($response, 0, 7) == 'failed!') {
                throw new ConverterFailedException(
                    'WPC failed converting image: "' . substr($response, 7) . '"'
                );
            }

            $errorMsg = 'Error: Unexpected result. We did not receive an image. We received: "';
            $errorMsg .= str_replace("\r", '', str_replace("\n", '', htmlentities(substr($response, 0, 400))));
            throw new ConverterFailedException($errorMsg . '..."');
            //throw new ConverterNotOperationalException($response);
        }

        $success = file_put_contents($destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConverterFailedException('Error saving file');
        }
        /*
                $curlOptions = [
                    'api_key' => $options['key'],
                    'webp' => '1',
                    'file' => curl_file_create($source),
                    'domain' => $_SERVER['HTTP_HOST'],
                    'quality' => $options['quality'],
                    'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
                ];

                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: WebPConvert',
                        'Accept: image/*'
                    ],
                    CURLOPT_POSTFIELDS => $curlOptions,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);*/
    }
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter does not exist.';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFileException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination file. Check file permisions!';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFolderException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination folder. Check file permisions!';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class InvalidFileExtensionException extends WebPConvertBaseException
{
    public $description = 'The converter does not accept the file extension';
}

?><?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class TargetNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter could not locate source file';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConversionDeclinedException extends WebPConvertBaseException
{
    public $description = 'The converter declined converting';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterFailedException extends WebPConvertBaseException
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}

?><?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotOperationalException extends WebPConvertBaseException
{
    public $description = 'The converter is not operational';
}

?><?php

namespace WebPConvert\Loggers;

use WebPConvert\Loggers\BaseLogger;

class BufferLogger extends BaseLogger
{
    public $entries = array();

    public function log($msg, $style = '')
    {
        $this->entries[] = [$msg, $style];
    }

    public function ln()
    {
        $this->entries[] = '';
    }

    public function getHtml()
    {
        $html = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $html .= '<br>';
            } else {
                list($msg, $style) = $entry;

                if ($style == 'bold') {
                    $html .= '<b>' . $msg . '</b>';
                } elseif ($style == 'italic') {
                    $html .= '<i>' . $msg . '</i>';
                } else {
                    $html .= $msg;
                }
            }
        }
        return $html;
    }

    public function getText($newLineChar = ' ')
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                if (substr($text, -2) != '. ') {
                    $text .= '. ';
                }
            } else {
                list($msg, $style) = $entry;
                $text .= $msg;
            }
        }

        return $text;
    }
}

?><?php

namespace WebPConvert\Loggers;

class EchoLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
        if ($style == 'bold') {
            echo '<b>' . $msg . '</b>';
        } elseif ($style == 'italic') {
            echo '<i>' . $msg . '</i>';
        } else {
            echo $msg;
        }
    }

    public function ln()
    {
        echo '<br>';
    }
}

?><?php

namespace WebPConvert\Loggers;

class VoidLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
    }

    public function ln()
    {
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\BufferLogger;
use WebPConvert\Converters\ConverterHelper;
use WebPConvert\ServeExistingOrConvert;
use WebPConvert\Serve\Report;

//use WebPConvert\Loggers\EchoLogger;

class ConvertAndServe
{
    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',

        'show-report' => false,
        'reconvert' => false,
        'serve-original' => false,
        'add-x-header-status' => true,
        'add-x-header-options' => false,
        'add-vary-header' => true,
        'converters' =>  ['cwebp', 'gd', 'imagick']
    ];

    private static function addHeadersPreventingCaching()
    {
        // Prevent caching
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    private static function addXStatusHeader($text, $options)
    {
        if ($options['add-x-header-status']) {
            header('X-WebP-Convert-Status: ' . $text);
        }
    }

    private static function addXOptionsHeader($options)
    {
        if (!$options['add-x-header-options']) {
            return;
        }
        header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($options));
    }

    protected static function serve404()
    {
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        header($protocol . " 404 Not Found");
    }

    protected static function serveOriginal($source)
    {
        $arr = explode('.', $source);
        $ext = array_pop($arr);
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
                header('Content-type: image/jpeg');
                break;
            case 'png':
                header('Content-type: image/png');
                break;
        }

        if (@readfile($source) === false) {
            header('X-WebP-Convert: Could not read file');
            return false;
        }
        return true;
    }

    protected static function serveErrorMessageImage($msg)
    {
        // Generate image containing error message
        header('Content-type: image/gif');

        // Prevent caching image
        self::addHeadersPreventingCaching();

        // TODO: handle if this fails...
        $image = imagecreatetruecolor(620, 200);
        imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
        // echo imagewebp($image);
        echo imagegif($image);
        imagedestroy($image);
    }

    protected static function fail($description, $failArgs, $critical = false)
    {
        $options = $failArgs['options'];
        //print_r($options);
        self::addXStatusHeader('Failed (' . $description . ')', $options);

        $action = $critical ? $options['fail-when-original-unavailable'] : $options['fail'];

        $title = 'Conversion failed';
        switch ($action) {
            case 'original':
                if (!self::serveOriginal($source)) {
                    self::serve404();
                };
                break;
            case '404':
                self::serve404();
                break;
            case 'report-as-image':
                // todo: handle if this fails
                self::serveErrorMessageImage($title . '. ' . $description);
                break;
            case 'report':
                echo '<h1>' . $title . '</h1>' . $description;
                break;
        }
    }

    protected static function criticalFail($description, $failArgs)
    {
        return self::fail($description, $failArgs, true);
    }


    /**
     * Main method
     */
    public static function convertAndServe($source, $destination, $options)
    {
        // For backward compatability:
        if (isset($options['critical-fail']) && !isset($options['fail-when-original-unavailable'])) {
            $options['fail-when-original-unavailable'] = $options['critical-fail'];
        }

        $options = array_merge(self::$defaultOptions, $options);

        $failArgs = [
            'source' => $source,
            'destination' => $destination,
            'options' => $options,
        ];

        self::addXOptionsHeader($options);

        if (empty($source)) {
            self::criticalFail('missing source argument', $failArgs);
            return false;
        }
        if (@!file_exists($source)) {
            self::criticalFail('source not found', $failArgs);
            return false;
        }
        if (empty($destination)) {
            self::fail('missing destination argument', $failArgs);
            return false;
        }
//Report::convertAndReport($source, $destination, $options);
        if ($options['show-report']) {
            self::addXStatusHeader('Reporting...', $options);
            Report::convertAndReport($source, $destination, $options);
            return true;  // yeah, lets say that a report is always a success, even if conversion is a failure
        }

        if ($options['serve-original']) {
            self::addXStatusHeader('Serving original image (was explicitly told to)', $options);
            if (!self::serveOriginal($source)) {
                self::criticalFail('could not read source file', $failArgs);
                return false;
            }
            return true;
        }


        if (@file_exists($destination)) {
            if (ServeExistingOrConvert::shouldWeServeExisting($source, $destination, $options)) {
                return ServeExistingOrConvert::serveExisting($destination, $options);
            }

            // Serve source file if it is lighter than destination
            $filesizeDestination = @filesize($destination);
            $filesizeSource = @filesize($source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                self::addXStatusHeader('Serving original image - because it is smaller than the converted!', $options);
                return self::serveOriginal($source);
            }
        }

        // Check if source file if it has been modified (we must check before doing the conversion...)
        $originalHasChanged = false;
        if (@file_exists($destination)) {
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                $originalHasChanged = true;
            }
        }


        $failAction = $options['fail'];
        $criticalFailAction = $options['fail-when-original-unavailable'];

        $criticalFail = false;
        $success = false;
        $bufferLogger = new BufferLogger();

        try {
            $success = WebPConvert::convert($source, $destination, $options, $bufferLogger);

            if ($success) {
                header('Content-type: image/webp');
                if ($originalHasChanged) {
                    self::addXStatusHeader('Serving freshly converted image (the original had changed)', $options);
                } else {
                    self::addXStatusHeader('Serving freshly converted image', $options);
                }
                ServeExistingOrConvert::addVaryHeader($options);

                // Should we add Content-Length header?
                // header('Content-Length: ' . filesize($file));
                if (@readfile($destination)) {
                    return true;
                } else {
                    self::fail('could not read the freshly converted file', $failArgs);
                    return false;
                }
            } else {
                $description = 'No converters are operational';
                $msg = '';
            }
        } catch (\WebPConvert\Exceptions\InvalidFileExtensionException $e) {
            $criticalFail = true;
            $description = 'Failed (invalid file extension)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Failed (source file not found)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'Failure (no converters could convert the image)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'Failure (no converters could/wanted to convert the image)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\ConverterNotFoundException $e) {
            $description = 'Failure (a converter was not found!)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFileException $e) {
            $description = 'Failure (cannot create destination file)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFolderException $e) {
            $description = 'Failure (cannot create destination folder)';
            $msg = $e->getMessage();
        } catch (\Exception $e) {
            $description = 'Failure (an unanticipated exception was thrown)';
            $msg = $e->getMessage();
        }


        // Next line is commented out, because we need to be absolute sure that the details does not violate syntax
        // We could either try to filter it, or we could change WebPConvert, such that it only provides safe texts.
        // header('X-WebP-Convert-And-Serve-Details: ' . $bufferLogger->getText());

        self::fail($description, $failArgs);
        return false;
        //echo '<p>This is how conversion process went:</p>' . $bufferLogger->getHtml();
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Converters\ConverterHelper;
use WebPConvert\Loggers\EchoLogger;

//use WebPConvert\Loggers\EchoLogger;

class Report
{

    public static function flattenConvertersArray($options)
    {
        // TODO: If there are more of the same converters,
        // they should be added as ie 'wpc-2', 'wpc-3', etc
        foreach ($options['converters'] as &$converter) {
            if (is_array($converter)) {
                $converterName = $converter['converter'];
                if (!isset($options['converter-options'][$converterName])) {
                    if (isset($converter['options'])) {
                        if (!isset($options['converter-options'])) {
                            $options['converter-options'] = [];
                        }
                        $options['converter-options'][$converterName] = $converter['options'];
                    }
                }
                $converter = $converterName;
            }
        }
        return $options;
    }

    /* Hides sensitive options */
    public static function getPrintableOptions($options)
    {
        $printable_options = [];

        // (psst: the is_callable check is needed in order to work with WebPConvert v1.0)
        if (is_callable('ConverterHelper', 'getClassNameOfConverter')) {
            $printable_options = self::flattenConvertersArray($options);
            if (isset($printable_options['converter-options'])) {
                foreach ($printable_options['converter-options'] as $converterName => &$converterOptions) {
                    $className = ConverterHelper::getClassNameOfConverter($converterName);

                    // (pstt: the isset check is needed in order to work with WebPConvert v1.0)
                    if (isset($className::$extraOptions)) {
                        foreach ($className::$extraOptions as $extraOption) {
                            if ($extraOption['sensitive']) {
                                if (isset($converterOptions[$extraOption['name']])) {
                                    $converterOptions[$extraOption['name']] = '*******';
                                }
                            }
                        }
                    }
                }
            }
        }
        return $printable_options;
    }

    public static function getPrintableOptionsAsString($options, $glue = '. ')
    {
        $optionsForPrint = [];
        foreach (self::getPrintableOptions($options) as $optionName => $optionValue) {
            $printValue = '';
            if ($optionName == 'converter-options') {
                $converterNames = [];
                $extraConvertOptions = $optionValue;
                //print_r($extraConvertOptions);
                /*
                foreach ($optionValue as $converterName => $converterOptions) {

                    if (is_array($converter)) {
                        $converterName = $converter['converter'];
                        if (isset($converter['options'])) {
                            $extraConvertOptions[$converter['converter']] = $converter['options'];
                        }
                    } else {
                        $converterName = $converter;
                    }
                    $converterNames[] = $converterName;
                }*/
                $glueMe = [];
                foreach ($extraConvertOptions as $converter => $extraOptions) {
                    $opt = [];
                    foreach ($extraOptions as $oName => $oValue) {
                        $opt[] = $oName . ':"' . $oValue . '"';
                    }
                    $glueMe[] = '(' . $converter . ': (' . implode($opt, ', ') . '))';
                }
                $printValue = implode(',', $glueMe);
            } else {
                switch (gettype($optionValue)) {
                    case 'boolean':
                        if ($optionValue === true) {
                            $printValue = 'true';
                        } elseif ($optionValue === false) {
                            $printValue = 'false';
                        }
                        break;
                    case 'string':
                        $printValue = '"' . $optionValue . '"';
                        break;
                    case 'array':
                        $printValue = implode(', ', $optionValue);
                        break;
                    case 'integer':
                        $printValue = $optionValue;
                        break;
                    default:
                        $printValue = $optionValue;
                }
            }
            $optionsForPrint[] = $optionName . ': ' . $printValue;
        }
        return implode($glue, $optionsForPrint);
    }

    public static function convertAndReport($source, $destination, $options)
    {
        ?>
<html>
    <head>
        <style>td {vertical-align: top} table {color: #666}</style>
        <script>
            function showOptions(elToHide) {
                document.getElementById('options').style.display='block';
                elToHide.style.display='none';
            }
        </script>
    </head>
    <body>
        <table>
            <tr><td><i>source:</i></td><td><?php echo $source ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo $destination ?><td></tr>
            <tr>
                <td><i>options:</i></td>
                <td>
                    <i style="text-decoration:underline;cursor:pointer" onclick="showOptions(this)">click to see</i>
                    <pre id="options" style="display:none"><?php
                        echo print_r(self::getPrintableOptionsAsString($options, '<br>'), true);
                    ?></pre>
                    <?php //echo json_encode(self::getPrintableOptions($options)); ?>
                    <?php //echo print_r(self::getPrintableOptions($options), true); ?>
                </td>
            </tr>
        </table>
        <br>
        <?php
        // TODO:
        // We could display warning if unknown options are set
        // but that requires that WebPConvert also describes its general options

        try {
            $echoLogger = new EchoLogger();
            $success = WebPConvert::convert($source, $destination, $options, $echoLogger);
        } catch (\Exception $e) {
            $success = false;

            $msg = $e->getMessage();

            echo '<b>' . $msg . '</b>';
            exit;
        }

        if ($success) {
            //echo 'ok';
        } else {
            echo '<b>Conversion failed. None of the tried converters are operational</b>';
        }
        ?>
    </body>
    </html>
        <?php
    }
}

