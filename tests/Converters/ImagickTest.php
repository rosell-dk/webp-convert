<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickTest extends TestCase
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

            $result = Imagick::convert($source, $destination);

            $this->assertTrue(file_exists($destination));
            $this->assertEmpty($result);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // The converter is not operational.
            // and that is ok!
        }
    }

    public function testInvalidDestinationFolder()
    {

        try {
            // We can only do this test, if the converter is operational.
            // In order to test that, we first do a normal conversion
            $source = (__DIR__ . '/../test.jpg');
            $destination = (__DIR__ . '/../test.webp');

            Imagick::convert($source, $destination);

            // if we are here, it means that the converter is operational.
            // Now do something that tests that the converter fails the way it should,
            // when it cannot create the destination file

            $this->expectException(\WebPConvert\Converters\Exceptions\ConverterFailedException::class);

            // I here assume that no system grants write access to their root folder
            // this is perhaps wrong to assume?
            $destinationFolder = '/you-can-delete-me/';

            Imagick::convert(__DIR__ . '/../test.jpg', $destinationFolder . 'you-can-delete-me.webp');
        } catch (\Exception $e) {
            // its ok...
        }
    }
}
