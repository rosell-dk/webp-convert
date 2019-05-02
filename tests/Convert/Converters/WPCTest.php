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
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\InvalidApiKeyException;

use PHPUnit\Framework\TestCase;

class WpcTest extends TestCase
{


    public $imageDir = __DIR__ . '/../../images/';

    public function testApi0()
    {
        if (!empty(getenv('WPC_API_URL_API0'))) {
            ConverterTestHelper::runAllConvertTests($this, 'Wpc', [
                'api-version' => 0,
                'url' => getenv('WPC_API_URL_API0')
            ]);
        }
    }

    public function testApi1()
    {
        if (empty(getenv('WPC_API_URL')) || empty(getenv('WPC_API_KEY'))) {
            return;
        }

        ConverterTestHelper::runAllConvertTests($this, 'Wpc', [
            'api-version' => 1,
            'crypt-api-key-in-transfer' => true
        ]);

        // TODO: Also test without crypt
    }

    public function testWrongSecretButRightUrl()
    {
        if (empty(getenv('WPC_API_URL'))) {
            return;
        }

        $this->expectException(InvalidApiKeyException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'api-version' => 0,
            'url' => getenv('WPC_API_URL'),
            'secret' => 'purposely-wrong-secret!'
        ]);
    }

/*
    public function testMissingURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'url' => '',
            'secret' => 'bad dog!',
        ]);
    }*/

    public function testBadURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'url' => 'badurl!',
            'secret' => 'bad dog!',
        ]);
    }

}
