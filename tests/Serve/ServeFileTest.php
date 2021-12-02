<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\Serve\ServeFile;
use WebPConvert\Serve\MockedHeader;
use WebPConvert\Exceptions\InvalidInpuException;
use WebPConvert\Exceptions\InvalidInput\InvalidImageTypeException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;
//use WebPConvert\Serve\Exceptions\ServeFailedException;

use PHPUnit\Framework\TestCase;

class ServeFileTest extends TestCase
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
    public static function callServe($filename, $mime, $options)
    {
        ob_start();
        try {
            ServeFile::serve($filename, $mime, $options);
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
    public static function callServeWithThrow($filename, $mime, $options)
    {
        ob_start();
        try {
            ServeFile::serve($filename, $mime, $options);
        } catch (\Exception $e) {
            ob_get_clean();
            throw($e);
        } catch (\Throwable $e) {
            ob_get_clean();
            throw($e);
        }
        return ob_get_clean();
    }

    public function testServeDefaultOptions()
    {
        MockedHeader::reset();

        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));

        $result = self::callServeWithThrow($filename, 'image/webp', []);

        // Test that content of file was send to output
        $isWindows = preg_match('/^win/i', PHP_OS);
        if ($isWindows) {
            $this->assertEquals("text\r\n", $result);
        } else {
            $this->assertEquals("text\n", $result);

        }

        $headers = MockedHeader::getHeaders();
        $this->assertGreaterThanOrEqual(1, MockedHeader::getNumHeaders());

        // Test that headers were set as expected
        $this->assertTrue(MockedHeader::hasHeader('Content-Type: image/webp'));
        $this->assertFalse(MockedHeader::hasHeader('Vary: Accept'));

        $this->assertTrue(MockedHeader::hasHeaderContaining('Content-Length:'));

        //$this->assertTrue(MockedHeader::hasHeader('Last-Modified: Mon, 29 Apr 2019 12:54:37 GMT'));

        // TODO:The following fails on travis. WHY???
        //$this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));

        //$this->assertTrue(MockedHeader::hasHeader('Cache-Control: public, max-age=86400'));
        //$this->assertTrue(MockedHeader::hasHeaderContaining('Expires:'));
    }

    public function testServeVaryHeader()
    {
        MockedHeader::reset();

        $this->assertEquals(0, MockedHeader::getNumHeaders());

        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));

        $options = [
            'headers' => [
                'vary-accept' => true
            ]
        ];

        $result = self::callServeWithThrow($filename, 'image/webp', $options);

        $this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));

    }


    public function testServeNoHeaders()
    {
        MockedHeader::reset();

        $this->assertEquals(0, MockedHeader::getNumHeaders());

        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));

        $options = [
            'headers' => [
                'cache-control' => false,
                'content-length' => false,
                'content-type' => false,
                'expires' => false,
                'last-modified' => false,
                'vary-accept' => false
            ],
            'cache-control-header' => 'private, max-age=100',
        ];

        $result = self::callServeWithThrow($filename, 'image/webp', $options);

        // Test that content of file was send to output
        $isWindows = preg_match('/^win/i', PHP_OS);
        if ($isWindows) {
            $this->assertEquals("text\r\n", $result);
        } else {
            $this->assertEquals("text\n", $result);
        }

        // Test that headers were set as expected
        // We actually expect that none are added.

        $headers = MockedHeader::getHeaders();
        $this->assertEquals(0, MockedHeader::getNumHeaders());

        // TODO:The following fails on travis. WHY???
        //$this->assertFalse(MockedHeader::hasHeader('Content-Type: image/webp'));

        //$this->assertTrue(MockedHeader::hasHeader('Vary: Accept'));
        //$this->assertFalse(MockedHeader::hasHeader('Last-Modified: Mon, 29 Apr 2019 12:54:37 GMT'));

        // TODO:The following fails on travis. WHY???
        //$this->assertTrue(MockedHeader::hasHeaderContaining('Last-Modified:'));


        $this->assertFalse(MockedHeader::hasHeader('Cache-Control: public, max-age=86400'));
        $this->assertFalse(MockedHeader::hasHeaderContaining('Expires:'));
    }

    public function testServeCustomCacheControl()
    {
        MockedHeader::reset();
        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));

        $options = [
            'headers' => [
                'cache-control' => true,
                'expires' => true,
            ],
            'cache-control-header' => 'private, max-age=100',
        ];

        $result = self::callServeWithThrow($filename, 'image/webp', $options);
        $this->assertTrue(MockedHeader::hasHeader('Cache-Control: private, max-age=100'));
        $this->assertTrue(MockedHeader::hasHeaderContaining('Expires:'));
    }

    public function testServeCustomCacheControlNoMaxAge()
    {
        MockedHeader::reset();
        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));
        $options = [
            'headers' => [
                'cache-control' => true,
            ],
            'cache-control-header' => 'private',
        ];
        $result = self::callServeWithThrow($filename, 'image/webp', $options);

        $this->assertTrue(MockedHeader::hasHeader('Cache-Control: private'));

        // When there is no max-age, there should neither be any Expires
        $this->assertFalse(MockedHeader::hasHeaderContaining('Expires:'));
    }


    public function testServeNonexistantFile()
    {
        //MockedHeader::reset();

        $filename = __DIR__ . '/i-dont-exist-no';
        $this->assertFalse(file_exists($filename));

        //$this->expectException(TargetNotFoundException::class);
        list($result, $e) = self::callServe($filename, 'image/webp', []);
        $this->assertSame(
            'WebPConvert\Exceptions\InvalidInput\TargetNotFoundException',
            get_class($e)
        );
        $this->assertSame('', $result);
        //$this->assertTrue(MockedHeader::hasHeader('X-WebP-Convert-Error: Could not read file'));
    }

}
require_once('mock-header.inc');
