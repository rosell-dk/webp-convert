<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\Serve\Header;
use WebPConvert\Serve\MockedHeader;
use WebPConvert\Loggers\BufferLogger;

use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase
{

    public function testAddHeader()
    {
        MockedHeader::reset();

        Header::addHeader('X-test: testing');
        $header0 = MockedHeader::getHeaders()[0];
        $this->assertEquals('X-test: testing', $header0[0]);
        $this->assertFalse($header0[1]);

        Header::addHeader('X-test: testing2');
        $header1 = MockedHeader::getHeaders()[1];
        $this->assertEquals('X-test: testing2', $header1[0]);
        $this->assertFalse($header1[1]);
    }

    public function testSetHeader()
    {
        MockedHeader::reset();

        Header::setHeader('X-test: testing set header');
        $header0 = MockedHeader::getHeaders()[0];
        $this->assertEquals('X-test: testing set header', $header0[0]);
        $this->assertTrue($header0[1]);
    }

    public function testAddLogHeader()
    {
        MockedHeader::reset();
        Header::addLogHeader('test', new BufferLogger());
        $header0 = MockedHeader::getHeaders()[0];
        $this->assertEquals('X-WebP-Convert-Log: test', $header0[0]);
        $this->assertFalse($header0[1]);

    }
}
require_once('mock-header.inc');
