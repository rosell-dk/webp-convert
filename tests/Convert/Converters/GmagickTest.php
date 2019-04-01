<?php

namespace WebPConvert\Tests\Convert\Converters;

use PHPUnit\Framework\TestCase;

class GmagickTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Gmagick');
    }

/*
    public function testSupported()
    {
        if (!extension_loaded('Gmagick')) {
            return;
        }
        if (!class_exists('Gmagick')) {
            return;
        }
        $im = new \Gmagick();
        $this->assertSame([], $im->queryformats());

    }
*/
}
