<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Vips;

use PHPUnit\Framework\TestCase;

class VipsTest extends TestCase
{

    public function testConvert()
    {
        $options = [];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);

        $options = [
            'smart-subsample' => true,
            'preset' => 1,
        ];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);
    }

    public static $imageDir = __DIR__ . '/../..';


}
