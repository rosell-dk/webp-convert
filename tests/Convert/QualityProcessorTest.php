<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert;

use WebPConvert\Convert\QualityProcessor;
use WebPConvert\Tests\Convert\TestConverters\SuccessGuaranteedConverter;

use PHPUnit\Framework\TestCase;

class QualityProcessorTest extends TestCase
{

    private static $imgDir = __DIR__ . '/../images';

    public function testFixedQuality()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/small-q61.jpg',
            self::$imgDir . '/small-q61.jpg.webp',
            [
                'max-quality' => 80,
                'quality' => 75,
                'default-quality' => 70,
            ]
        );

        $qp = new QualityProcessor($converter);
        $result = $qp->getCalculatedQuality();
        $this->assertSame(75, $result);

        $this->assertFalse($qp->isQualityDetectionRequiredButFailing());

        // Test that it is still the same (testing caching)
        $this->assertFalse($qp->isQualityDetectionRequiredButFailing());

    }

    public function testAutoQualityWhenQualityCannotBeDetected()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/non-existant',
            self::$imgDir . '/non-existant.webp',
            [
                'max-quality' => 80,
                'quality' => 'auto',
                'default-quality' => 70,
            ]
        );

        $qp = new QualityProcessor($converter);
        $result = $qp->getCalculatedQuality();

        $this->assertSame(70, $result);
    }

    public function testAutoQuality()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/small-q61.jpg',
            self::$imgDir . '/small-q61.jpg.webp',
            [
                'max-quality' => 80,
                'quality' => 'auto',
                'default-quality' => 61,
            ]
        );

        $qp = new QualityProcessor($converter);
        $result = $qp->getCalculatedQuality();

        // "Cheating" a bit here...
        // - If quality detection fails, it will be 61 (because default-quality is set to 61)
        // - If quality detection succeeds, it will also be 61
        $this->assertSame(61, $result);
    }

    public function testAutoQualityMaxQuality()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/small-q61.jpg',
            self::$imgDir . '/small-q61.jpg.webp',
            [
                'max-quality' => 60,
                'quality' => 'auto',
                'default-quality' => 61,
            ]
        );

        $qp = new QualityProcessor($converter);

        $this->assertSame(60, $qp->getCalculatedQuality());

        // Test that it is still the same (testing caching)
        $this->assertSame(60, $qp->getCalculatedQuality());
    }

    public function testAutoQualityMaxQualityOnNonJpeg()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/non-existant',
            self::$imgDir . '/non-existant.webp',
            [
                'max-quality' => 60,
                'quality' => 'auto',
                'default-quality' => 70,
            ]
        );

        $qp = new QualityProcessor($converter);

        $this->assertSame(70, $qp->getCalculatedQuality());
        $this->assertFalse($qp->isQualityDetectionRequiredButFailing());
    }

    public function testAutoQualityOnQualityDetectionFail()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/non-existing.jpg',
            self::$imgDir . '/non-existant.webp',
            [
                'max-quality' => 70,
                'quality' => 'auto',
                'default-quality' => 60,
            ]
        );

        $qp = new QualityProcessor($converter);

        $this->assertSame(60, $qp->getCalculatedQuality());
        $this->assertTrue($qp->isQualityDetectionRequiredButFailing());
    }


    public function testIsQualitySetToAutoAndDidQualityDetectionFail()
    {
        $converter = SuccessGuaranteedConverter::createInstance(
            self::$imgDir . '/non-existant.jpg',
            self::$imgDir . '/non-existant.webp',
            [
                'max-quality' => 60,
                'quality' => 'auto',
                'default-quality' => 70,
            ]
        );

        $qp = new QualityProcessor($converter);
        $this->assertTrue($qp->isQualityDetectionRequiredButFailing());

        // Test that it is still the same (testing caching)
        $this->assertTrue($qp->isQualityDetectionRequiredButFailing());
    }


}
