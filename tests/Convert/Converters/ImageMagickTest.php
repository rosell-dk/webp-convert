<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\ImageMagick;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Loggers\BufferLogger;

use PHPUnit\Framework\TestCase;

class ImageMagickTest extends TestCase
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
        ConverterTestHelper::runAllConvertTests($this, 'ImageMagick');
    }

    private static function tryThis($test, $source, $options)
    {
        $bufferLogger = new BufferLogger();

        try {
            ImageMagick::convert($source, $source . '.webp', $options, $bufferLogger);

            $test->addToAssertionCount(1);
        } catch (ConversionFailedException $e) {

            //$bufferLogger->getText()
            throw $e;
        } catch (ConverterNotOperationalException $e) {
            // (SystemRequirementsNotMetException is also a ConverterNotOperationalException)
            // this is ok.
            return;
        }
    }

    public function testWithNice() {
        $source = self::getImagePath('test.png');
        $options = [
            'use-nice' => true,
            'encoding' => 'lossless',
        ];
        self::tryThis($this, $source, $options);
    }

}
