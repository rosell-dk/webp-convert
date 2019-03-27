<?php

namespace WebPConvert\Tests\Convert\Converters\AbstractConverters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;
use WebPConvert\Tests\Convert\Converters\SuccessGuaranteedConverter;

use PHPUnit\Framework\TestCase;

class AbstractConverterTest extends TestCase
{

    private static $imgDir = __DIR__ . '/../../..';

    public function testConvert()
    {
        SuccessGuaranteedConverter::convert(
            self::$imgDir . '/test.jpg',
            self::$imgDir . '/test.webp'
        );
        $this->addToAssertionCount(1);
    }

    public function testMimeTypeGuesser()
    {
        $this->assertEquals('image/jpeg', AbstractConverter::getMimeType(self::$imgDir . '/test.jpg'));
        $this->assertEquals('image/png', AbstractConverter::getMimeType(self::$imgDir . '/test.png'));
    }
}
