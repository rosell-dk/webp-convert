<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;

use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{

    public static function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public static function getImagePath($image)
    {
        return self::getImageFolder() . '/' . $image;
    }

    /*
    public function testConvert()
    {
        //ConverterTestHelper::runAllConvertTests($this, 'Stack');
    }*/

    public function testConverterNotFound()
    {
        $this->expectException(ConverterNotFoundException::class);

        Stack::convert(
            self::getImagePath('test.jpg'),
            self::getImagePath('test.webp'),
            [
                'converters' => ['invalid-id']
            ]
        );
    }

    public function testCustomConverter()
    {
        Stack::convert(
            self::getImagePath('test.jpg'),
            self::getImagePath('test.webp'),
            [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        );
        $this->addToAssertionCount(1);
    }

}
