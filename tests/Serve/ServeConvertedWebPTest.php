<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\MockedHeader;
use WebPConvert\Serve\Exceptions\ServeFailedException;

use PHPUnit\Framework\TestCase;

class ServeConvertedWebPTest extends TestCase
{

    public static $imageFolder = __DIR__ . '/../images';

    public function testServeFreshlyConverted()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        $this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/webp'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    public function testServeOriginal()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        ob_start();
        $options = [
            'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        $this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/png'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    public function testServeDestination()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        // create fake webp at destination
        $destination = $source . '.webp';
        file_put_contents($destination, '1234');
        $this->assertTrue(file_exists($destination));


        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Check that destination is output (it has the content "1234")
        $this->assertEquals('1234', $result);

        // Test that headers were set as expected
        $this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/webp'));
    }

    public function testInvalidSourceArg()
    {
        $this->expectException(ServeFailedException::class);

        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        $source = '';
        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();
    }

    public function testInvalidDestinationArg()
    {
        $this->expectException(ServeFailedException::class);

        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        $destination = '';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();
    }

    public function testNoFileAtSource()
    {
        $this->expectException(ServeFailedException::class);

        $source = self::$imageFolder . '/i-do-not-exist.png';
        $this->assertFalse(file_exists($source));

        $destination = '';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();
    }

}
require_once('mock-header.inc');
