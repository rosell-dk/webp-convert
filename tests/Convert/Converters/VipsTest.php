<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Vips;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Tests\Convert\Exposers\VipsExposer;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Convert\Converters\Vips
 * @covers WebPConvert\Convert\Converters\Vips
 */
class VipsTest extends TestCase
{

    public function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public function getImagePath($image)
    {
        return $this->getImageFolder() . '/' . $image;
    }

    public function __construct()
    {
        //require_once('pretend.inc');
    }

    public function testConvert()
    {
        $options = [];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);

        $options = [
            'smart-subsample' => true,
            'preset' => 'text',
        ];
        ConverterTestHelper::runAllConvertTests($this, 'Vips', $options);
    }


    private function createVips($src, $options = [])
    {
        $source = $this->getImagePath($src);
        $this->assertTrue(file_exists($source), 'source does not exist:' . $source);

        return new Vips($source, $source . '.webp', $options);
    }

    private function createVipsExposer($src, $options = [])
    {
        return new VipsExposer($this->createVips($src, $options));
    }

    private function isVipsOperational()
    {
        try {
            $vips->checkOperationality();
            $vips->checkConvertability();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function testCreateParamsForVipsWebPSave1()
    {
        $options = [
            'encoding' => 'lossless',
            'smart-subsample' => true,
            'near-lossless' => 90,
            'preset' => 'picture',      // In vips, this has the constant: 1
        ];
        $vipsExposer = $this->createVipsExposer('test.png', $options);

        $vipsParams = $vipsExposer->createParamsForVipsWebPSave();

        // Check some options that are straightforwardly copied
        $this->assertSame(true, $vipsParams['lossless']);
        $this->assertSame($options['smart-subsample'], $vipsParams['smart_subsample']);
        $this->assertSame(1, $vipsParams['preset']);

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

    public function testCreateImageResource1()
    {
        // Exit if vips is not operational
        if (!$this->isVipsOperational()) {
            return;
        }

        $source = $this->getImagePath('non-existing');
        $vips = new Vips($source, $source . '.webp', []);
        $vipsExposer = new VipsExposer($vips);

        // It must fail because it should not be able to create resource when file does not exist
        $this->expectException(ConversionFailedException::class);

        $vipsExposer->createImageResource();
    }

    public function testNotOperational1()
    {
        global $pretend;

        $vips = $this->createVips('test.png');
        reset_pretending();

        // pretend vips_image_new_from_file
        $pretend['functionsNotExisting'] = ['vips_image_new_from_file'];
        $this->expectException(SystemRequirementsNotMetException::class);
        $vips->checkOperationality();
    }

    public function testNotOperational2()
    {
        global $pretend;

        $vips = $this->createVips('test.png');
        reset_pretending();

        // pretend vips_image_new_from_file
        $pretend['extensionsNotExisting'] = ['vips'];
        $this->expectException(SystemRequirementsNotMetException::class);
        $vips->checkOperationality();
    }

    public function testOperational1()
    {
        global $pretend;

        $vips = $this->createVips('test.png');
        reset_pretending();

        // pretend vips_image_new_from_file
        $pretend['functionsExisting'] = ['vips_image_new_from_file'];
        $pretend['extensionsExisting'] = ['vips'];
        $vips->checkOperationality();

        $this->addToAssertionCount(1);
    }

    /**
     * @covers ::webpsave
     */
    public function testWebpsave()
    {
        reset_pretending();

        $vips = $this->createVips('test.png', []);
        $vipsExposer = new VipsExposer($vips);

        // Exit if vips is not operational
        if (!$this->isVipsOperational()) {
            $this->markTestSkipped('vips is not operational');
            return;
        }

        $im = $vipsExposer->createImageResource();
        $options = $vipsExposer->createParamsForVipsWebPSave();

        // Create non-existing vips option.
        // - The converter must be able to ignore this without failing
        $options['non-existing-vips-option'] = true;
        $vipsExposer->webpsave($im, $options);
    }

    /**
     * @covers ::createImageResource
     */
    public function testCreateImageResourceWhenFileNotFound()
    {
        //
        reset_pretending();

        // Exit if vips is not operational
        if (!$this->isVipsOperational()) {
            $this->markTestSkipped('vips is not operational');
            return;
        }

        $source = self::$imageDir . '/i-do-not-exist.jpg';
        $this->assertFalse(file_exists($source));

        $options = [];
        $vips = new Vips($source, $source . '.webp', $options);
        $vipsExposer = new VipsExposer($vips);

        // this should fail!
        try {
            $im = $vipsExposer->createImageResource();
            $this->fail('exception was expected');
        } catch (ConversionFailedException $e) {
            $this->assertRegExp('#not found#', $e->getMessage());
        }

    }
/*
    public function testDoActualConvert()
    {

        $options = [
            'alpha-quality' => 100
        ];
        $vipsExposer = $this->createVipsExposer('test.png', $options);

        $vips = $this->createVips('not-existing.png');

        $this->addToAssertionCount(1);
    }*/
}

require_once('pretend.inc');
