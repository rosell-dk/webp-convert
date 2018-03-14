<?php

function webpconvert_imagick($source, $destination, $quality, $strip_metadata)
{
    if (!extension_loaded('imagick')) {
        return 'This implementation requires the imagick extension, which you do not have';
    }

    try {
        $im = new Imagick($source);
    } catch (Exception $e) {
        return 'imagick is installed but cannot handle source file. Error message: ' . $e->getMessage();
    }

    try {
        $im->setImageFormat("WEBP");
    } catch (Exception $e) {
        return 'imagick is installed, but not compiled with webp support';
        // return $e->getMessage();
        // echo 'Available formats:' . implode(', ', $im->queryFormats());
    }

    // About webp options:
    // http://www.imagemagick.org/script/webp.php
    // https://stackoverflow.com/questions/37711492/imagemagick-specific-webp-calls-in-php


    if (defined("WEBPCONVERT_IMAGICK_METHOD")) {
        $im->setOption('webp:method', WEBPCONVERT_IMAGICK_METHOD);
    } else {
        $im->setOption('webp:method', '6');
    }

    if (defined("WEBPCONVERT_IMAGICK_LOW_MEMORY")) {
        $im->setOption('webp:low-memory', (
            WEBPCONVERT_IMAGICK_LOW_MEMORY
            ? 'true'
            : 'false'
        ));
    } else {
        $im->setOption('webp:low-memory', 'true');
    }

    $im->setImageCompressionQuality($quality);

    $parts = explode('.', $source);
    $ext = array_pop($parts);
    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
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
        return true;
    } else {
        return 'writeImage("' . $destination . '") failed';
    }
}
