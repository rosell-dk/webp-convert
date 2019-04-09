<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Tests\Convert\Exposers\GdExposer;
use WebPConvert\Convert\Converters\Gd;

use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Gd');
    }

    public static $imageDir = __DIR__ . '/../..';

    public function testSource()
    {
        $source = self::$imageDir . '/test.png';
        $gd = new Gd($source, $source . '.webp');
        $gdExposer = new GdExposer($gd);

        $this->assertEquals($source, $gdExposer->getSource());
        $this->assertTrue(file_exists($source), 'source does not exist');
    }

    public function testCreateImageResource()
    {
        $source = self::$imageDir . '/test.png';
        $gd = new Gd($source, $source . '.webp');
        $gdExposer = new GdExposer($gd);

        if (!$gdExposer->isOperating()) {
            //$this->assertTrue(false);
            return;
        }

        // It is operating and image should be ok.
        // - so it should be able to create image resource
        $image = $gdExposer->createImageResource();
        $this->assertEquals(gettype($image), 'resource');

    }

}
