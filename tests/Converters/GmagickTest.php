<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Gmagick;
use PHPUnit\Framework\TestCase;

class GmagickTest extends TestCase
{
    /**
     * Test convert.
     * - It must either make a successful conversion, or throw an ConverterNotOperationalException
     *   It may not throw a ConverterFailedException because if it is operational, then it should also
     *   be able to do the conversion.
     *   It may not throw a normal Exception either
     * - It must not return anything
     */
    public function testConvert()
    {
        try {
            $source = (__DIR__ . '/../test.jpg');
            $destination = (__DIR__ . '/../test.webp');

            $result = Gmagick::convert($source, $destination);

            $this->assertTrue(file_exists($destination));
            $this->assertEmpty($result);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // The converter is not operational.
            // and that is ok!
        }
    }

}
