<?php


/*
URL parameters:

source:
Path to source file. Can be absolute or relative (relative to document root). If it starts with "/", it is considered an absolute path.

destination (optional): (TODO)
Path to destination file. Can be absolute or relative (relative to document root). You can choose not to specify destination. In that case, the path will be created based upon source, destination-root and absrel settings. If all these are blank, the destination will be same folder as source, and the filename will have ".webp" appended to it (ie image.jpeg.webp)

destination-root (optional):
Path of destination (relative to document root) or an absolute path. If not supplied, then the converted file will be placed in same folder as the target. Double-dots are allowed, ie "../../webp-cache/images/2017"

quality:
The quality of the generated WebP image, 0-100.

preferred_tools (optional):
Set the priority of the tools, that is, the order to try the tools in. You do not have to specify all tools. The tools you specify will move to the top of the list. The script will always try all tools before giving up.
Comma-separated list.
Allowed values: "cwebp", "imagewebp"
Default order is: cwebp, imagewebp

absrel (optional):
Might be relevant in rare occasions where you cannot pass all of the relative path. 

debug (optional):
If set (if "&debug" is appended to the URL), the script will produce text output instead of an image.
*/

$debug = isset($_GET['debug']);

function logmsg($msg) {
  global $debug;
  if ($debug) {
    echo $msg . '<br>';   
  }
}


// Canonicalize a path by resolving '../' and './'
// Got it from a comment here: http://php.net/manual/en/function.realpath.php
// But modified it (it could not handle ./../)
function canonicalize($path) {
  $parts = explode('/', $path);

  // Remove parts containing just '.' (and the empty holes afterwards)
  $parts = array_values(array_filter($parts, function($var) {
    return ($var != '.');
  }));

  // Remove parts containing '..' and the preceding
  $keys = array_keys($parts, '..');
  foreach($keys as $keypos => $key) {
    array_splice($parts, $key - ($keypos * 2 + 1), 2);
  }
  return implode('/', $parts);
}

// We do not operate with backslashes here. Windows is a big boy now, 
// it can handle forward slashes
function replace_backslashes($str) {
  return str_replace('\\', '/', $str);
}

function remove_double_slash($str) {
  return preg_replace('/\/\//', '/', $str);
}

/* Get relative path between one dir and the other.
   ie
      from:   /var/www/wordpress/wp-content/plugins/webp-express
      to:     /var/www/wordpress/wp-content/uploads
      result: ../../uploads/     
   */
function get_rel_dir($from_dir, $to_dir) {
  $from_dir_parts = explode('/', str_replace( '\\', '/', $from_dir ));
  $to_dir_parts = explode('/', str_replace( '\\', '/', $to_dir ));
  $i = 0;
  while (($i < count($from_dir_parts)) && ($i < count($to_dir_parts)) && ($from_dir_parts[$i] == $to_dir_parts[$i])) {
    $i++;
  }
  $rel = "";
  for ($j = $i; $j < count($from_dir_parts); $j++) {
    $rel .= "../";
  } 

  for ($j = $i; $j < count($to_dir_parts); $j++) {
    $rel .= $to_dir_parts[$j];
    if ($j < count($to_dir_parts)-1) {
      $rel .= '/';
    }
  }
  return $rel;
}

// Strip filename from path
function get_folder($path_with_filename) {
  $parts = explode('/', $path_with_filename);
  array_pop($parts);
  return implode('/', $parts);
}

$root = $_SERVER['DOCUMENT_ROOT'];
logmsg('Document root: "' . $root . '"');
if (isset($_GET['absrel'])) {
  $root .= '/' . $_GET['absrel'];
  logmsg('absrel was supplied, our root is: "' . $root . '"');
}
logmsg();

$source_arg = $_GET['source'];
$source = replace_backslashes($source_arg);
logmsg('<i>source file:</i>');
if (!(substr($source, 0, 1) == '/')) {
  // Make source an absolute path
  $source_rel = $source;
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Relative path was given: "' . $source_rel . '"');
  $source_abs = canonicalize(remove_double_slash($root . '/' . $source_rel));
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Absolute path (calculated): "' . $source_abs . '"');
//  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;This will be relative to DOCUMENT_ROOT: ' . $_SERVER['DOCUMENT_ROOT']);
}
else {
//  logmsg('<i>source file:</i> absolute path: ' . $source);
  $source_abs = $source;
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Absolute path was given: "' . $source_abs . '"');
  $source_rel = get_rel_dir($root, $source_abs);
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Relative path (calculated): "' . $source_rel . '"');
  if (preg_match('/\.\.\//', $source_rel)) {
    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;<b>Note</b>: path is outside document root. You are allowed to, but its unusual setup. When used to calculate destination folder, the "../"s will be removed');
  }
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;(relative path shall be used later. destination folder = destination root + relative path of source file)');
//  die_with_msg('absolute path for source not supported yet');
}
logmsg();


logmsg('<i>destination root:</i>');

