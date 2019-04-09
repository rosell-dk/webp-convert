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

        // It is operating and image should be ok.
        // - so it should be able to create image resource
        $image = $gdExposer->createImageResource();
        $this->assertEquals(gettype($image), 'resource');

    }

}
