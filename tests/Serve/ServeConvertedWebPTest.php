<?php

namespace WebPConvert\Tests\Serve;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;
use WebPConvert\Exceptions\InvalidInputException;
use WebPConvert\Exceptions\InvalidInput\InvalidImageTypeException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;
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

    public function getImageFolder()
    {
        return realpath(__DIR__ . '/../images');
    }

    public function getImagePath($image)
    {
        return $this->getImageFolder() . '/' . $image;
    }

    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginal()
    {
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source), 'source file does not exist:' . $source);

        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serveOriginal($source, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/png'));
        $this->assertFalse(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertFalse(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }


    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginalNotAnImage()
    {
        $this->expectException(InvalidImageTypeException::class);

        $source =$this->getImagePath('text.txt');
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(false, $contentType);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
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
        $this->expectException(InvalidImageTypeException::class);

        $source = $this->getImagePath('text');
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(null, $contentType);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serveOriginal($source, $options);
        $result = ob_get_clean();
        $this->assertEquals('', $result);
    }

    /**
     * @covers ::serve
     */
    public function testServeReconvert()
    {
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/webp'));
        $this->assertFalse(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertFalse(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    /**
     * @covers ::serve
     */
    public function testServeServeOriginal()
    {
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        ob_start();
        $options = [
            'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/png'));
        $this->assertFalse(MockedHeader::hasHeader('Vary: Accept'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));
        $this->assertFalse(MockedHeader::hasHeaderContaining('Cache-Control:'));
    }

    /**
     * Testing when the "cached" image can be served
     * @covers ::serve
     */
    public function testServeDestination()
    {
        $source = $this->getImagePath('/test.png');
        $this->assertTrue(file_exists($source));

        // create fake webp at destination
        $destination = $source . '.webp';
        file_put_contents($destination, '1234');
        $this->assertTrue(file_exists($destination));

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Check that destination is output (it has the content "1234")
        $this->assertEquals('1234', $result);

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/webp'));
    }

    /**
     * @covers ::serve
     */
    public function testEmptySourceArg()
    {
        $this->expectException(InvalidInputException::class);

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];

        $source = '';
        $this->assertEmpty($source);
        ServeConvertedWebP::serve($source, $this->getImagePath('test.png.webp'), $options);
        $result = ob_get_clean();
    }

    /**
     * @covers ::serve
     */
    public function testEmptyDestinationArg()
    {
        $this->expectException(InvalidInputException::class);

        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source));

        $destination = '';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
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
        $this->expectException(TargetNotFoundException::class);

        $source = $this->getImagePath('i-do-not-exist.png');
        $this->assertFalse(file_exists($source));

        $destination = '';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
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
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source));
        $destination = $source . '.webp';

        ob_start();
        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'show-report' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $destination, $options);
        $result = ob_get_clean();

        // Check that output looks like a report
        $this->assertTrue(strpos($result, 'source:') !== false, 'The following does not contain "source:":' . $result);

        // Test that headers were set as expected
        //$this->assertTrue(MockedHeader::hasHeaderContaining('X-WebP-Convert-Action:'));

        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/webp'));
    }

    public function testSourceIsLighter()
    {
        $source = $this->getImagePath('plaintext-with-jpg-extension.jpg');

        // create fake webp at destination, which is larger than the fake jpg
        file_put_contents($source . '.webp', 'aotehu aotnehuatnoehutaoehu atonse uaoehu');

        $this->assertTrue(file_exists($source));
        $this->assertTrue(file_exists($source . '.webp'));

        ob_start();
        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $source . '.webp', $options);
        $result = ob_get_clean();

        // the source file contains "text", so the next assert asserts that source was served
        $this->assertRegExp('#text#', $result);
    }

    public function testExistingOutDated()
    {
        $source = $this->getImagePath('test.jpg');
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';
        @unlink($destination);
        copy($this->getImagePath('pre-converted/test.webp'), $destination);

        // set modification date earlier than source
        touch($destination, filemtime($source) - 1000);

        // check that it worked
        $this->assertLessThan(filemtime($source), filemtime($destination));

        ob_start();
        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $source . '.webp', $options);
        $result = ob_get_clean();

        unlink($destination);

        // Our success-converter always creates fake webps with the content:
        // "we-pretend-this-is-a-valid-webp!".
        // So testing that we got this back is the same as testing that a "conversion" was
        // done and the converted file was served. It is btw smaller than the source.

        $this->assertRegExp('#we-pretend-this-is-a-valid-webp!#', $result);
    }

    public function testNoFileAtDestination()
    {
        $source = $this->getImagePath('test.jpg');
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';
        @unlink($destination);

        ob_start();
        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        ServeConvertedWebP::serve($source, $source . '.webp', $options);
        $result = ob_get_clean();

        // Our success-converter always creates fake webps with the content:
        // "we-pretend-this-is-a-valid-webp!".
        // So testing that we got this back is the same as testing that a "convert" was
        // done and the converted file was served. It is btw smaller than the source.

        $this->assertRegExp('#we-pretend-this-is-a-valid-webp!#', $result);
    }

}
require_once('mock-header.inc');
