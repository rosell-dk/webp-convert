<?php

namespace WebPConvert\Tests\Convert\Converters\AbstractConverters;

use WebPConvert\Tests\Convert\Converters\SuccessGuaranteedConverter;

use PHPUnit\Framework\TestCase;

class AbstractConverter extends TestCase
{

    public function testConvert()
    {
        SuccessGuaranteedConverter::convert(
            __DIR__ . '/../../../test.jpg',
            __DIR__ . '/../../../test.webp'
        );
        $this->addToAssertionCount(1);

    }
}
