<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Tests\Convert\TestConverters\GdExposer;
use WebPConvert\Convert\Converters\Gd;

use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Gd');
    }

    public function testSource()
    {
        $source = __DIR__ . '/../../test.png';
        $gdExposer = new GdExposer($source, $source . '.webp');
        $this->assertEquals($source, $gdExposer->getSource());
        $this->assertTrue(file_exists($source), 'source does not exist');
    }

    public function testCreateImageResource()
    {
        $source = __DIR__ . '/../../test.png';

        $gdExposer = new GdExposer($source, $source . '.webp');

        if (!$gdExposer->isOperating()) {
            return;
        }

        // Get image before it has been created should return false
        $image = $gdExposer->getImage();
        $this->assertFalse($image, 'Getting image before it has been created should return false');

        $gdExposer->createImageResource();
        $image = $gdExposer->getImage();
        $this->assertNotFalse($image, 'Failed creating image even though Gd is operating and source image should be ok');
        $this->assertEquals(gettype($image), 'resource');

    }

}
