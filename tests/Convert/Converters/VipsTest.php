<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Vips;
use WebPConvert\Tests\Convert\Exposers\VipsExposer;

use PHPUnit\Framework\TestCase;

class VipsTest extends TestCase
{

    public function testConvert()
    {
        $options = [];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);

        $options = [
            'smart-subsample' => true,
            'preset' => 1,
        ];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);
    }

    public static $imageDir = __DIR__ . '/../..';

    private function createVips($src, $options = [])
    {
        $source = self::$imageDir . '/' . $src;
        $this->assertTrue(file_exists($source), 'source does not exist:' . $source);

        return new Vips($source, $source . '.webp', $options);
    }

    private function createVipsExposer($src, $options = [])
    {
        return new VipsExposer($this->createVips($src, $options));
    }

    public function testCreateParamsForVipsWebPSave()
    {
        $options = [
            'lossless' => true,
            'smart-subsample' => true,
        ];
        $vipsExposer = $this->createVipsExposer('test.png', $options);

        $vipsParams = $vipsExposer->createParamsForVipsWebPSave();
        $this->assertSame($options['lossless'], $vipsParams['lossless']);
        $this->assertSame($options['smart_subsample'], $vipsParams['smart-subsample']);

    }
}
