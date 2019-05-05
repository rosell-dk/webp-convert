<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Imagick;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Convert\Converters\Imagick
 * @covers WebPConvert\Convert\Converters\Imagick
 */
class ImagickTest extends TestCase
{

    public static $imageDir = __DIR__ . '/../../images/';

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Imagick');
    }

    /**
     * @coversNothing
     */
    public function testQueryFormats()
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped(
              'The imagick extension is not available.'
            );
            return;
        }
        //if (!class_exists('\\Imagick')) {}

        $im = new \Imagick();

        $this->assertEquals(1, count($im->queryFormats('JPEG')));
        $this->assertGreaterThan(2, count($im->queryFormats('*')));
        $this->assertGreaterThan(2, count($im->queryFormats()));
        $this->assertEquals(count($im->queryFormats('*')), count($im->queryFormats()));
    }

    /**
     * @coversNothing
     */
    public function testThatImagickFunctionsUsedDoesNotThrow()
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped(
              'The imagick extension is not available.'
            );
            return;
        }
        $im = new \Imagick(self::$imageDir . '/test.jpg');
        $im->setImageFormat('JPEG');
        $im->stripImage();
        $im->setImageCompressionQuality(100);
        $imageBlob = $im->getImageBlob();

        $this->addToAssertionCount(1);
    }
}
