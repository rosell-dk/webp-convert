<?php

namespace WebPConvert\Tests\Convert\Converters;

use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Gd');
    }

}
