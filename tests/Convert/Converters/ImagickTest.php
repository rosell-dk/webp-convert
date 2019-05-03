<?php

namespace WebPConvert\Tests\Convert\Converters;

use PHPUnit\Framework\TestCase;

class ImagickTest extends TestCase
{

    public static $imageDir = __DIR__ . '/../../images/';

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Imagick');
    }

    public function testQueryFormats()
    {
        if (!extension_loaded('imagick')) {
            return;
        }

        $im = new \Imagick();

        $this->assertEquals(1, count($im->queryFormats('JPEG')));
        $this->assertGreaterThan(2, count($im->queryFormats('*')));
        $this->assertGreaterThan(2, count($im->queryFormats()));
        $this->assertEquals(count($im->queryFormats('*')), count($im->queryFormats()));
    }

    public function testThatImagickFunctionsUsedDoesNotThrow()
    {
        $im = new \Imagick(self::$imageDir . '/test.jpg');
        $im->setImageFormat('JPEG');
        $im->stripImage();
        $im->setImageCompressionQuality(100);
        $imageBlob = $im->getImageBlob();

        $this->addToAssertionCount(1);
    }
}
