<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;
use PHPUnit\Framework\TestCase;

class WebPConvertTest extends TestCase
{
  /*
    public function testPreferredConverters()
    {
        $this->assertEmpty(WebPConvert::$preferredConverters);
    }

    public function testExcludeDefaultBinaries()
    {
        $this->assertFalse(WebPConvert::$excludeDefaultBinaries);
    }

    public function testAllowedExtensions()
    {
        $allowed = ['jpg', 'jpeg', 'png'];

        foreach ($allowed as $key) {
            $this->assertContains($key, WebPConvert::$allowedExtensions);
        }
    }

    public function testSetConverters()
    {
        $preferred = ['gd', 'cwebp'];
        WebPConvert::setConverters($preferred);

        $this->assertEquals($preferred, WebPConvert::$preferredConverters);
    }
*/
    /**
     * @expectedExceptio \Exception
     */
     /*
    public function testIsValidTargetInvalid()
    {
        WebPConvert::isValidTarget('Invalid');
    }

    public function testIsValidTargetValid()
    {
        $this->assertTrue(WebPConvert::isValidTarget(__FILE__));
    }
*/
    /**
     * @expectedExceptio \Exception
     */
     /*
    public function testIsAllowedExtensionInvalid()
    {
        $allowed = ['jpg', 'jpeg', 'png'];

        foreach ($allowed as $key) {
            WebPConvert::isAllowedExtension(__FILE__);
        }
    }

    public function testIsAllowedExtensionValid()
    {
        $source = (__DIR__ . '/test.jpg');

        $this->assertTrue(WebPConvert::isAllowedExtension($source));
    }

    public function testGetConvertersDefault()
    {
        $default = ['cwebp', 'ewww', 'gd', 'imagick'];

        foreach ($default as $key) {
            $this->assertContains($key, WebPConvert::getConverters());
        }
    }

    public function testGetConvertersCustom()
    {
        WebPConvert::$preferredConverters = ['gd', 'cwebp'];
        $custom = ['gd', 'cwebp', 'ewww', 'imagick'];

        $this->assertEquals($custom, WebPConvert::getConverters());
    }

    public function testSetGetConverters()
    {
        $preferred = ['gd', 'cwebp'];
        WebPConvert::setConverters($preferred, true);

        $this->assertEquals($preferred, WebPConvert::getConverters());
    }
*/

/*
Idea: WebPConvert could throw custom exceptions, and we
could test these like this:
$this->expectException(InvalidArgumentException::class);
https://phpunit.readthedocs.io/en/7.1/writing-tests-for-phpunit.html#testing-exceptions
*/

    /**
     * Test convert.
     * - It must either make a successful conversion, or thwrow an exception
     * - It must return boolean
     *//*
    public function testConvert()
    {
        $source = (__DIR__ . '/test.jpg');
        $destination = (__DIR__ . '/test.webp');

        $result = WebPConvert::convert($source, $destination);

        $this->assertTrue(file_exists($destination));
        $this->assertInternalType('boolean', $result);
    }*/

    /**
     *  Basically test what happens when no converters are able to do a conversion,
     *  WebPConvert::convert should in that case return false
    */
    public function testConvertWithNoConverters()
    {
        //$this->expectException(\WebPConvert\Exceptions\NoOperationalConvertersException::class);
        $source = __DIR__ . '/test.jpg';
        $destination = __DIR__ . '/test.jpg.webp';
        $result = WebPConvert::convert($source, $destination, array(
            'converters' => array()
        ));
        $this->assertFalse($result);
    }


    public function testTargetNotFound()
    {
        $this->expectException(\WebPConvert\Exceptions\TargetNotFoundException::class);

        WebPConvert::convert(__DIR__ . '/i-dont-existno.jpg', __DIR__ . '/i-dont-exist.webp');
        //$this->assertTrue($result);
    }

    public function testInvalidDestinationFolder()
    {

        // Notice: mkdir emits a warning on failure.
        // I have reconfigured php unit to not turn warnings into exceptions (phpunit.xml.dist)
        // - if I did not do that, the exception would not be CreateDestinationFolderException

        $this->expectException(\WebPConvert\Exceptions\CreateDestinationFolderException::class);

        // I here assume that no system grants write access to their root folder
        // this is perhaps wrong to assume?
        $destinationFolder = '/you-can-delete-me/';

        WebPConvert::convert(__DIR__ . '/test.jpg', $destinationFolder . 'you-can-delete-me.webp');
    }

    /**
     * Test ConversionDeclinedException by testing Gd.
     */
    public function testDeclined()
    {
        // only try Gd
        //WebPConvert::setConverterOrder(array('gd'), true);

        // configure Gd not to convert pngs
        //WebPConvert::setConverterOption('gd', 'convert_pngs', false);

        $source = __DIR__ . '/test.png';
        $destination = __DIR__ . '/test.png.webp';
        $options = array(
            'converters' => array(
                array(
                    'converter' => 'gd',
                    'options' => array(
                        'skip-pngs' => true,
                    ),
                ),
            )
        );
        try {
            WebPConvert::convert($source, $destination, $options);
        } catch (\WebPConvert\Converters\Exceptions\ConverterNotOperationalException $e) {
            // converter isn't operational, so we cannot make the unit test
            return;
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // Yeah, this is what we want to test.

            $this->expectException(\WebPConvert\Converters\Exceptions\ConversionDeclinedException::class);
            WebPConvert::convert($source, $destination, $options);
        }
    }


    // How to test CreateDestinationFileException ?
}
