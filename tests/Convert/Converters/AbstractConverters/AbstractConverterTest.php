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

        $mimeTypeMaybeDetected = AbstractConverter::getMimeType(self::$imgDir . '/png-without-extension');
        if ($mimeTypeMaybeDetected === false) {
            // It was not detected, and that is ok!
            // - it is not possible to detect mime type on all platforms. In case it could not be detected,
            // - and file extension could not be mapped either, the method returns false.
            $this->addToAssertionCount(1);
        } else {
            $this->assertEquals('image/png', $mimeTypeMaybeDetected);
        }

    }
}