if ((isset($_GET['destination-root'])) && ((substr($dest, 0, 1) == '/'))) {
  die_with_msg('absolute path for destination not supported yet');  
}
else {
  if (!isset($_GET['destination-root'])) {
    $destination_root = '';
//    $dest = dirname($source);

//    $destination_root_rel = dirname($source);
    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;No root was specified.');
  }
  else {
    $destination_root = replace_backslashes($_GET['destination-root']);
    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Relative path was given: "' . $destination_root . '"');
  }

  if ($destination_root == '') {
    $destination_root = '.';
  }
//    if (!(substr($dest, 0, 1) == '/')) {
//    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Remember this is the relative path to the script.');
//    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Remember this is the relative to the DOCUMENT_ROOT.');
  $destination_root_rel = $destination_root;

  $destination_root_abs = canonicalize(remove_double_slash($root . '/' . $destination_root_rel));
  logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Absolute path (calculated): "' . $destination_root_abs . '"');

  // Calculate relative to DOCUMENT_ROOT

//    logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Relative path given.');

//    logmsg('<i>destination folder:</i> Relative to script. ' . $dest);

}


logmsg();
logmsg('<i>destination file:</i>');
/*logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Calculated by appending relative path of source file to destination root');
$destination_file_rel = canonicalize(remove_double_slash($destination_root . '/' . $source_rel . '.webp'));
logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Relative (to script): "' . $destination_file_rel . '"');
*/
logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Calculated by appending relative path of source file to destination root (absolute path)');
$destination_file_abs = canonicalize(remove_double_slash($destination_root_abs . '/' . preg_replace('/\.\.\//', '', $source_rel) . '.webp'));
logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Absolute path: "' . $destination_file_abs . '"');

logmsg();
logmsg('<i>destination folder:</i>');
$folders = explode('/', $destination_file_abs);
array_pop($folders);
$destination_folder_abs = implode($folders, '/');
logmsg('&nbsp;&nbsp;&nbsp;&nbsp;Absolute path: "' . $destination_folder_abs . '"');

logmsg();

$quality = intval($_GET['quality']);
$preferred_tools = explode(',', $_GET['preferred_tools']); 
//die();

//logmsg($source);
//logmsg(realpath('.'));
//logmsg(realpath('..'));
//logmsg(canonicalize('images/..////crazyfolder/logo2.jpg'));
//logmsg(canonicalize('images/../crazyfolder/logo2.jpg'));
//logmsg(realpath('images/../images/crazyfolder/logo2.jpg'));

/*logmsg($_GET['source']);
logmsg(realpath($_GET['source']));
logmsg(realpath('/tmp//'));*/


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
$ext = array_pop(explode('.', $source_rel));
if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
  die_with_msg("Unsupported file extension: " . $ext);
}

// Test if target file exists
if (!file_exists($source_abs)) {
  die_with_msg("File not found: " . $source_abs);
}

// Prepare destination folder



// Prepare destination folder


if (!file_exists($destination_folder_abs)) {

  logmsg('We need to create destination folder');

  // Find out which permissions to set.
  // We want same permissions as parent folder
  // But which parent? - the parent to the first missing folder
  // (TODO: what to do if this is out of basedir?)
  $parent_folders = explode('/', $destination_folder_abs);
  $popped_folders = array();
  while (!(file_exists(implode('/', $parent_folders)))) {
    array_unshift($popped_folders, array_pop($parent_folders));
  }
  $closest_existing_folder = implode('/', $parent_folders);

  logmsg('Using permissions of closest existing folder (' . $closest_existing_folder . ')');
  $perms = fileperms($closest_existing_folder) & 000777;
  logmsg('Permissions are: 0' . decoct($perms));

  if (!mkdir($destination_folder_abs, $perms, TRUE)) {
    die_with_msg('Failed creating folder:' . $folder);
  };
  logmsg('Folder created successfully');

  // alas, mkdir does not respect $perms. We have to chmod each created subfolder
  $path = $closest_existing_folder;
  foreach ($popped_folders as $subfolder) {
    $path .= '/' . $subfolder;
    logmsg('chmod 0' . decoct($perms) . ' ' . $path);
    chmod( $path, $perms );
  }

}




// Prepare destination folder
/*
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
}*/

// Test if it will be possible to write file
if (file_exists($destination_file_abs)) {
  if (!is_writable($destination_file_abs)) {
    die_with_msg('Cannot overwrite file: ' . $destination_file_abs  . '. Check the file permissions.');
  }
}
else {
  if (!is_writable($destination_folder_abs)) {
    die_with_msg('Cannot write file: ' . $destination_file_abs  . '. Check the folder permissions.');
  }
}

// Remove file if it exists
// (actually it seems the current tools can handle that, but maybe not future tools)
if (file_exists($destination_file_abs)) {
  if (unlink($destination_file_abs)) {
    logmsg('Destination file already exists... - removed');
  }
  else {
    logmsg('Destination file already exists. Could not remove it');
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
  $result = $convert_function($source_abs, $destination_file_abs, $quality);
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
  die_with_msg('No tools could convert file: ' . $source_abs);
}


if (!file_exists($destination_file_abs)) {
  die_with_msg('Failed saving image to path: ' . $destination_file_abs);
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
