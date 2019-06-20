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

    public function testServeDefaultOptions()
    {
        MockedHeader::reset();

        $filename = self::getImagePath('plaintext-with-jpg-extension.jpg');
        $this->assertTrue(file_exists($filename));

        ob_start();
        ServeFile::serve($filename, 'image/webp', []);
        $result = ob_get_clean();

        // Test that content of file was send to output
        $this->assertEquals("text\n", $result);

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

        ob_start();
        ServeFile::serve($filename, 'image/webp', $options);
        $result = ob_get_clean();

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

        ob_start();
        ServeFile::serve($filename, 'image/webp', $options);
        $result = ob_get_clean();

        // Test that content of file was send to output
        $this->assertEquals("text\n", $result);

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

        ob_start();
        ServeFile::serve($filename, 'image/webp', $options);
        $result = ob_get_clean();
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
        ob_start();
        ServeFile::serve($filename, 'image/webp', $options);
        $result = ob_get_clean();
        $this->assertTrue(MockedHeader::hasHeader('Cache-Control: private'));

        // When there is no max-age, there should neither be any Expires
        $this->assertFalse(MockedHeader::hasHeaderContaining('Expires:'));
    }

    public function testServeNonexistantFile()
    {
        MockedHeader::reset();

        $filename = __DIR__ . '/i-dont-exist-no';
        $this->assertFalse(file_exists($filename));

        $this->expectException(TargetNotFoundException::class);

        ob_start();
        ServeFile::serve($filename, 'image/webp', []);
        $result = ob_get_clean();

        $this->assertEquals("", $result);

        $this->assertTrue(MockedHeader::hasHeader('X-WebP-Convert-Error: Could not read file'));
    }

}
require_once('mock-header.inc');
