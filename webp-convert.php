<?php


$filename = $_GET['file'];
$filename_abs = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $filename;

$dest = $_GET['destination-folder'] . $filename . '.webp';

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
  function($target, $destination, $quality) {
    if (!function_exists( 'exec' )) {
      return 'exec() is not enabled';
    }

    // Select binary
    $bin = array(
      'WinNT' => 'cwebp.exe',
      'Darwin' => 'cwebp-mac12',
      'SunOS' => 'cwebp-sol',
      'FreeBSD' => 'cwebp-fbsd',
      'Linux' => 'cwebp-linux'
    )[PHP_OS];
    if (!$bin) {
      // The binary is not included per standard
      // ...but perhaps the user has put his own custom compilation to the dir?
      $bin = 'cwebp-custom';
      if (!file_exists('bin/' . $bin)) {
        return 'No bin file found for current OS (' . PHP_OS . '), and no custom bin found at ' . __DIR__ . '/' . $bin;
      }
    }
    $bin = 'bin/' . $bin;

    // TODO: md5 check before exec()

    // Check the version
    //   (the appended "2>&1" is in order to get the output - thanks for your comment, Simon
    //    @ http://php.net/manual/en/function.exec.php)
		exec( "$bin -version 2>&1", $version );
    if (empty($version)) {
      return 'Failed getting version';
    }
    if (!preg_match( '/0.\d.\d/', $version[0] ) ) {
			return 'Unexpected version format';
		}    

    // Try with nice        
    $cmd = 'bin/cwebp-linux -q ' . $quality . ' -metadata all ' . $target . ' -o ' . $destination . ' 2>&1';
    exec('nice ' . $cmd, $output, $return_var);

    // if it failed, try without "nice"
    if ($return_var > 0) {
      exec($cmd, $output, $return_var);
    }
    if ($return_var > 0) {
      // Return codes:  
      // 0: everything ok!
      // 127: binary cannot be found
      // 255: target not found
      $msg = 'binary exited with error code ' . $return_var . '. ';
      switch ($return_var) {
        case 127:
          $msg .= 'This probably means that the binary was not found. ';
          break;
        case 255:
          $msg .= 'This probably means that the target was not found. ';
          break;
      }
      $msg .= 'Output was: ' . print_r($output, TRUE);
      return $msg;
    }
    else {
      return TRUE;
    }
  }
);

wepb_convert_add_tool(
  'imagewebp',
  function($target, $destination, $quality) {
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

if (!file_exists($filename_abs)) {
  die_with_msg("File not found: " . $filename_abs);
}

// Prepare destination folder
if (!isset($_GET['no-save'])) {
  $dest = $_GET['destination-folder'] . $filename . '.webp';
  if (isset($_GET['destination-folder'])) {
    $folders = explode('/', $dest);
    array_pop($folders);
    $folder = implode($folders, '/');
    if (!file_exists($folder)) {
      if (!mkdir($folder, 0755, TRUE)) {
        die_with_msg('Failed creating folder:' . $folder);
      };
    }
  }
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
