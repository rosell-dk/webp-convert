<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Wpc;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

use PHPUnit\Framework\TestCase;

class WpcTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Wpc', [
            'url' => 'https://wpc.bitwise-it.dk/wpc/wpc.php',
            'secret' => 'insert-right-secret-for-proper-testing'
        ]);
    }

    public function testWrongSecretButRightUrl()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert(__DIR__ . '/../../test.jpg', __DIR__ . '/../../test.webp', [
            'url' => 'https://wpc.bitwise-it.dk/wpc/wpc.php',
            'secret' => 'purposely-wrong-secret!'
        ]);
    }

    public function testMissingURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert(__DIR__ . '/../../test.jpg', __DIR__ . '/../../test.webp', [
            'url' => '',
            'secret' => 'bad dog!',
        ]);
    }

    public function testBadURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert(__DIR__ . '/../../test.jpg', __DIR__ . '/../../test.webp', [
            'url' => 'badurl!',
            'secret' => 'bad dog!',
        ]);
    }



}
