<?php


$filename = $_GET['file'];
$filename_abs = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $filename;

$dest = $_GET['destination-folder'] . $filename . '.webp';
//$dest = 'test.webp';


$quality = intval($_GET['quality']);


function cwebp_available() {
  if (!function_exists( 'exec' )) {
    return FALSE;
  }
  // TODO: Test if it is there and it works
  return TRUE;
}

function imagewebp_available() {
  return function_exists(imagewebp);
}



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

if (imagewebp_available()) {
  $ext = array_pop(explode('.', $filename));
  $image = '';
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $image = imagecreatefromjpeg($filename_abs);
      break;
    case 'png':
      $image = imagecreatefrompng($filename_abs);
      break;
  }

  if (!$image) {
    die_with_msg("Failed creating image: " . $filename);
    return;
  }

  imagewebp($image, $dest, $quality);
  imagedestroy($image);
}
else if (cwebp_available()) {
  exec('nice bin/cwebp-linux -q 80 -metadata all ' . $filename_abs . ' -o ' . $dest, $result);
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
