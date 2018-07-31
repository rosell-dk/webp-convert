<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Converters;

use WebPConvert\Converters\Wpc;
use PHPUnit\Framework\TestCase;

class WpcTest extends TestCase
{

    public function testMissingURL()
    {
        $this->expectException(\WebPConvert\Converters\Exceptions\ConverterNotOperationalException::class);
        Wpc::convert(__DIR__ . '/../test.jpg', __DIR__ . '/../test.webp', [
            'url' => '',
            'secret' => 'bad dog!',
        ]);
    }

    public function testBadURL()
    {
        $this->expectException(\WebPConvert\Converters\Exceptions\ConverterNotOperationalException::class);
        Wpc::convert(__DIR__ . '/../test.jpg', __DIR__ . '/../test.webp', [
            'url' => 'badurl!',
            'secret' => 'bad dog!',
        ]);
    }



}
