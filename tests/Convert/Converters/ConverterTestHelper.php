<?php

/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\ConverterFactory;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\UnhandledException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Exceptions\InvalidInput\TargetNotFoundException;

class ConverterTestHelper
{

/*
    public function testPNGDeclined()
    {
        try {
            $source = __DIR__ . '/../test.png';
            $destination = __DIR__ . '/../test.png.webp';
            Gd::convert($source, $destination, array(
                'skip-pngs' => true,
            ));
            $testCase->fail('The conversion should have failed, because PNG should have been skipped');

        } catch (SystemRequirementsNotMetException $e) {
            // System requirements are not met, so could not make the test
            return;
        } catch (ConversionSkippedException $e) {
            // Yeah, this is what we wanted to test. And it went well!
            $testCase->assertTrue(true);
        } catch (ConversionFailedException $e) {
            $testCase->fail("A ConversionFailedException was thrown (and it was not the SystemRequirementsNotMetException)");
        } catch (\Exception $e) {
            $testCase->fail("An unexpected exception was thrown");
        }
    }
*/
    public static function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public static function getImagePath($image)
    {
        return self::getImageFolder() . '/' . $image;
    }

    private static function callConvert($converterClassName, $source, $destination, $converterOptions)
    {
        return call_user_func(
            ['WebPConvert\\Convert\\Converters\\' . $converterClassName, 'convert'],
            $source,
            $destination,
            $converterOptions
        );
        //$logger

        /*
        TODO: Consider using mikey179/vfsStream
        https://github.com/mikey179/vfsStream
        https://phpunit.de/manual/6.5/en/test-doubles.html#test-doubles.mocking-the-filesystem
        */
    }

    public static function testInvalidDestinationFolder($testCase, $converterClassName, $converterOptions)
    {
        $testCase->expectException(CreateDestinationFolderException::class);

        try {
            $source = self::getImagePath('test.jpg');
            $destination = '/you-can-delete-me/';
            $result = self::callConvert($converterClassName, $source, $destination);
        } catch (ConverterNotOperationalException $e) {
            // Converter not operational, and that is ok!
            // We shall pretend that the expected exception was thrown, by throwing it!
            throw new CreateDestinationFolderException();
        }
/*
        try {
            // We can only do this test, if the converter is operational.
            // In order to test that, we first do a normal conversion
            $source = (__DIR__ . '/../../test.jpg');
            $destination = (__DIR__ . '/../../test.webp');

            Imagick::convert($source, $destination);

            // if we are here, it means that the converter is operational.
            // Now do something that tests that the converter fails the way it should,
            // when it cannot create the destination file

            $this->expectException(\WebPConvert\Convert\Exceptions\ConverterFailedException::class);

            // I here assume that no system grants write access to their root folder
            // this is perhaps wrong to assume?
            $destinationFolder = '/you-can-delete-me/';

            Imagick::convert(__DIR__ . '/../test.jpg', $destinationFolder . 'you-can-delete-me.webp');
        } catch (\Exception $e) {
            // its ok...
        }*/
    }

    public static function testTargetNotFound($testCase, $converterClassName, $converterOptions)
    {
        $testCase->expectException(TargetNotFoundException::class);

        try {
            $result = self::callConvert(
                $converterClassName,
                __DIR__ . '/i-dont-exist.jpg',
                __DIR__ . '/i-dont-exist.webp',
                $converterOptions
            );
        } catch (ConverterNotOperationalException $e) {
            // Converter not operational, and that is ok!
            // We shall pretend that the expected exception was thrown, by throwing it!
            throw new TargetNotFoundException();
        }
    }

    /**
     * Test convert.
     * - It must either make a successful conversion, or throw the SystemRequirementsNotMetException
     *   Other exceptions are unexpected and will result in test failure
     * - It must not return anything (as of 2.0, there is no return value)
     * - If conversion is successful, there must be a file at the destination
     */
    public static function testConvert($src, $testCase, $converterClassName, $converterOptions)
    {

        try {
            $source = self::getImagePath($src);
            $destination = self::getImagePath($src . '.webp');

            $result = self::callConvert($converterClassName, $source, $destination, $converterOptions);

            // Conversion was successful.

            // make sure the function did not return anything (as of 2.0)
            $testCase->assertEmpty($result, 'The doActualConvert() method returned something. As of 2.0, converters should never return anything');

            // verify that there indeed is a file
            $testCase->assertTrue(file_exists($destination), 'There is not a converted file at the destinaiton');

        } catch (ConverterNotOperationalException $e) {
            // Converter not operational, and that is ok!
            // (ie if system requirements are not met, or the quota of a cloud converter is used up)

        } catch (UnhandledException $e) {
            // Handle the UnhandledException specially, so we can display the original error
            $prevEx = $e->getPrevious();
            $testCase->fail(
                'An UnhandledException was thrown: ' .
                get_class($prevEx). '. ' .
                $prevEx->getMessage() . '. ' .
                $prevEx->getFile() . ', line:' . $prevEx->getLine()
                //'Trace:' . $prevEx->getTraceAsString()
            );
        } catch (ConversionFailedException $e) {
            $testCase->fail(
                "A ConversionFailedException was thrown (and it was not a ConverterNotOperationalException). The exception was: " .
                get_class($e) .
                ". The message was: '" . $e->getMessage() . "'");
        } catch (\Exception $e) {
            $testCase->fail("An unexpected exception was thrown:" . get_class($e) . '. Message:' . $e->getMessage());
        }
    }

    public static function warnIfNotOperational($converterClassName)
    {
        $converter = ConverterFactory::makeConverterFromClassname(
            'WebPConvert\\Convert\\Converters\\' . $converterClassName,
            $source = self::getImagePath('test.jpg'),
            $destination = self::getImagePath('test.jpg.webp')
        );
        try {
            $converter->checkOperationality();
            //echo "\n" . $converterClassName . ' is operational.' . "\n";
        } catch (\Exception $e) {
            echo "\n" . 'NOTICE: ' . $converterClassName . ' is not operational: ' . $e->getMessage() . "\n";
        }
    }

    public static function runAllConvertTests($testCase, $converterClassName, $converterOptions = [])
    {
        self::warnIfNotOperational($converterClassName);

        $converterOptions['encoding'] = 'auto';
        self::testConvert('test.jpg', $testCase, $converterClassName, $converterOptions);
        self::testConvert('test.png', $testCase, $converterClassName, $converterOptions);
        //self::testConvert('not-true-color.png', $testCase, $converterClassName, $converterOptions);

        self::testTargetNotFound($testCase, $converterClassName, $converterOptions);
        self::testInvalidDestinationFolder($testCase, $converterClassName, $converterOptions);
    }
}
