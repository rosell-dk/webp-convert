<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;

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
        $this->expectException(ConverterNotOperationalException::class);
        $source = __DIR__ . '/test.jpg';
        $destination = __DIR__ . '/test.jpg.webp';
        $result = WebPConvert::convert($source, $destination, array(
            'converters' => array()
        ));
        //$this->assertFalse($result);
    }


    public function testTargetNotFound()
    {
        $this->expectException(TargetNotFoundException::class);

        WebPConvert::convert(__DIR__ . '/i-dont-existno.jpg', __DIR__ . '/i-dont-exist.webp');
        //$this->assertTrue($result);
    }

    public function warningHandler($errno, $errstr, $errfile, $errline)
    {
        //echo 'warning handler here';
        //return false;
    }

    public function testInvalidDestinationFolder()
    {

        // Notice: mkdir emits a warning on failure.
        // I have reconfigured php unit to not turn warnings into exceptions (phpunit.xml.dist)
        // - if I did not do that, the exception would not be CreateDestinationFolderException

        $isWindows = preg_match('/^win/i', PHP_OS);
        if ($isWindows) {
            // The test doesnt work on windows:
            // Failed asserting that exception of type "WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException" is thrown.
            // Maybe it did not get converted into an exception
            
            $this->addToAssertionCount(1);
            return;
        }

        $this->expectException(CreateDestinationFolderException::class);
        //$this->expectException(\Exception::class);


        // Set error handler in order to suppress warnings.
        // (we probably get a warning because mkdir() does not have permission to create the dir it is asked to)
        $handler = set_error_handler(
            array($this, "warningHandler"),
            E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_USER_ERROR
        );
        //echo 'previously defined handler:' . print_r($handler, true);*/

        /*
        set_error_handler(
            array($this, "warningHandler"),
            E_ALL
        );

        chown();*/

        // I here assume that no system grants write access to their root folder
        // this is perhaps wrong to assume?
        $destinationFolder = '/you-can-delete-me/';

        WebPConvert::convert(__DIR__ . '/test.jpg', $destinationFolder . 'you-can-delete-me.webp');

        restore_error_handler();
    }

    /**
     * Test ConversionSkippedException by testing Gd.
     */
     /*
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
        } catch (\WebPConvert\Convert\Exceptions\SystemRequirementsNotMetException $e) {
            // converter isn't operational, so we cannot make the unit test
            return;
        } catch (\WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException $e) {
            // Yeah, this is what we want to test.

            $this->expectException(\WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException::class);
            WebPConvert::convert($source, $destination, $options);
        }
    }*/


    // How to test CreateDestinationFileException ?
}
