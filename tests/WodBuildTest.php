<?php

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;

use PHPUnit\Framework\TestCase;

/**
 *  Test the builds (webp-convert.inc, webp-on-demand-1.inc and webp-on-demand-2.inc)
 */
class WodBuildTest extends TestCase
{
    public function testWodBuild()
    {

        require __DIR__ . '/../build/webp-on-demand-1.inc';

        $source = __DIR__ . '/images/png-without-extension';

        // We do not try/catch the following.
        // If it errors out (which it should not), or an exception is thrown (which ought not to happpen either),
        // - It will be discovered, and cause the tests to fail.
        WebPConvert::convertAndServe(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                'require-for-conversion' => __DIR__ . '/../build/webp-on-demand-2.inc',
                'converters' => ['imagick'],
                'aboutToServeImageCallBack' => function($servingWhat, $whyServingThis, $obj) {
                    /*
                    The first argument to the callback contains a string that tells what is about to be served.
                    It can be 'fresh-conversion', 'destination' or 'source'.

                    The second argument tells you why that is served. It can be one of the following:
                    for 'source':
                    - "explicitly-told-to"     (when the "serve-original" option is set)
                    - "source-lighter"         (when original image is actually smaller than the converted)

                    for 'fresh-conversion':
                    - "explicitly-told-to"     (when the "reconvert" option is set)
                    - "source-modified"        (when source is newer than existing)
                    - "no-existing"            (when there is no existing at the destination)

                    for 'destination':
                    - "no-reason-not-to"       (it is lighter than source, its not older, and we were not told to do otherwise)
                    */

                    // Return false, in order to cancel serving
                    return false;
                },
                'aboutToPerformFailActionCallback' => function ($errorTitle, $errorDescription, $actionAboutToBeTaken, $serveConvertedObj) {
                    return false;
                }
            ]
        );
    }
}
