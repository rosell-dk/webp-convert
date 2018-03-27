<?php

namespace WebPConvert\Converters;

class Cwebp
{
    private static $defaultCwebpPaths = array( // System paths to look for cwebp binary
        '/usr/bin/cwebp',
        '/usr/local/bin/cwebp',
        '/usr/gnu/bin/cwebp',
        '/usr/syno/bin/cwebp'
    );

    public static function convert($source, $destination, $quality, $strip_metadata)
    {
        try {
            if (!function_exists('exec')) {
                throw new \Exception('exec() is not enabled.');
            }
        } catch (\Exception $e) {
            throw $e;
        }

        // OS-specific binary will be prepended to an array, but only if it passes some tests ..
        // Select binary
        $binary = array(
          'WinNT' => array( 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'),
          'Darwin' => array( 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'),
          'SunOS' => array( 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'),
          'FreeBSD' => array( 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'),
          'Linux' => array( 'cwebp-linux', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568')
        )[PHP_OS];

        $supplied_bin_error = '';
        if (!$binary) {
            $supplied_bin_error = 'We do not have a supplied bin for your OS (' . PHP_OS . ')';
        } else {
            $bin = __DIR__ . '/Binaries/' . $binary[0];
            if (!file_exists($bin)) {
                $supplied_bin_error = 'bin file missing ( ' . $bin . ')';
            } else {
                // Check Checksum
                $binary_sum = hash_file('sha256', $bin);
                if ($binary_sum != $binary[1]) {
                    $supplied_bin_error = 'sha256 sum of supplied binary is invalid!';
                }

                // Also check mimetype?
                //ewww_image_optimizer_mimetype( $binary_path, 'b' )
            }
        }

        if ($supplied_bin_error == '') {
            array_unshift(self::$defaultCwebpPaths, $bin);
        } else {
            // WebPConvert::logMessage('Not able to use supplied bin. ' . $supplied_bin_error);
        }

        // Build options string
        $options = '-q ' . $quality;
        $options .= (
            $strip_metadata
            ? ' -metadata none'
            : ' -metadata all'
        );
        // comma separated list of metadata to copy from the input to the output if present.
        // Valid values: all, none (default), exif, icc, xmp

        $parts = explode('.', $source);
        $ext = array_pop($parts);
        if ($ext == 'png') {
            $options .= ' -lossless';
        }

        if (defined("WEBPCONVERT_CWEBP_METHOD")) {
            $options .= ' -m ' . WEBPCONVERT_CWEBP_METHOD;
        } else {
            $options .= ' -m 6';
        }

        if (defined("WEBPCONVERT_CWEBP_LOW_MEMORY")) {
            $options .= (
                WEBPCONVERT_CWEBP_LOW_MEMORY
                ? ' -low_memory'
                : ''
            );
        } else {
            $options .= ' -low_memory';
        }
        //$options .= ' -low_memory';


        function escapeFilename($string)
        {
            // esc whitespace
            $string = preg_replace('/\s/', '\\ ', $string);

            // esc quotes (but it fails anyway...)
            $string = filter_var($string, FILTER_SANITIZE_MAGIC_QUOTES);

            // strip control characters
            // https://stackoverflow.com/questions/12769462/filter-flag-strip-low-vs-filter-flag-strip-high
            $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);


            return $string;
        }

        // $options .= ' -quiet';
        $options .= ' ' . escapeFilename($source) . ' -o ' . escapeFilename($destination) . ' 2>&1';

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
        foreach (self::$defaultCwebpPaths as $i => $bin) {
            // WebPConvert::logMessage('trying to execute binary: ' . $bin);

            $cmd = $nice . $bin . ' ' . $options;

            exec($cmd, $output, $return_var);
            // Return codes:
            // 0: everything ok!
            // 127: binary cannot be found
            // 255: target not found

            if ($return_var == 0) {
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
                if (($i == 0) && ($supplied_bin_error == '')) {
                    $msg = '<b>Supplied binary found, but it exited with error code ' . $return_var . '. </b>';
                    switch ($return_var) {
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

        return 'No working cwebp binary found';

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
