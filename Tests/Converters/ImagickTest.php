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
  /*
    public function testGetExtension()
    {
        $this->assertEquals('php', Imagick::getExtension(__FILE__));
    }*/

    public function testConvert()
    {
        $source = realpath(__DIR__ . '/../test.jpg');
        $destination = realpath(__DIR__ . '/../test.webp');
        $quality = 85;
        $stripMetadata = true;

        $this->assertTrue(Imagick::convert($source, $destination, $quality, $stripMetadata));
    }
}
