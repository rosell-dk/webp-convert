<?php
header('Content-type: image/webp');

// if this file is not placed in the root, you may need this line instead of the next:
// $filename = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['file'];
$filename = $_GET['file'];

$quality = intval($_GET['quality']);


function echoTextImage($text) {
  $image = imagecreatetruecolor(320, 20);
  imagestring($image, 1, 5, 5,  $text, imagecolorallocate($image, 233, 214, 291));
  echo imagewebp($image);
  imagedestroy($image);
}

if (!file_exists($filename)) {
  echoTextImage("File not found: " . $filename);
  return;
}


//if (!in_array('save', $_GET)) {
$ext = array_pop(explode('.', $filename));

switch ($ext) {
  case 'jpg':
  case 'jpeg':
    $image = imagecreatefromjpeg($filename);
    break;
  case 'png':
    $image = imagecreatefrompng($filename);
    break;
}


if (!$image) {
  echoTextImage('Failed creating image: ' . $_SERVER['QUERY_STRING']);
  return;
}

// Save the file, unless asked not to
if (!isset($_GET['no-save'])) {
  $dest = $_GET['destination-folder'] . $filename . '.webp';
  if (isset($_GET['destination-folder'])) {
    $folders = explode('/', $dest);
    array_pop($folders);
    $folder = implode($folders, '/');
    if (!file_exists($folder)) {
      mkdir($folder, 0755, TRUE);
    }
  }

/*
  This may be useful for testing htaccess (.htaccess should make sure not to call
  webp-convert.php when file already exists)

  if (file_exists($dest)) {
    echoTextImage('The webp file already exists. I refuse to overwrite');
    imagedestroy($image);
    return;
  }*/


  // I have experienced blank PNG images when setting quality
  if ($ext == 'png') {
    imagewebp($image, $dest);
  }
  else {
    imagewebp($image, $dest, $quality);
  }

  if (!file_exists($dest)) {
    echoTextImage('Failed saving image to path: ' . $dest);
    imagedestroy($image);
    return;
  }

  // Serve the saved file
  readfile($dest);
}
else {
  // Just create image, no saving
  if ($ext == 'png') {
    echo imagewebp($image);
  }
  else {
    echo imagewebp($image, NULL, $quality);
  }
}

// Free up memory
imagedestroy($image);
?>
