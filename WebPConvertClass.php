<?php


class WebPConvert {
  private static $tools = array();
  private static $tools_order = array();

  public static $serve_converted_image = TRUE;
  public static $serve_original_image_on_fail = TRUE;
  private static $preferred_tools_order = TRUE;

  public static $current_conversion_vars;

  // Little helper
  public static function logmsg($msg = '') {
    if (!WebPConvert::$serve_converted_image) {
      echo $msg . '<br>';
    }
  }

  // Critical fail - when we can't even serve the source as fallback
  private static function cfail($msg) {
//      echo $msg;
    header('Content-type: image/gif');

    // Prevent caching error message
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", FALSE);
    header("Pragma: no-cache");

    $image = imagecreatetruecolor(620, 200);
    imagestring($image, 1, 5, 5,  $msg, imagecolorallocate($image, 233, 214, 291));
//      echo imagewebp($image);
    echo imagegif($image);
    imagedestroy($image);
  }

  private static function fail($msg) {
    self::logmsg($msg);
    if (WebPConvert::$serve_converted_image && WebPConvert::$serve_original_image_on_fail) {
      $ext = array_pop(explode('.', WebPConvert::$current_conversion_vars['source']));
      switch ($ext) {
        case 'jpg':
        case 'jpeg':
          header('Content-type: image/jpeg');
          break;
        case 'png':
          header('Content-type: image/png');
          break;
      }
      readfile(WebPConvert::$current_conversion_vars['source']);
    }
  }

  public static function set_preferred_tools($preferred_tools) {
    // Remove preferred tools from order (we will add them again right away!)
    self::$tools_order = array_diff(self::$tools_order, $preferred_tools);

    // Add preferred tools to order
    foreach (array_reverse($preferred_tools) as $pref_tool) {
      if (self::$tools[$pref_tool]) {
        array_unshift(self::$tools_order, $pref_tool);
      }
    }
  }

  /**
    @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
    @param (string) $destination: Absolute path (no backslashes)
    @param (int) $quality (optional):  Quality of converted file (0-100)
    @param (bool) $strip_metadata (optional):  Whether or not to strip metadata. Default is to strip. Not all tools supports this
  */
  public static function convert($source, $destination, $quality = 85, $strip_metadata = TRUE) {

    self::logmsg('WebPConvert::convert() called');
    self::logmsg('- source: ' . $source);
    self::logmsg('- destination: ' . $destination);
    self::logmsg('- quality: ' . $quality);
    self::logmsg('- strip_metadata: ' . ($strip_metadata ? 'true' : 'false'));
    self::logmsg();

    WebPConvert::$current_conversion_vars = array();
    WebPConvert::$current_conversion_vars['source'] =  $source;
    WebPConvert::$current_conversion_vars['destination'] =  $destination;

    if (self::$serve_converted_image) {
      // If set to serve image, textual content will corrupt the image
      // Therefore, we prevent PHP from outputting error messages
      ini_set('display_errors','Off');
    }

    // "dirname", but which doesn't localization
    function strip_filename_from_path($path_with_filename) {
      $parts = explode('/', $path_with_filename);
      array_pop($parts);
      return implode('/', $parts);
    }

    // Test if file extension is valid
    $parts = explode('.', $source);
    $ext = array_pop($parts);

    if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
      self::cfail("Unsupported file extension: " . $ext);
      return;      
    }

    // Test if source file exists
    if (!file_exists($source)) {
      self::cfail("File not found: " . $source);
      return;
    }

    // Prepare destination folder
    $destination_folder = strip_filename_from_path($destination);

    if (!file_exists($destination_folder)) {

      self::logmsg('We need to create destination folder');

      // Find out which permissions to set.
      // We want same permissions as parent folder
      // But which parent? - the parent to the first missing folder
      // (TODO: what to do if this is out of basedir?)
      $parent_folders = explode('/', $destination_folder);
      $popped_folders = array();
      while (!(file_exists(implode('/', $parent_folders)))) {
        array_unshift($popped_folders, array_pop($parent_folders));
      }
      $closest_existing_folder = implode('/', $parent_folders);

      self::logmsg('Using permissions of closest existing folder (' . $closest_existing_folder . ')');
      $perms = fileperms($closest_existing_folder) & 000777;
      self::logmsg('Permissions are: 0' . decoct($perms));

      if (!mkdir($destination_folder, $perms, TRUE)) {
        self::fail('Failed creating folder:' . $folder);
        return;
      };
      self::logmsg('Folder created successfully');

      // alas, mkdir does not respect $perms. We have to chmod each created subfolder
      $path = $closest_existing_folder;
      foreach ($popped_folders as $subfolder) {
        $path .= '/' . $subfolder;
        self::logmsg('chmod 0' . decoct($perms) . ' ' . $path);
        chmod( $path, $perms );
      }

    }


