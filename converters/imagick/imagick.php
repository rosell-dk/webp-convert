<?php 

function webpconvert_imagick($source, $destination, $quality, $strip_metadata) {
  if (!extension_loaded('imagick')) {
    return 'This implementation requires the imagick extension, which you do not have';
  }

  try {
    $im = new Imagick($source);
  }
  catch (Exception $e) {
    return 'imagick is installed but cannot handle source file. Error message: ' . $e->getMessage();
  }
  try {
    $im->setImageFormat( "WEBP" );
  }
  catch (Exception $e) {
    return 'imagick is installed, but not compiled with webp support';
//    return $e->getMessage();
//    echo 'Available formats:' . implode(', ', $im->queryFormats());
  }

  // About webp options:
  // http://www.imagemagick.org/script/webp.php
  // https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php

  $im->setOption('webp:low-memory', 'true');
  $im->setImageCompressionQuality($quality);

  $parts = explode('.', $source);
  $ext = array_pop($parts);
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      // the compression method to use. It controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. Default value is 4. When higher values are utilized, the encoder spends more time inspecting additional encoding possibilities and decide on the quality gain. Lower value might result in faster processing time at the expense of larger file size and lower compression quality.
      $im->setOption('webp:method', '6'); 
      break;
    case 'png':
      $im->setOption('webp:lossless', 'true');
      break;
    default:
      return 'Unsupported file extension';
  }

  $success = $im->writeImage($destination);
  // This would also works: 
  // $success = file_put_contents($destination, $im); 

  if ($success) {
    return TRUE;
  }
  else {
    return 'writeImage("' . $destination . '") failed';
  }
}

