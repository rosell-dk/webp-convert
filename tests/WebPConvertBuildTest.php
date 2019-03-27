<?php

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;

use PHPUnit\Framework\TestCase;

/**
 *  Test the builds (webp-convert.inc, webp-on-demand-1.inc and webp-on-demand-2.inc)
 */
class WebPConvertBuildTest extends TestCase
{
    public function testWebPConvertBuild()
    {
        require __DIR__ . '/../build/webp-convert.inc';

        $source = __DIR__ . '/images/png-without-extension';

        WebPConvert::convertAndServe(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                'require-for-conversion' => __DIR__ . '/../build/webp-on-demand-2.inc',
                'converters' => ['imagick'],
                'aboutToServeImageCallBack' => function($servingWhat, $whyServingThis, $obj) {
                    return false;
                },
                'aboutToPerformFailActionCallback' => function ($errorTitle, $errorDescription, $actionAboutToBeTaken, $serveConvertedObj) {
                    return false;
                }
            ]
        );

    }

}
