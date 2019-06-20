<?php

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;

use PHPUnit\Framework\TestCase;

/**
 *  Test the webp-on-demand builds (webp-on-demand-1.inc and webp-on-demand-2.inc)
 */
class WodBuildTest extends TestCase
{

    private static $buildDir = __DIR__ . '/../src-build';

    public static function getImageFolder()
    {
        return realpath(__DIR__ . '/../tests/images');
    }

    public static function getImagePath($image)
    {
        return self::getImageFolder() . '/' . $image;
    }


    public function autoloadingDisallowed($class) {
        throw new \Exception('no autoloading expected! ' . $class);
    }

    public function autoloaderLoad($class) {
        if (strpos($class, 'WebPConvert\\') === 0) {
            require_once self::$buildDir . '/webp-on-demand-2.inc';
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testWodBuildWithoutAutoload()
    {
        // The following should NOT trigger autoloader, because ALL functionality for
        // serving existing is in webp-on-demand-1.php

        $wod1 = self::$buildDir . '/webp-on-demand-1.inc';
        $this->assertTrue(file_exists($wod1), 'webp-on-demand-1.inc not found!');
        require_once $wod1;

        spl_autoload_register([self::class, 'autoloaderLoad'], true, true);

        $source = self::getImagePath('png-without-extension');
        $this->assertTrue(file_exists($source));

        ob_start();
        WebPConvert::serveConverted(
            $source,
            $source . '.webp',
            [
                'convert' => [
                    'converters' => [
                        'gd',
                        'imagick',
                        '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                        // vips? - causes build error:
                        // PHPUnit_Framework_Exception: (banana:9793): VIPS-WARNING **: near_lossless unsupported

                        //'imagickbinary',
                        // PHPUnit_Framework_Exception: convert: delegate failed `"cwebp" -quiet -q %Q "%i" -o "%o"' @ error/delegate.c/InvokeDelegate/1310.
                    ],
                ]
                //'reconvert' => true,
                /* 'convert' => [
                    'converters' => ['imagick'],
                ]*/
                //

            ]
        );
        ob_end_clean();
        spl_autoload_unregister([self::class, 'autoloaderLoad']);

    }

    /**
     * @runInSeparateProcess
     */
    public function testWodBuildWithAutoload()
    {
        $wod1 = self::$buildDir . '/webp-on-demand-1.inc';
        $wod2 = self::$buildDir . '/webp-on-demand-2.inc';

        $this->assertTrue(file_exists(self::$buildDir), 'build dir not found!');
        $this->assertTrue(file_exists($wod1), 'webp-on-demand-1.inc not found!');
        $this->assertTrue(file_exists($wod2), 'webp-on-demand-2.inc not found!');

        /*
         As failed assertions exits the method, it is now safe to require the inc...
        If the code is unparsable, a parse error will be thrown, like this:

        There was 1 error:

        1) WebPConvert\Tests\WodBuildTest::testWodBuildNotCompletelyBroken
        ParseError: syntax error, unexpected 'ServeBase' (T_STRING)

        /var/www/wc/wc0/webp-convert/build/webp-on-demand-1.inc:7

        ERRORS!
        Tests: 1, Assertions: 3, Errors: 1.
        Script phpunit handling the test event returned with error code 2
        */
        require_once $wod1;


        $source = self::getImagePath('png-without-extension');
        $this->assertTrue(file_exists($source));

        /*
        We do not try/catch the following.
        If it errors out (which it should not), or an exception is thrown (which ought not to happpen either),
        - It will be discovered, and cause the tests to fail.

        For example, if webp-on-demand-2 is unparsable, phpunit will fail like this:

        There was 1 error:

        /var/www/wc/wc0/webp-convert/build/webp-on-demand-2.inc:9
        /var/www/wc/wc0/webp-convert/build/webp-on-demand-1.inc:293
        /var/www/wc/wc0/webp-convert/tests/WodBuildTest.php:72

        ERRORS!
        Tests: 1, Assertions: 3, Errors: 1.
        Script phpunit handling the test event returned with error code 2
        */

        /*
        WebPConvert::convertAndServe(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                //'converters' => ['imagick'],
                'aboutToServeImageCallBack' => function() {
                    // Return false, in order to cancel serving
                    return false;
                },
                'aboutToPerformFailActionCallback' => function () {
                    return false;
                }
            ]
        );
        */

        spl_autoload_register([self::class, 'autoloaderLoad'], true, true);

        ob_start();
        WebPConvert::serveConverted(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                'convert' => [
                    'converters' => [
                        'imagick',
                        'gd',
                        'cwebp',
                        '\\WebPConvert\\Tests\\Convert\\TestConverters\\SuccessGuaranteedConverter'
                    ],
                ]
            ]
        );
        ob_end_clean();
        spl_autoload_unregister([self::class, 'autoloaderLoad']);

        $this->addToAssertionCount(1);
    }
}
require_once(__DIR__ . '/../tests/Serve/mock-header.inc');