    // Test if it will be possible to write file
    if (file_exists($destination)) {
      if (!is_writable($destination)) {
        self::fail('Cannot overwrite file: ' . $destination  . '. Check the file permissions.');
        return;
      }
    }
    else {
      if (!is_writable($destination_folder)) {
        self::fail('Cannot write file: ' . $destination  . '. Check the folder permissions.');
        return;
      }
    }

    // If there is already a converted file at destination, remove it
    // (actually it seems the current tools can handle that, but maybe not future tools)
    if (file_exists($destination)) {
      if (unlink($destination)) {
        self::logmsg('Destination file already exists... - removed');
      }
      else {
        self::logmsg('Destination file already exists. Could not remove it');
      }
    }

    self::logmsg('Order of tools to be tried: ' . implode(', ', self::$tools_order));

    $success = FALSE;
    foreach (self::$tools_order as $tool_name) {
      self::logmsg('<br>trying <b>' . $tool_name . '</b>');
      $convert_function = self::$tools[$tool_name];
      $result = $convert_function($source, $destination, $quality, $strip_metadata);
      if ($result === TRUE) {
        self::logmsg('success!');
        $success = TRUE;
        break;
      }
      else {
        self::logmsg($result);
      }
    }

    if (!$success) {
      self::fail('No tools could convert file: ' . $source);
      return;
    }


    if (!file_exists($destination)) {
      self::fail('Failed saving image to path: ' . $destination);
      return;
    }


    if (self::$serve_converted_image) {
      // Serve the saved file
      header('Content-type: image/webp');
//    Should we add Content-Length header?  header('Content-Length: ' . filesize($file));
      readfile($destination);
    }

  }

  public static function addTool($name, $convert_function) {
    self::$tools[$name] = $convert_function;
    self::$tools_order[] = $name;
  }
}

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
      $bin = 'bin/' . $binary[0];
      if (!file_exists($bin)) {
        $supplied_bin_error = 'bin file missing ( ' . __DIR__ . '/' . $bin . ')';
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

      // TODO: escape shell cmd (ewww_image_optimizer_escapeshellcmd)


      exec($cmd, $output, $return_var);
      // Return codes:  
      // 0: everything ok!
      // 127: binary cannot be found
      // 255: target not found

      if ($return_var == 0) {
        // Success!
        // cwebp however sets file permissions to 664. We want same as parent folder (but no executable bits)
        // cwebp also sets file owner. We want same as parent folder

		    // Set correct file permissions.
		    $stat = stat( dirname( $destination ) );
		    $perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		    chmod( $destination, $perms );

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


WebPConvert::addTool(
  'imagewebp',
  function($source, $destination, $quality, $strip_metadata = TRUE) {
    if(!function_exists('imagewebp')) {
      return 'imagewebp() is not available';
    }


    $parts = explode('.', $source);
    $ext = array_pop($parts);
    $image = '';


    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $image = imagecreatefromjpeg($source);
        break;
      case 'png':
        $image = imagecreatefrompng($source);
        break;
      default:
        return 'Unsupported file extension';
    }

    if (!$image) {
      // Either imagecreatefromjpeg or imagecreatefrompng returned FALSE
      return 'Either imagecreatefromjpeg or imagecreatefrompng failed';
    }

    $success = imagewebp($image, $destination, $quality);


    // This is a hack solves bug with imagewebp
    // - Got it here: https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files
    if (filesize($source) % 2 == 1) {
      file_put_contents($source, "\0", FILE_APPEND);
    }

    // Hm... sometimes I get completely transparent images, even with the hack above. Help, anybody?

    imagedestroy($image);
    if ($success) {
      return TRUE;
    }
    else {
      return 'imagewebp() call failed';
    }
  }
);


?>
