<?php

namespace WebPConvert\Tests\Convert\Converters;

use PHPUnit\Framework\TestCase;

class ImagickTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Imagick');
    }

}
