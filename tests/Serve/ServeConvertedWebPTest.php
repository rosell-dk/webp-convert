<?php

namespace WebPConvert\Tests\Serve;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\MockedHeader;
use WebPConvert\Serve\Exceptions\ServeFailedException;

use ServeConvertedWebPExposer;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Serve\ServeConvertedWebP
 * @covers WebPConvert\Serve\ServeConvertedWebP
 */
class ServeConvertedWebPTest extends TestCase
{

    public static $imageFolder = __DIR__ . '/../images';

    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginal()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source), 'source file does not exist:' . $source);

        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serveOriginal($source, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/png'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }


    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginalNotAnImage()
    {
        $this->expectException(ServeFailedException::class);

        $source = self::$imageFolder . '/text.txt';
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(false, $contentType);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serveOriginal($source, []);
        $result = ob_get_clean();
        $this->assertEquals('', $result);
    }

    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginalNotAnImage2()
    {
        $this->expectException(ServeFailedException::class);

        $source = self::$imageFolder . '/text';
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(null, $contentType);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serveOriginal($source, $options);
        $result = ob_get_clean();
        $this->assertEquals('', $result);
    }

    /**
     * @covers ::serve
     */
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
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/webp'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    /**
     * @covers ::serve
     */
    public function testServeServeOriginal()
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
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/png'));
        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    /**
     * @covers ::serve
     */
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
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/webp'));
    }

    /**
     * @covers ::serve
     */
    public function testEmptySourceArg()
    {
        $this->expectException(ServeFailedException::class);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];

        $this->assertEmpty('');
        ServeConvertedWebP::serve('', self::$imageFolder . '/test.png.webp', $options);
        $result = ob_get_clean();
    }

    /**
     * @covers ::serve
     */
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

    /**
     * @covers ::serve
     */
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

    /**
     * @covers ::serve
     */
    public function testServeReport()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));
        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'show-report' => true,
            'converters' => [
                '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Check that output looks like a report
        $this->assertTrue(strpos($result, 'source:') !== false, 'The following does not contain "source:":' . $result);

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-type: image/webp'));
    }

}
require_once('mock-header.inc');
