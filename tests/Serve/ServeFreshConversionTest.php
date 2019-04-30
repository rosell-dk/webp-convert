<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\WebPConvert;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

use WebPConvert\Serve\MockedHeader;
use WebPConvert\Serve\ServeFreshConversion;
use WebPConvert\Serve\Exceptions\ServeFailedException;

use PHPUnit\Framework\TestCase;

class ServeFreshConversionTest extends TestCase
{

    public static $imageFolder = __DIR__ . '/../images';

    private static $isConversionWorking;

    /*
    private static function isConversionWorking()
    {
        if (isset(self::$isConversionWorking)) {
            return self::$isConversionWorking;
        }
        $source = self::$imageFolder . '/test.png';
        try {
            WebPConvert::convert($source, $source . '.webp');
            self::$isConversionWorking = true;
        } catch (\Exception $e) {
            self::$isConversionWorking = false;
        }

        return self::$isConversionWorking;
    }
    */

    public function testServeWhenOriginalIsRequested()
    {
        MockedHeader::reset();

        $source = self::$imageFolder . '/test.png';
        //$source = self::$imageFolder . '/text-with-jpg-extension.jpg';
        //$source = self::$imageFolder . '/plaintext-with-jpg-extension.jpg';

        $this->assertTrue(file_exists($source));

        ob_start();
        ServeFreshConversion::serve($source, $source . '.webp', [
            'serve-original' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ]);
        $result = ob_get_clean();

        // Test that it appears that a PNG was output
        $firstFour = strtoupper(bin2hex(substr($result, 0, 4)));
        $firstFourOfPNG = '89504E47';
        $this->assertEquals($firstFourOfPNG, $firstFour);

        // Test that headers were set as expected
        $this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/png'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    public function testServeOriginalButConvertFailure()
    {
        $this->expectException(ConversionFailedException::class);

        $source = self::$imageFolder . '/test.png';
        ServeFreshConversion::serve($source, $source . '.webp', [
            'serve-original' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\FailureGuaranteedConverter'
            ]
        ]);
    }


}
require_once('mock-header.inc');
