<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Ewww;
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
        try {
            $source = (__DIR__ . '/../test.jpg');
            $destination = (__DIR__ . '/../test.webp');

            $result = Ewww::convert($source, $destination);

            $this->assertTrue(file_exists($destination));
            $this->assertEmpty($result);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // The converter is not operational.
            // and that is ok!
        }
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
