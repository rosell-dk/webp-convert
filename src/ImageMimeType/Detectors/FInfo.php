<?php

namespace WebPConvert\ImageMimeType\Detectors;

class FInfo extends BaseDetector
{

    /**
     *  Try to detect mime type of image using finfo
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public function doDetect($filePath)
    {

        if (class_exists('finfo')) {
            // phpcs:ignore PHPCompatibility.PHP.NewClasses.finfoFound
            $finfo = new \finfo(FILEINFO_MIME);
            $mime = explode('; ', $finfo->file($filePath));
            $result = $mime[0];

            if (strpos($result, 'image/') === 0) {
                return $result;
            } else {
                return false;
            }

            return $type;
        }
    }
}
