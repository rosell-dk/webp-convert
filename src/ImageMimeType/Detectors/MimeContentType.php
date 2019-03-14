<?php

namespace WebPConvert\ImageMimeType\Detectors;

class MimeContentType extends BaseDetector
{

    /**
     *  Try to detect mime type of image using "mime_content_type"
     *
     *  Like all detectors, it returns:
     *  - null  (if it cannot be determined)
     *  - false (if it can be determined that this is not an image)
     *  - mime  (if it is in fact an image, and type could be determined)
     *  @return  mime | null | false.
     */
    public function doDetect($filePath)
    {
        // mime_content_type supposedly used to be deprecated, but it seems it isn't anymore
        // it may return false on failure.
        if (function_exists('mime_content_type')) {
            try {
                $result = mime_content_type($filePath);
                if ($result !== false) {
                    if (strpos($result, 'image/') === 0) {
                        return $result;
                    } else {
                        return false;
                    }
                }
            } catch (\Exception $e) {
        		// we are unstoppable!
        	}
        }
    }

}
