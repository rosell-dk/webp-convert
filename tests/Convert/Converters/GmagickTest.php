<?php

namespace WebPConvert\Tests\Convert\Converters;

use PHPUnit\Framework\TestCase;

class GmagickTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Gmagick');
    }

}
