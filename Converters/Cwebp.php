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

    protected static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            if (!function_exists('exec')) {
                throw new \Exception('exec() is not enabled.');
            }

            $binaries = self::updateBinaries(
                self::$binaryInfo[0],
                self::$binaryInfo[1],
                self::$cwebpDefaultPaths
            );
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
        }

        // Build options string
        $options = '-q ' . $quality;
        $options .= (
            $stripMetadata
            ? ' -metadata none'
            : ' -metadata all'
        );
        // comma separated list of metadata to copy from the input to the output if present.
        // Valid values: all, none (default), exif, icc, xmp

        if (self::getExtension($source) == 'png') {
            $options .= ' -lossless';
        }

        if (defined('WEBPCONVERT_CWEBP_METHOD')) {
            $options .= ' -m ' . WEBPCONVERT_CWEBP_METHOD;
        } else {
            $options .= ' -m 6';
        }

        if (defined('WEBPCONVERT_CWEBP_LOW_MEMORY')) {
            $options .= (
                WEBPCONVERT_CWEBP_LOW_MEMORY
                ? ' -low_memory'
                : ''
            );
        } else {
            $options .= ' -low_memory';
        }
        //$options .= ' -low_memory';

        // $options .= ' -quiet';
        $options .= ' ' . self::escapeFilename($source) . ' -o ' . self::escapeFilename($destination) . ' 2>&1';

        // Test if "nice" is available
        // ($nice will be set to "nice ", if it is)
        $nice = '';
        exec("nice 2>&1", $nice_output);
        if (is_array($nice_output) && isset($nice_output[0])) {
            if (preg_match('/usage/', $nice_output[0]) || (preg_match('/^\d+$/', $nice_output[0]))) {
                // Nice is available.
                // We run with default niceness (+10)
                // https://www.lifewire.com/uses-of-commands-nice-renice-2201087
                // https://www.computerhope.com/unix/unice.htm
                $nice = 'nice ';
            }
        }
        // WebPConvert::logMessage('parameters:' . $options);

        // Try all paths
        $success = false;
        foreach ($binaries as $index => $binary) {
            $cmd = $nice . $binary . ' ' . $options;
            exec($cmd, $output, $returnCode);
            // Return codes:
            // 0: everything ok!
            // 127: binary cannot be found
            // 255: target not found
            if ($returnCode == 0) {
                // Success!
                // cwebp however sets file permissions to 664. We want same as parent folder (but no executable bits)
                // Set correct file permissions.
                $stat = stat(dirname($destination));
                $permissions = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
                chmod($destination, $permissions);
                // TODO cwebp also appears to set file owner. We want same owner as parent folder
                return true;
            } else {
                // If supplied bin failed, log some information
                if (($index == 0) && ($supplied_bin_error == '')) {
                    $msg = '<b>Supplied binary found, but it exited with error code ' . $returnCode . '. </b>';
                    switch ($returnCode) {
                        case 127:
                            $msg .= 'This probably means that the binary was not found. ';
                            break;
                        case 255:
                            $msg .= 'This probably means that the target was not found. ';
                            break;
                    }
                    $msg .= 'Output was: ' . print_r($output, true);
                    // WebPConvert::logMessage($msg);
                }
            }
        }

        return false;

        // Check the version
        //   (the appended "2>&1" is in order to get the output - thanks for your comment, Simon
        //    @ http://php.net/manual/en/function.exec.php)
        /*
        exec( "$bin -version 2>&1", $version );
        if (empty($version)) {
            return 'Failed getting version';
        }
        if (!preg_match( '/0.\d.\d/', $version[0] ) ) {
            return 'Unexpected version format';
        }
        */
    }
}
