<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\GraphicsMagick;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Loggers\BufferLogger;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Convert\Converters\GraphicsMagick
 * @covers WebPConvert\Convert\Converters\GraphicsMagick
 */
class GraphicsMagickTest extends TestCase
{

    public static function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public static function getImagePath($image)
    {
        return self::getImageFolder() . '/' . $image;
    }

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'GraphicsMagick');
    }

    /**
     * Try converting.
     * It is ok if converter is not operational.
     * It is not ok if converter thinks it is operational, but fails
     *
     * @return $ok
     * @throws ConversionFailedException if convert throws it
     */
    private static function tryThis($test, $source, $options)
    {
        $bufferLogger = new BufferLogger();

        try {
            GraphicsMagick::convert($source, $source . '.webp', $options, $bufferLogger);
        } catch (ConversionFailedException $e) {

            //$bufferLogger->getText()
            throw $e;
        } catch (ConverterNotOperationalException $e) {
            // (SystemRequirementsNotMetException is also a ConverterNotOperationalException)
            // this is ok.
            return true;
        }
        return true;
    }

    public function testWithNice() {
        $source = self::getImagePath('test.png');
        $options = [
            'use-nice' => true,
            'lossless' => true,
        ];
        $this->assertTrue(self::tryThis($this, $source, $options));
    }

}
