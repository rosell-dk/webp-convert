<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Vips;

use PHPUnit\Framework\TestCase;

class VipsTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Vips');
    }

    public static $imageDir = __DIR__ . '/../..';


}
