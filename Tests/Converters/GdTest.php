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
        } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
            // Converter failed in an anticipated fashion.
            // This is acceptable too
        }
    }
}
