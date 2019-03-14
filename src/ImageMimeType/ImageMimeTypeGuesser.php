<?php

namespace WebPConvert\ImageMimeType;

use \WebPConvert\ImageMimeType\Detectors\Stack;

class ImageMimeTypeGuesser
{


    /**
     *  Try to detect mime type of image using "exif_imagetype"
     *  returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public static function detectMimeTypeImage($filePath)
    {
        // Result of the discussion here:
        // https://github.com/rosell-dk/webp-convert/issues/98

        return Stack::detect($filePath);
    }

    /**
     *  Should only be used as a fallback
     *  It is very unreliable!
     */
    public static function guessMimeTypeFromExtension($filePath)
    {
        // fallback to using pathinfo
        // is this a security risk? - By setting file extension to "jpg", one can
        // lure our library into trying to convert a file, which isn't a jpg.
        // hm, seems very unlikely, though not unthinkable that one of the converters could be exploited
        $fileExtension = self::getExtension($filePath);
        if ($fileExtension == 'jpg') {
            $fileExtension = 'jpeg';
        }
        return 'image/' . $fileExtension;
    }

    public static function guessMimeTypeImage($filePath)
    {
        $detectionResult = self::detectMimeTypeImage($filePath);
        if (!is_null($detectionResult)) {
            return $detectionResult;
        }

        // fall back to the unreliable 
        return self::guessMimeTypeFromExtension($filePath);
    }


}
