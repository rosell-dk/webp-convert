<?php

namespace WebPConvert\ImageMimeType\Detectors;

use \WebPConvert\ImageMimeType\Detectors\BaseDetector;

class ExifImageType extends BaseDetector
{

    /**
     *  Try to detect mime type of image using "exif_imagetype"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public function doDetect($filePath)
    {
        // exif_imagetype is fast, however not available on all systems,
        // It may return false. In that case we can rely on that the file is not an image (and return false)
        if (function_exists('exif_imagetype')) {
            try {
                $imageType = exif_imagetype($filePath);
                return ($imageType ? image_type_to_mime_type($imageType) : false);
            } catch (\Exception $e) {
                // well well, don't let this stop us
            }
        }
        return;
    }
}
