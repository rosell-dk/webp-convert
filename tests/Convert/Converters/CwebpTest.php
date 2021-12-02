<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Cwebp;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;

use WebPConvert\Tests\CompatibleTestCase;
use WebPConvert\Tests\Convert\Exposers\CwebpExposer;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Convert\Converters\Cwebp
 * @covers WebPConvert\Convert\Converters\Cwebp
 */
class CwebpTest extends CompatibleTestCase
{

    public static function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public static function getImagePath($image)
    {
        return self::getImageFolder() . '/' . $image;
    }

    public function testConvert()
    {
        ConverterTestHelper::runAllConvertTests($this, 'Cwebp');
    }

    public function testSource()
    {
        $source = self::getImagePath('test.png');
        $cwebp = new Cwebp($source, $source . '.webp');
        $cwebpExposer = new CwebpExposer($cwebp);

        $this->assertEquals($source, $cwebpExposer->getSource());
        $this->assertTrue(file_exists($source), 'source does not exist');
    }


    /**
     * @covers ::createCommandLineOptions
     */
    public function testCreateCommandLineOptions()
    {
        $source = self::getImagePath('test.png');
        $options = [
            'quality' => 'auto',
            'method' => 3,
            'command-line-options' => '-sharpness 5 -crop 10 10 40 40 -low_memory',
        ];
        $cwebp = new Cwebp($source, $source . '.webp', $options);
        $cwebpExposer = new CwebpExposer($cwebp);

        //$cwebpExposer->prepareOptions();

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();
        //$this->assertEquals('e', $commandLineOption); // use this to quickly see it...

        // Per default we have no preset set
        $this->assertDoesNotMatchRegularExpression2('#-preset#', $commandLineOptions);

        // Metadata is per default none
        $this->assertMatchesRegularExpression2('#-metadata none#', $commandLineOptions);

        // We passed the method option and set it to 3
        $this->assertMatchesRegularExpression2('#-m 3#', $commandLineOptions);

        // There must be an output option, and it must be quoted
        $this->assertMatchesRegularExpression2('#-o [\'"]#', $commandLineOptions);

        // There must be a quality option, and it must be digits
        $this->assertMatchesRegularExpression2('#-q \\d+#', $commandLineOptions);

        // -sharpness '5'
        $this->assertMatchesRegularExpression2('#-sharpness [\'"]5[\'"]#', $commandLineOptions);

        // Extra command line option with multiple values. Each are escapeshellarg'ed
        $this->assertMatchesRegularExpression2(
            '#-crop [\'"]10[\'"] [\'"]10[\'"] [\'"]40[\'"] [\'"]40[\'"]#', 
            $commandLineOptions
        );

        // Command line option (flag)
        $this->assertMatchesRegularExpression2('#-low_memory#', $commandLineOptions);

        // -sharpness '5'
        $this->assertMatchesRegularExpression2('#-sharpness [\'"]5[\'"]#', $commandLineOptions);
    }

    /**
     * @covers ::createCommandLineOptions
     */
    public function testCreateCommandLineOptions2()
    {
        $source = self::getImagePath('test.png');
        $options = [
            'quality' => 70,
            'method' => 3,
            'size-in-percentage' => 55,
            'preset' => 'picture'
        ];
        $cwebp = new Cwebp($source, $source . '.webp', $options);
        $cwebpExposer = new CwebpExposer($cwebp);

        //$cwebpExposer->prepareOptions();

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();

        // Preset
        // Note that escapeshellarg uses doublequotes on Windows
        $this->assertMatchesRegularExpression2("#-preset ['\"]picture['\"]#", $commandLineOptions);

        // Size
        $fileSizeInBytes = floor($options['size-in-percentage']/100 * filesize($source));
        $this->assertEquals(1714, $fileSizeInBytes);
        $this->assertMatchesRegularExpression2('#-size ' . $fileSizeInBytes . '#', $commandLineOptions);

        // There must be no quality option, because -size overrules it.
        $this->assertDoesNotMatchRegularExpression2('#-q \\d+#', $commandLineOptions);
    }

