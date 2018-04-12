<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Gd;
use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{
    /**
     * Test convert.
     * - It must either make a successful conversion, or throw one of these Exceptions:
     *   NoOperationalConvertersException or ConverterFailedException
     *   That shows that the exception was anticipated.
     *   Other exceptions are unexpected and will result in test failure
     * - It must not return anything
     */
    public function testConvert()
    {
        try {
            $source = (__DIR__ . '/../test.jpg');
            $destination = (__DIR__ . '/../test.webp');
            $quality = 80;
            $stripMetadata = true;

            $result = Gd::convert($source, $destination, $quality, $stripMetadata);

            $this->assertTrue(file_exists($destination));
            $this->assertEmpty($result);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // The converter is not operational.
            // and that is ok!
        }
    }

    public function testPNGDeclined()
    {
        $this->expectException(\WebPConvert\Converters\Exceptions\ConversionDeclinedException::class);
        Gd::convert(__DIR__ . '/../test.png', __DIR__ . '/../test.png.webp', 80, true, array(
            'convert_pngs' => false
        ));
    }

    public function testPNGDeclined2()
    {
        $this->expectException(\WebPConvert\Converters\Exceptions\ConversionDeclinedException::class);
        Gd::convert(__DIR__ . '/../test.png', __DIR__ . '/../test.png.webp', 80, true);
    }
}
