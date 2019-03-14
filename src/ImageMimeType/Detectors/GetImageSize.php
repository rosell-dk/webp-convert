<?php

namespace WebPConvert\ImageMimeType\Detectors;

class GetImageSize extends BaseDetector
{

    /**
     *  Try to detect mime type of image using "getimagesize"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public function doDetect($filePath)
    {
        // getimagesize is slower than exif_imagetype
        // It may not return "mime". In that case we can rely on that the file is not an image (and return false)
        if (function_exists('getimagesize')) {
            try {
                $imageSize = getimagesize($filePath);
                return (isset($imageSize['mime']) ? $imageSize['mime'] : false);
            } catch (\Exception $e) {
                // well well, don't let this stop us either
            }
        }
    }
}
