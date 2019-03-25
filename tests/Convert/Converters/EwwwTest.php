<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Ewww;
use PHPUnit\Framework\TestCase;

class EwwwTest extends TestCase
{
    /**
     * Test convert.
     * We cannot test a real conversion, because that requires a valid key.
     * But we can at least test that the converter throws an expected exception
     */
    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Ewww', [
            //'key' => ''
        ]);
    }

    public function testIsValidKey()
    {
        $invalidKey = 'notvalidno';
        $this->assertFalse(Ewww::isValidKey($invalidKey));

        $demoKey = 'abc123';
        $this->assertTrue(Ewww::isValidKey($demoKey));
    }

    public function testIsWorkingKey()
    {
        $invalidKey = 'notvalidno';
        $this->assertFalse(Ewww::isWorkingKey($invalidKey));
    }
}
