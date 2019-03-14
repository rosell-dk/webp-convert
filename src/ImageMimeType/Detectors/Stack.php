<?php

namespace WebPConvert\ImageMimeType\Detectors;

class Stack extends BaseDetector
{

    /**
     *  Try to detect mime type of image using all available detectors
     *  returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public function doDetect($filePath)
    {
        $detectors = [
            'ExifImageType',
            'GetImageSize',
            'FInfo',
            'MimeContentType'
        ];

        foreach ($detectors as $className) {
            $result = call_user_func(array("\\WebPConvert\\ImageMimeType\\Detectors\\" . $className, 'detect'), $filePath);
            if (!is_null($result)) {
                return $result;
            }
        }

        return;     // undetermined
    }

}
