<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\ImageMimeType;

use \WebPConvert\ImageMimeType\Detectors\ExifImageType;
use \PHPUnit\Framework\TestCase;

class ImageMimeTypeGuesserTest extends TestCase
{

    /* Not really a test - just a helper */
    public function testDetector($detectorClassName = null, $filePath = null, $expectedMime = null)
    {
        if (is_null($detectorClassName)) {
            return;
        }

        $mime = call_user_func(array("\\WebPConvert\\ImageMimeType\\Detectors\\" . $detectorClassName, 'detect'), $filePath);

        if (is_null($mime)) {
            // this is ok
            return;
        }

        if ($mime === false) {
            // also ok
            return;
        }

        $this->assertEquals($mime, $expectedMime);

    }

    /* Not really a test - just a helper */
    public function testAllDetectors($fileName = null, $expectedMime = null)
    {
        if (is_null($fileName)) {
            return;
        }

        $detectors = [
            'ExifImageType',
            'GetImageSize',
            'FInfo',
            'MimeContentType',
            'Stack'
        ];

        foreach ($detectors as $className) {
            $this->testDetector($className, __DIR__ . '/../images/' . $fileName, $expectedMime);
        }
    }

    public function testGuessMimeType()
    {

        $this->testAllDetectors('test.jpg', 'image/jpeg');
        $this->testAllDetectors('test.png', 'image/png');
        $this->testAllDetectors('png-without-extension', 'image/png');
        $this->testAllDetectors('png-with-jpeg-extension.jpg', 'image/png');
        $this->testAllDetectors('not-true-color.png', 'image/png');
        $this->testAllDetectors('with space.jpg', 'image/jpeg');

        $this->testAllDetectors('non-existing', false);

    }
}
