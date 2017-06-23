<?php
WebPConvert::addTool(
  'cwebp',
  function($source, $destination, $quality, $strip_metadata = TRUE) {

    if (!function_exists( 'exec' )) {
      return 'exec() is not enabled';
    }

    // System paths to look for cwebp
    // Supplied bin will be prepended array, but only if it passes some tests...
    $paths_to_test = array(
      '/usr/bin/cwebp',
      '/usr/local/bin/cwebp',
      '/usr/gnu/bin/cwebp',
      '/usr/syno/bin/cwebp'
    );
    
    // Select binary
    $binary = array(
      'WinNT' => array( 'cwebp.exe', '49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873'),
      'Darwin' => array( 'cwebp-mac12', 'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379'),
      'SunOS' => array( 'cwebp-sol', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f'),
      'FreeBSD' => array( 'cwebp-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573'),
      'Linux' => array( 'cwebp-linux', '43ca351e8f5d457b898c587151ebe3d8f6cce8dcfb7de44f6cb70148a31a68bc')
    )[PHP_OS];

    $supplied_bin_error = '';
    if (!$binary) {
      $supplied_bin_error = 'We do not have a supplied bin for your OS (' . PHP_OS . ')';
    }
    else {
      $bin = __DIR__ . '/bin/' . $binary[0];
      if (!file_exists($bin)) {
        $supplied_bin_error = 'bin file missing ( ' . $bin . ')';
      }
      else {
        // Check Checksum
        $binary_sum = hash_file( 'sha256', $bin );
        if ($binary_sum != $binary[1]) {
          $supplied_bin_error = 'sha256 sum of supplied binary is invalid!';
        }

        // Also check mimetype?
        //ewww_image_optimizer_mimetype( $binary_path, 'b' )

      }
    }
    if ($supplied_bin_error == '') {
      array_unshift($paths_to_test, $bin);
    }
    else {
      WebPConvert::logmsg('Not able to use supplied bin. ' . $supplied_bin_error);
    }

    function esc_whitespace($string) {
    	return ( preg_replace( '/\s/', '\\ ', $string ) );
    }

    // Build options string
    $options = '-q ' . $quality;
    $options .= ($strip_metadata ? ' -metadata none' : '-metadata all');
    // comma separated list of metadata to copy from the input to the output if present.
    // Valid values: all, none (default), exif, icc, xmp

    $parts = explode('.', $source);
    $ext = array_pop($parts);
    if ($ext == 'png') {
      $options .= ' -lossless';
    }
    //$options .= ' -low_memory';
    
    // $options .= ' -quiet';
    $options .= ' ' . esc_whitespace($source) . ' -o ' . esc_whitespace($destination) . ' 2>&1';

    // Test if "nice" is available
    // ($nice will be set to "nice ", if it is)
    $nice = '';
		exec( "nice 2>&1", $nice_output );
    if (is_array($nice_output) && isset($nice_output[0]) ) {
      if (preg_match( '/usage/', $nice_output[0]) || (preg_match( '/^\d+$/', $nice_output[0] ))) {
        // Nice is available. 
        // We run with default niceness (+10)
        // https://www.lifewire.com/uses-of-commands-nice-renice-2201087
        // https://www.computerhope.com/unix/unice.htm
        $nice = 'nice ';
      }
    }
    WebPConvert::logmsg('parameters:' . $options);

    // Try all paths
    $success = FALSE;
    foreach ($paths_to_test as $i => $bin) {
      WebPConvert::logmsg('trying to execute binary: ' . $bin);

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
		    $stat = stat( dirname( $destination ) );
		    $perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		    chmod( $destination, $perms );

        // TODO cwebp also appears to set file owner. We want same owner as parent folder

        return TRUE;
      }
      else {
        // If supplied bin failed, log some information
        if (($i == 0) && ($supplied_bin_error == '')) {
          $msg = 'Supplied binary found, but it exited with error code ' . $return_var . '. ';
          switch ($return_var) {
            case 127:
              $msg .= 'This probably means that the binary was not found. ';
              break;
            case 255:
              $msg .= 'This probably means that the target was not found. ';
              break;
          }
          $msg .= 'Output was: ' . print_r($output, TRUE);
          WebPConvert::logmsg($msg);
        }
      }
    }
    // 
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
		}*/
  }
);



