<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Tests\Convert\Exposers\AbstractConverterExposer;
use WebPConvert\Tests\Convert\TestConverters\ExposedConverter;
use WebPConvert\Tests\Convert\TestConverters\SuccessGuaranteedConverter;

use PHPUnit\Framework\TestCase;

class AbstractConverterTest extends TestCase
{

    private static $imgDir = __DIR__ . '/../../images';


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

        //$this->assertEquals('image/jpeg', ExposedConverter::exposedGetMimeType(self::$imgDir . '/test.jpg'));
        //$this->assertEquals('image/png', ExposedConverter::exposedGetMimeType(self::$imgDir . '/test.png'));
        //$mimeTypeMaybeDetected = ExposedConverter::exposedGetMimeType(self::$imgDir . '/png-without-extension');

        $successConverterJpeg = SuccessGuaranteedConverter::createInstance(self::$imgDir . '/test.jpg', '');
        $this->assertEquals('image/jpeg', $successConverterJpeg->getMimeTypeOfSource());

        $successConverterPng = SuccessGuaranteedConverter::createInstance(self::$imgDir . '/test.png', '');
        $this->assertEquals('image/png', $successConverterPng->getMimeTypeOfSource());

        $successConverterPngMaybeDetected = SuccessGuaranteedConverter::createInstance(self::$imgDir . '/png-without-extension', '');

        $mimeTypeMaybeDetected = $successConverterPngMaybeDetected->getMimeTypeOfSource();

        if ($mimeTypeMaybeDetected === false) {
            // It was not detected, and that is ok!
            // - it is not possible to detect mime type on all platforms. In case it could not be detected,
            // - and file extension could not be mapped either, the method returns false.
            $this->addToAssertionCount(1);
        } else {
            $this->assertEquals('image/png', $mimeTypeMaybeDetected);
        }
    }

    public function testDefaultOptions()
    {
        $converter = new SuccessGuaranteedConverter(
            self::$imgDir . '/test.jpg',
            self::$imgDir . '/test.webp'
        );

        $exposer = new AbstractConverterExposer($converter);

        $defaultOptions = $exposer->getOptions();

        $this->assertSame('auto', $defaultOptions['quality']);
        $this->assertSame(85, $defaultOptions['max-quality']);
        $this->assertSame(75, $defaultOptions['default-quality']);
        $this->assertSame('none', $defaultOptions['metadata']);
    }


    public function testOptionMerging()
    {
        $converter = new SuccessGuaranteedConverter(
            self::$imgDir . '/test.jpg',
            self::$imgDir . '/test.webp',
            [
                'quality' => 80
            ]
        );

        $exposer = new AbstractConverterExposer($converter);

        //$exposer->prepareOptions();

        $mergedOptions = $exposer->getOptions();

        $this->assertSame(80, $mergedOptions['quality']);
    }
}
