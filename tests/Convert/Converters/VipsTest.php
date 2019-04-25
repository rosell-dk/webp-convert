<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Vips;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Tests\Convert\Exposers\VipsExposer;

use PHPUnit\Framework\TestCase;

class VipsTest extends TestCase
{

    public function __construct()
    {
        require_once('pretend.inc');
    }

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

    public function testCreateParamsForVipsWebPSave1()
    {
        $options = [
            'lossless' => true,
            'smart-subsample' => true,
            'near-lossless' => 90,
            'lossless' => true,
            'preset' => 1,
        ];
        $vipsExposer = $this->createVipsExposer('test.png', $options);

        $vipsParams = $vipsExposer->createParamsForVipsWebPSave();

        // Check some options that are straightforwardly copied
        $this->assertSame($options['lossless'], $vipsParams['lossless']);
        $this->assertSame($options['smart-subsample'], $vipsParams['smart_subsample']);
        $this->assertSame($options['preset'], $vipsParams['preset']);

        // When near-lossless is set, the value should be copied to Q
        $this->assertSame($options['near-lossless'], $vipsParams['Q']);
    }

    public function testCreateParamsForVipsWebPSave2()
    {
        $options = [
            'alpha-quality' => 100
        ];
        $vipsExposer = $this->createVipsExposer('test.png', $options);

        $vipsParams = $vipsExposer->createParamsForVipsWebPSave();

        // Some options are only set if they differ from default
        $this->assertFalse(isset($vipsParams['smart_subsample']));
        $this->assertFalse(isset($vipsParams['alpha_q']));
    }

    // pretend imagewebp is missing
    public function testNotOperational1()
    {
        global $pretend;

        $vips = $this->createVips('test.png');
        reset_pretending();

        $pretend['functionsNotExisting'] = ['vips_image_new_from_file'];
        $this->expectException(SystemRequirementsNotMetException::class);
        $vips->checkOperationality();
    }
}
