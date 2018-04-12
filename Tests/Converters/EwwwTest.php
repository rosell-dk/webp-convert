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
            $quality = 80;
            $stripMetadata = true;

            $result = Ewww::convert($source, $destination, $quality, $stripMetadata);

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
