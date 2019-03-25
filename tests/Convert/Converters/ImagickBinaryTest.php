<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Imagickbinary;
use PHPUnit\Framework\TestCase;

class ImagickBinaryTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Imagickbinary');
    }

}
