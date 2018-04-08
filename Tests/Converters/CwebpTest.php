<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Cwebp;
use PHPUnit\Framework\TestCase;

class CwebpTest extends TestCase
{
    public function testCwebpDefaultPaths()
    {
        $default = [
            '/usr/bin/cwebp',
            '/usr/local/bin/cwebp',
            '/usr/gnu/bin/cwebp',
            '/usr/syno/bin/cwebp'
        ];

        foreach ($default as $key) {
            $this->assertContains($key, Cwebp::$cwebpDefaultPaths);
        }
    }

    /**
     * @expectedException \Exception
     */
    public function testUpdateBinariesInvalidFile()
    {
        $array = [];

        Cwebp::updateBinaries('InvalidFile', 'Hash', $array);
    }

    /**
     * @expectedException \Exception
     */
    public function testUpdateBinariesInvalidHash()
    {
        $array = [];

        Cwebp::updateBinaries('cwebp-linux', 'InvalidHash', $array);
    }

    public function testUpdateBinaries()
    {
        $file = 'cwebp.exe';
        $filePath = realpath(__DIR__ . '/../../Converters/Binaries/' . $file);
        $hash = hash_file('sha256', $filePath);
        $array = [];

        $this->assertContains($filePath, Cwebp::updateBinaries($file, $hash, $array));
    }
}
