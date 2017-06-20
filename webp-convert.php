<?php


$filename = $_GET['file'];

// TODO: Test if $filename starts with '/'

$filename_abs = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $filename;

$dest = preg_replace('/\/\//', '/', $_GET['destination-folder'] . '/' . $filename . '.webp');

$quality = intval($_GET['quality']);
$preferred_tools = explode(',', $_GET['preferred_tools']); 
$debug = isset($_GET['debug']);

function logmsg($msg) {
  global $debug;
  if ($debug) {
    echo $msg . '<br>';
    
  }
}


// actually comma is "unsafe" in URLs according to RFC.
// - See speedplanes comment here: https://stackoverflow.com/questions/198606/can-i-use-commas-in-a-url

$tools = array();
$tools_order = array();

function wepb_convert_add_tool($name, $convert_function) {
  global $tools;
  $tools[$name] = $convert_function;

  global $tools_order;
  $tools_order[] = $name;
}


wepb_convert_add_tool(
  'cwebp',
  function($target, $destination, $quality, $copy_metadata = TRUE) {
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
      logmsg('Not able to use supplied bin. ' . $supplied_bin_error);
    }

/*
			case 'image/jpeg':
				$quality = (int) apply_filters( 'jpeg_quality', 82, 'image/webp' );
				exec( "$nice " . $tool . " -q $quality -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
				break;
			case 'image/png':
				exec( "$nice " . $tool . " -lossless -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );

*/

    function esc_whitespace($string) {
    	return ( preg_replace( '/\s/', '\\ ', $string ) );
    }

    // Build options string
    $options = '-q ' . $quality;
    $options .= ($copy_metadata ? ' -metadata all ' : '-metadata none');
    $ext = array_pop(explode('.', $filename));
    if ($ext == 'png') {
      $options .= ' -lossless';
    }
    $options .= ' ' . esc_whitespace($target) . ' -o ' . esc_whitespace($destination) . ' 2>&1';

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
    logmsg('parameters:' . $options);

    // Try all paths
    $success = FALSE;
    foreach ($paths_to_test as $i => $bin) {
      logmsg('trying to execute binary: ' . $bin);

      $cmd = $nice . $bin . ' ' . $options;

      // TODO: escape shell cmd (ewww_image_optimizer_escapeshellcmd)


      exec($cmd, $output, $return_var);
      // Return codes:  
      // 0: everything ok!
      // 127: binary cannot be found
      // 255: target not found

      if ($return_var == 0) {
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
          logmsg($msg);
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

wepb_convert_add_tool(
  'imagewebp',
  function($target, $destination, $quality, $copy_metadata = TRUE) {
    if(!function_exists(imagewebp)) {
      return 'imagewebp() is not available';
    }
    $ext = array_pop(explode('.', $target));
    $image = '';
    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $image = imagecreatefromjpeg($target);
        break;
      case 'png':
        $image = imagecreatefrompng($target);
        break;
      default:
        return 'Unsupported file extension';
    }

    if (!$image) {
      // Either imagecreatefromjpeg or imagecreatefrompng returned FALSE
      return 'Either imagecreatefromjpeg or imagecreatefrompng failed';
    }

    $success = imagewebp($image, $destination, $quality);
    imagedestroy($image);

    return $success;
  }
);


function die_with_msg($text) {
  global $debug;
  if ($debug) {
    echo '<br><b>' . $text . '</b><br>';
  }
  else {
    header('Content-type: image/gif');
    $image = imagecreatetruecolor(620, 20);
    imagestring($image, 1, 5, 5,  $text, imagecolorallocate($image, 233, 214, 291));
  //  echo imagewebp($image);
    echo imagegif($image);
    imagedestroy($image);
  }
  die();
}

// Test if file extension is valid
$ext = array_pop(explode('.', $filename));
if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
  die_with_msg("Unsupported file extension: " . $ext);
}

// Test if target file exists
if (!file_exists($filename_abs)) {
  die_with_msg("File not found: " . $filename_abs);
}

// Prepare destination folder
if (isset($_GET['destination-folder'])) {
  $folders = explode('/', $dest);
  array_pop($folders);
  $folder = implode($folders, '/');
  logmsg('dest:' . $folder);
  if (!file_exists($folder)) {
    if (!mkdir($folder, 0755, TRUE)) {
      die_with_msg('Failed creating folder:' . $folder);
    };
  }
}

// Test if it will be possible to write file
if (!is_writable($dest)) {
  die_with_msg('Cannot save file to: ' . $dest  . '. Check the file permissions.');
}

// Remove preffered tools from order (we will add them soon!)
$tools_order = array_diff($tools_order, $preferred_tools);

// Add preffered tools to order
foreach ($preferred_tools as $pref_tool) {
  if ($tools[$pref_tool]) {
    array_unshift($tools_order, $pref_tool);
  }
}

logmsg('Order of tools to be tried: ' . implode(', ', $tools_order));

$success = FALSE;
foreach ($tools_order as $tool_name) {
  logmsg('<br>trying <b>' . $tool_name . '</b>');
  $convert_function = $tools[$tool_name];
  $result = $convert_function($filename_abs, $dest, $quality);
  if ($result === TRUE) {
    logmsg('success!');
    $success = TRUE;
    break;
  }
  else {
    logmsg($result);
  }
}

if (!$success) {
  die_with_msg('No tools could convert file: ' . $filename_abs);
}


if (!file_exists($dest)) {
  die_with_msg('Failed saving image to path: ' . $dest);
}

if ($debug) {
}
else {
  // Serve the saved file
  header('Content-type: image/webp');
  readfile($dest);
}

/*  if (file_exists($dest)) {
    die_with_msg('The webp file already exists. I refuse to overwrite');

?>
