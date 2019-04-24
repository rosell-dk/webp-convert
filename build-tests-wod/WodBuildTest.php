<?php

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;

use PHPUnit\Framework\TestCase;

/**
 *  Test the webp-on-demand builds (webp-on-demand-1.inc and webp-on-demand-2.inc)
 */
class WodBuildTest extends TestCase
{

    /**
     * @runInSeparateProcess
     */
     public function testWodBuildNotCompletelyBroken()
    {
        $buildDir = __DIR__ . '/../src-build';
        $wod1 = $buildDir . '/webp-on-demand-1.inc';
        $wod2 = $buildDir . '/webp-on-demand-2.inc';

        $this->assertTrue(file_exists($buildDir), 'build dir not found!');
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
        require $wod1;


        $source = __DIR__ . '/images/png-without-extension';

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
        WebPConvert::convertAndServe(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                'require-for-conversion' => $wod2,
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
        $this->addToAssertionCount(1);
    }
}
