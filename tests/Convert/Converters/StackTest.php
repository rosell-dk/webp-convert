<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;

use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{

    public function testConvert()
    {
        //ConverterTestHelper::runAllConvertTests($this, 'Stack');
    }

    public function testConverterNotFound()
    {
        $this->expectException(ConverterNotFoundException::class);

        Stack::convert(
            __DIR__ . '/../../test.jpg',
            __DIR__ . '/../../test.webp',
            [
                'converters' => ['invalid-id']
            ]
        );
    }

    public function testCustomConverter()
    {
        Stack::convert(
            __DIR__ . '/../../test.jpg',
            __DIR__ . '/../../test.webp',
            [
                'converters' => [
                    '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                ]
            ]
        );
        $this->addToAssertionCount(1);
    }

}
