<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Cwebp;
use WebPConvert\Tests\Convert\Exposers\CwebpExposer;
use PHPUnit\Framework\TestCase;

class CwebpTest extends TestCase
{

    public static $imageDir = __DIR__ . '/../..';

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Cwebp');
    }

    public function testSource()
    {
        $source = self::$imageDir . '/test.png';
        $cwebp = new Cwebp($source, $source . '.webp');
        $cwebpExposer = new CwebpExposer($cwebp);

        $this->assertEquals($source, $cwebpExposer->getSource());
        $this->assertTrue(file_exists($source), 'source does not exist');
    }

    public function testCreateCommandLineOptions()
    {
        $source = self::$imageDir . '/test.png';
        $cwebp = new Cwebp($source, $source . '.webp', [
            'quality' => 'auto',
            'method' => 3,
            'command-line-options' => '-sharpness 5 -crop 10 10 40 40'
        ]);
        $cwebpExposer = new CwebpExposer($cwebp);

        //$cwebpExposer->prepareOptions();

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();
        //$this->assertEquals('e', $commandLineOption); // use this to quickly see it...

        // Metadata is per default none
        $this->assertRegExp('#-metadata none#', $commandLineOptions);

        // We passed the method option and set it to 3
        $this->assertRegExp('#-m 3#', $commandLineOptions);

        // There must be an output option, and it must be quoted
        $this->assertRegExp('#-o \'#', $commandLineOptions);

        // There must be a quality option, and it must be digits
        $this->assertRegExp('#-q \\d+#', $commandLineOptions);

        // -sharpness '5'
        $this->assertRegExp('#-sharpness \'5\'#', $commandLineOptions);

        // Option with multiple values. Each are escapeshellarg'ed
        $this->assertRegExp('#-crop \'10\' \'10\' \'40\' \'40\'#', $commandLineOptions);

    }

/*
    public function testCreateCommandLineOptions2()
    {
        $source = self::$imageDir . '/test.png';
        $cwebp = new Cwebp($source, $source . '.webp', [
            'quality' => 'auto',
            'method' => 3,
        ]);
        $cwebpExposer = new CwebpExposer($cwebp);

        $cwebpExposer->prepareOptions();

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();
    }*/

  /*
    public function testCwebpDefaultPaths()
    {
        $default = [
            '/usr/bin/cwebp',
            '/usr/local/bin/cwebp',
            '/usr/gnu/bin/cwebp',
            '/usr/syno/bin/cwebp'
        ];

        foreach ($default as $key) {
            $this->assertContains($key, Cwebp::$cwebpDefaultPaths);
        }
    }*/

    /**
     * @expectedException \Exception
     */
     /*
    public function testUpdateBinariesInvalidFile()
    {
        $array = [];

        Cwebp::updateBinaries('InvalidFile', 'Hash', $array);
    }*/

    /**
     * @expectedException \Exception
     */
     /*
    public function testUpdateBinariesInvalidHash()
    {
        $array = [];

        Cwebp::updateBinaries('cwebp-linux', 'InvalidHash', $array);
    }

    public function testUpdateBinaries()
    {
        $file = 'cwebp.exe';
        $filePath = realpath(__DIR__ . '/../../Converters/Binaries/' . $file);
        $hash = hash_file('sha256', $filePath);
        $array = [];

        $this->assertContains($filePath, Cwebp::updateBinaries($file, $hash, $array));
    }

    public function testEscapeFilename()
    {
        $wrong = '/path/to/file Na<>me."ext"';
        $right = '/path/to/file\\\ Name.\&#34;ext\&#34;';

        $this->assertEquals($right, Cwebp::escapeFilename($wrong));
    }

    public function testHasNiceSupport()
    {
        $this->assertNotNull(Cwebp::hasNiceSupport());
    }*/
/*
    public function testConvert()
    {
        $source = realpath(__DIR__ . '/../test.jpg');
        $destination = realpath(__DIR__ . '/../test.webp');
        $quality = 85;
        $stripMetadata = true;

        $this->assertTrue(Cwebp::convert($source, $destination, $quality, $stripMetadata));
    }*/

}
