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

    public function testPNGDeclined()
    {
        try {
            $source = __DIR__ . '/../test.png';
            $destination = __DIR__ . '/../test.png.webp';
            Gd::convert($source, $destination, array(
                'skip-pngs' => true
            ));
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // converter isn't operational, so we cannot make the unit test
            return;
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // Yeah, this is what we want to test.
            $this->expectException(\WebPConvert\Converters\Exceptions\ConversionDeclinedException::class);
            Gd::convert($source, $destination, array(
                'skip-pngs' => true
            ));
        }
    }

    public function testTargetNotFound()
    {

        $this->expectException(\WebPConvert\Exceptions\TargetNotFoundException::class);

        Gd::convert(__DIR__ . '/i-dont-exist.jpg', __DIR__ . '/i-dont-exist.webp');
    }

    public function testConvert()
    {
        try {
            $source = (__DIR__ . '/../test.jpg');
            $destination = (__DIR__ . '/../test.webp');

            $result = Gd::convert($source, $destination);

            $this->assertTrue(file_exists($destination));
            $this->assertEmpty($result);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // The converter is not operational.
            // and that is ok!
        }
    }
}
