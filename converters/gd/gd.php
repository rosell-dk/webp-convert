<?php 

function webpconvert_gd($source, $destination, $quality, $strip_metadata) {
  if (!extension_loaded('gd')) {
    return 'This implementation requires the GD extension, which you do not have';
  }
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