    /**
     * @covers ::createCommandLineOptions
     */
    public function testCreateCommandLineOptions3()
    {
        $source = self::getImagePath('test.png');
        $options = [
            'encoding' => 'lossless',
            'near-lossless' => 75,
            'auto-filter' => true,
        ];
        $cwebp = new Cwebp($source, $source . '.webp', $options);
        $cwebpExposer = new CwebpExposer($cwebp);

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();

        // near-lossless
        $this->assertMatchesRegularExpression2('#-near_lossless 75#', $commandLineOptions);

        // There must be no -lossless option, because -near-lossless overrules it.
        $this->assertDoesNotMatchRegularExpression2('#-lossless#', $commandLineOptions);

        // auto-filter
        $this->assertMatchesRegularExpression2('#-af#', $commandLineOptions);

        // no low-memory
        $this->assertDoesNotMatchRegularExpression2('#-low_memory#', $commandLineOptions);
    }

    /**
     * @covers ::createCommandLineOptions
     */
    public function testCreateCommandLineOptions4()
    {
        $source = self::getImagePath('test.png');
        $options = [
            'encoding' => 'lossless',
            'near-lossless' => 100,
            'low-memory' => true,
        ];
        $cwebp = new Cwebp($source, $source . '.webp', $options);
        $cwebpExposer = new CwebpExposer($cwebp);

        $commandLineOptions = $cwebpExposer->createCommandLineOptions();

        // lossless
        $this->assertMatchesRegularExpression2('#-lossless#', $commandLineOptions);

        // There must be no -near_lossless option, because -lossless overrules it.
        $this->assertDoesNotMatchRegularExpression2('#-near_lossless#', $commandLineOptions);

        // low-memory
        $this->assertMatchesRegularExpression2('#-low_memory#', $commandLineOptions);

        // No auto-filter
        $this->assertDoesNotMatchRegularExpression2('#-af#', $commandLineOptions);
    }

    /**
     * @covers ::checkOperationality
     */
     public function testOperatinalityException()
     {
         $source = self::getImagePath('test.png');
         $options = [
             'try-cwebp' => false,
             'try-supplied-binary-for-os' => false,
             'try-common-system-paths' => false,
             'try-discovering-cwebp' => false,
         ];
         $this->expectException(ConverterNotOperationalException::class);
         //$cwebp = new Cwebp($source, $source . '.webp', $options);
         Cwebp::convert($source, $source . '.webp', $options);
     }

     public function testUsingSuppliedBinaryForOS()
     {
         $source = self::getImagePath('test.png');
         $options = [
             'try-cwebp' => false,
             'try-supplied-binary-for-os' => true,
             'try-common-system-paths' => false,
             'try-discovering-cwebp' => false,
         ];
         //$this->expectException(ConverterNotOperationalException::class);
         //$cwebp = new Cwebp($source, $source . '.webp', $options);
         try {
             Cwebp::convert($source, $source . '.webp', $options);
         } catch (ConversionFailedException $e) {
             // this is ok.
             // - but other exceptions are not!
         }
         $this->addToAssertionCount(1);
     }

     public function testUsingCommonSystemPaths()
     {
         $source = self::getImagePath('test.png');
         $options = [
             'try-cwebp' => false,
             'try-supplied-binary-for-os' => false,
             'try-common-system-paths' => true,
             'try-discovering-cwebp' => false,
         ];
         //$this->expectException(ConverterNotOperationalException::class);
         //$cwebp = new Cwebp($source, $source . '.webp', $options);
         try {
             Cwebp::convert($source, $source . '.webp', $options);
         } catch (ConversionFailedException $e) {
             // this is ok.
             // - but other exceptions are not!
         }
         $this->addToAssertionCount(1);

     }

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
