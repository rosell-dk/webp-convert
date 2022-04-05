<?php

namespace WebPConvert\Tests\Serve;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;
use WebPConvert\Exceptions\InvalidInputException;
use WebPConvert\Exceptions\InvalidInput\InvalidImageTypeException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\MockedHeader;
use WebPConvert\Serve\Exceptions\ServeFailedException;

use WebPConvert\Tests\CompatibleTestCase;

use ServeConvertedWebPExposer;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Serve\ServeConvertedWebP
 * @covers WebPConvert\Serve\ServeConvertedWebP
 */
class ServeConvertedWebPTest extends CompatibleTestCase
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
     *  Call to serve and return result or exception
     *
     *  The method takes care of closing output buffer in case of exception
     *
     *  @return array  First item: the output, second item: Exception, if thrown
     */
    public static function callServe($filename, $destination, $options)
    {
        ob_start();
        try {
            ServeConvertedWebP::serve($filename, $destination, $options);
        } catch (\Exception $e) {
           return [ob_get_clean(), $e];
        } catch (\Throwable $e) {
           return [ob_get_clean(), $e];
        }
        return [ob_get_clean(), null];
    }

    /**
     *  Call to serve and return result or exception
     *
     *  The method takes care of closing output buffer in case of exception
     *
     *  @return string  the output
     */
    public static function callServeWithThrow($filename, $destination, $options)
    {
        ob_start();
        try {
            ServeConvertedWebP::serve($filename, $destination, $options);
        } catch (\Exception $e) {
            ob_get_clean();
            throw($e);
        } catch (\Throwable $e) {
            ob_get_clean();
            throw($e);
        }
        return ob_get_clean();
    }

    /**
     *  Call to serve and return result or exception
     *
     *  The method takes care of closing output buffer in case of exception
     *
     *  @return string  the output
     */
    public static function callServeOriginalWithThrow($filename, $options)
    {
        ob_start();
        try {
            ServeConvertedWebP::serveOriginal($filename, $options);
        } catch (\Exception $e) {
            ob_get_clean();
            throw($e);
        } catch (\Throwable $e) {
            ob_get_clean();
            throw($e);
        }
        return ob_get_clean();
    }

    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginal()
    {
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source), 'source file does not exist:' . $source);

        $destination = $source . '.webp';

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeOriginalWithThrow($source, $options);

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
        //$this->expectException(InvalidImageTypeException::class);
        $this->expectException(ServeFailedException::class);

        $source =$this->getImagePath('text.txt');
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(false, $contentType);

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeOriginalWithThrow($source, []);
        $this->assertEquals('', $result);
    }

    /**
     * @covers ::serveOriginal
     */
    public function testServeOriginalNotAnImage2()
    {
        //$this->expectException(InvalidImageTypeException::class);
        $this->expectException(ServeFailedException::class);        

        $source = $this->getImagePath('text');
        $this->assertTrue(file_exists($source), 'source file does not exist');

        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        $this->assertSame(null, $contentType);

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeOriginalWithThrow($source, $options);
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

        $options = [
            //'serve-original' => true,
            'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $destination, $options);

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

        $options = [
            'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $destination, $options);

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

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $destination, $options);


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
        $result = self::callServeWithThrow($source, $this->getImagePath('test.png.webp'), $options);
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

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $destination, $options);
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

        $options = [
            //'serve-original' => true,
            //'reconvert' => true,
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $destination, $options);
    }

    /**
     * @covers ::serve
     */
    public function testServeReport()
    {
        $source = $this->getImagePath('test.png');
        $this->assertTrue(file_exists($source));
        $destination = $source . '.webp';

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
        $result = self::callServeWithThrow($source, $destination, $options);

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

        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $source . '.webp', $options);

        // the source file contains "text", so the next assert asserts that source was served
        $this->assertMatchesRegularExpression2('#text#', $result);
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

        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $source . '.webp', $options);

        unlink($destination);

        // Our success-converter always creates fake webps with the content:
        // "we-pretend-this-is-a-valid-webp!".
        // So testing that we got this back is the same as testing that a "conversion" was
        // done and the converted file was served. It is btw smaller than the source.

        $this->assertMatchesRegularExpression2('#we-pretend-this-is-a-valid-webp!#', $result);
    }

    public function testNoFileAtDestination()
    {
        $source = $this->getImagePath('test.jpg');
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';
        @unlink($destination);

        $options = [
            'convert' => [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        ];
        $result = self::callServeWithThrow($source, $source . '.webp', $options);

        // Our success-converter always creates fake webps with the content:
        // "we-pretend-this-is-a-valid-webp!".
        // So testing that we got this back is the same as testing that a "convert" was
        // done and the converted file was served. It is btw smaller than the source.

        $this->assertMatchesRegularExpression2('#we-pretend-this-is-a-valid-webp!#', $result);
    }

}
require_once('mock-header.inc');
