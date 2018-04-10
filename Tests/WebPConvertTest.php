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
     * @expectedException \Exception
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
     * @expectedException \Exception
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
    public function testConvert()
    {
        $source = (__DIR__ . '/test.jpg');
        $destination = (__DIR__ . '/test.webp');

        $this->assertTrue(WebPConvert::convert($source, $destination));
    }
}
