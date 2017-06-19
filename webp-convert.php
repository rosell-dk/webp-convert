<?php

$filename = $_GET['file'];
$filename_abs = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $filename;

$dest = $_GET['destination-folder'] . $filename . '.webp';

$quality = intval($_GET['quality']);
$preferred_tools = explode(',', $_GET['preferred_tools']); 

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
      return FALSE;
    }

    // The appended "2>&1" is in order to get the output.
    // (thanks for your comment, Simon - http://php.net/manual/en/function.exec.php)

    $cmd = 'bin/cwebp-linux -q ' . $quality . ' -metadata all ' . $target . ' -o ' . $destination . ' 2>&1';
    exec('nice ' . $cmd, $output, $return_var);

    // if it failed, try without "nice"
    if ($return_var > 0) {
      exec($cmd, $output, $return_var);
    }

    // Return codes:  
    // 0: everything ok!
    // 127: binary cannot be found
    // 255: target not found    
    return ($return_var == 0);
  }
);

wepb_convert_add_tool(
  'imagewebp',
  function($target, $destination, $quality) {
    if(!function_exists(imagewebp)) {
      return FALSE;
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
    }

    if (!$image) {
      // Either imagecreatefromjpeg returned FALSE or unsupported extension
      return FALSE;
    }

    $success = imagewebp($image, $destination, $quality);
    imagedestroy($image);

    return $success;
  }
);


function die_with_msg($text) {
  header('Content-type: image/gif');
  $image = imagecreatetruecolor(620, 20);
  imagestring($image, 1, 5, 5,  $text, imagecolorallocate($image, 233, 214, 291));
//  echo imagewebp($image);
  echo imagegif($image);
  imagedestroy($image);
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

$success = FALSE;
foreach ($tools_order as $tool_name) {

  $convert_function = $tools[$pref_tool];
  $success = $convert_function($filename_abs, $dest, $quality);
  if ($success) {
    break;
  }
}

if (!$success) {
  die_with_msg('No tools could convert file:' . $filename_abs);
}


if (!file_exists($dest)) {
  die_with_msg('Failed saving image to path: ' . $dest);
}

// Serve the saved file
header('Content-type: image/webp');
readfile($dest);


/*  if (file_exists($dest)) {
    die_with_msg('The webp file already exists. I refuse to overwrite');

?>
